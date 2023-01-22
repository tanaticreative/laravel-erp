<?php


namespace Tan\ERP\Entities;

use Tan\ERP\Contracts\EntityEvent;
use Tan\ERP\Contracts\EntitySyncState;
use Tan\ERP\Exceptions\NotSupportedByAGRException;
use Tan\ERP\Support\Facade;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

/**
 * Unit ERP Entity
 *
 * @property string $name
 */
class Unit extends BaseEntity
{
    const ENTITY_NAME = 'unit';

    /**
     * {@inheritdoc}
     */
    public function save(array $options = [])
    {
        $erpEntity = parent::find($this->id);
        if ($erpEntity) {
            // ERP wants all data for entity so we give it
            $this->fill(array_merge($erpEntity->toArray(), $this->toArray()));
            $data = Facade::getClient()->update($this);
            $this->fill($data);
            $event = 'updated';
        } else {
            $data = Facade::getClient()->create($this);
            $this->fill($data);
            $event = 'created';
        }

        $this->syncOriginal();

        Event::dispatch(EntityEvent::class, [$this, $event]);
    }


    /**
     * Init cached data
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public static function init()
    {
        Cache::delete(get_called_class() . ':all');
        Cache::rememberForever(get_called_class() . ':all', function () {
            $items = static::query()->get();
            $collection = Collection::make($items)->keyBy(function ($item, $key) {
                return $item->id;
            });

            $units = \App\Models\Unit::all();
            // WORKAROUND: this unit is used for invoices
            $unit = new \App\Models\Unit();
            $unit->id = 'EUR';
            $unit->name = 'Euro';
            $units[$unit->id] = $unit;

            foreach ($units as $modelUnit) {
                $unit = $collection->first(function ($item) use ($modelUnit) {
                    return $item->name === $modelUnit->id;
                });
                if (!$unit) {
                    $unit = new static();
                    $unit->name = $modelUnit->id;
                    $unit->description = $modelUnit->name;
                    $unit->save();
                    $collection->put($unit->id, $unit);
                }
            }

            return $collection;
        });
    }


    /**
     * @param string $name
     * @throws \Tan\ERP\Exceptions\ApiErrorException
     * @return static|null
     */
    public static function findByName(string $name)
    {
        $unit = static::all()->first(function ($item) use ($name) {
            return $item->name === $name;
        });

        return $unit;
    }


    /**
     * {@inheritdoc}
     */
    public static function find($id)
    {
        return static::all()->get($id)->first();
    }


    /**
     * {@inheritdoc}
     */
    public static function all()
    {
        if (!Cache::has(get_called_class() . ':all')) {
            static::init();
        }

        return Cache::get(get_called_class() . ':all');
    }


    /**
     * {@inheritdoc}
     */
    public function syncModel()
    {
        throw new NotSupportedByAGRException();
    }


    /**
     * {@inheritdoc}
     */
    public function createModel()
    {
        throw new NotSupportedByAGRException();
    }

    /**
     * {@inheritdoc}
     */
    public function fillFromModel(EntitySyncState $model)
    {
        throw new NotSupportedByAGRException();
    }
}
