<?php


namespace App\Components\ERP\Entities\Sales;


use Tan\ERP\Contracts\EntitySyncState;
use Tan\ERP\Entities\BaseEntity;
use Illuminate\Support\Facades\Cache;

class Stage extends BaseEntity
{
    const ENTITY_NAME = 'salesStage';

    public static function find($id)
    {
        return static::all()->get($id)->first();
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