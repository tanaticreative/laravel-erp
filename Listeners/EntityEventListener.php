<?php


namespace Tan\ERP\Listeners;

use Tan\ERP\Entities\BaseEntity;
use Illuminate\Support\Facades\Log;

/**
 * Listen updates on entities
 *
 * Class EntityListener
 * @package Tan\ERP\Listeners
 */
class EntityEventListener
{
    public function __construct($payload = null)
    {
    }


    /**
     * @param BaseEntity $entity
     * @param string $event
     * @throws \Tan\ERP\Exceptions\ApiErrorException
     */
    public function handle(BaseEntity $entity, string $event)
    {
        Log::channel('erp')->info("EntityEventListener: Handled entity event '$event", ['entityId' => $entity->id, 'entityClass' => get_class($entity)]);
        //NOTE: add other logic on after event here
    }
}
