<?php

namespace Tan\ERP\Entities;

use Tan\ERP\Contracts\EntityEvent;
use Tan\ERP\Contracts\EntitySyncState;
use Tan\ERP\ERPManager;
use Tan\ERP\Exceptions\NotSupportedByAGRException;
use Tan\ERP\Support\Facade;
use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

/**
 * Gets and caches all customer categories on first request.<br>
 * If category from company type mapper does not exist - it will be created
 *
 * {@inheritdoc}
 *
 * @property string $name
 * @property bool $active
 * @property int $positionNumber
 */
class CustomerTopics extends BaseEntity
{
    const ENTITY_NAME = 'customerTopic';


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

            foreach (Category::getGoods() as $cat) {
                $category = $collection->first(function ($item) use ($cat) {
                    return $item->name === $cat->name;
                });

                if (!$category) {
                    $category = new static();
                    $category->name = $cat->name;
                    $category->active = true;
                    $category->save();
                    $collection->put($category->id, $category);
                }
            }

            return $collection;
        });
    }

    /**
     * @param \App\Models\Company|null $company
     * @return static|null
     * @throws \Tan\ERP\Exceptions\ApiErrorException
     */
    public static function findByCompanyModel(?\App\Models\Company $company)
    {
        if (!$company) {
            return null;
        }

        $goods = [];
        $goodsIDs = $company->manager->settings['goods'] ?? [];

        Category::getGoods()->each(function ($item) use ($goodsIDs, &$goods) {
            if (in_array($item->id, $goodsIDs)) {
                $goods[] = ['id' => static::all()->first(function ($el) use ($item) {
                    return $el->name === $item->name;
                })->id, 'name' => $item->name];
            }
        });

        return $goods;
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
