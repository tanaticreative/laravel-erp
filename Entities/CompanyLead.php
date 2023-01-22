<?php


namespace Tan\ERP\Entities;

use Tan\ERP\Contracts\EntityEvent;
use Tan\ERP\Support\Facade;
use Illuminate\Support\Facades\Event;

/**
 * {@inheritdoc}
 */
class CompanyLead extends Company
{
    const ENTITY_NAME = 'lead';

    public function convertToCompanyCustomer()
    {
        $syncState = $this->model->syncState;

        $companyCustomer = Facade::getClient()->convertLeadToCompanyCustomer($this);
        $syncState->entity_id = $companyCustomer->id;
        $syncState->entity_type_id = get_class($companyCustomer);
        $syncState->version = $companyCustomer->version;
        $syncState->save();

        Event::dispatch(EntityEvent::class, [$this, 'convertedToCustomer']);

        return $companyCustomer;
    }
}
