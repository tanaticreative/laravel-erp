<?php


namespace App\Components\ERP\Entities;

use Tan\ERP\Contracts\EntityEvent;
use Tan\ERP\Contracts\EntitySyncState;
use Tan\ERP\Entities\BaseEntity;
use Tan\ERP\ERPManager;
use Tan\ERP\Exceptions\NotSupportedByAGRException;
use Tan\ERP\Support\Facade;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

class LeadSource extends BaseEntity
{
    const ENTITY_NAME = 'leadSource';

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
