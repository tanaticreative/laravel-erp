<?php


namespace Tan\ERP\Observers;

use Tan\ERP\Entities\CompanyLead;
use Tan\ERP\Jobs\ConvertCompanyLeadToCustomerJob;
use App\Models\Tender;

class TenderObserver
{
    public function created(Tender $tender)
    {
//        if ($tender->company->syncState && $tender->company->syncState->entity_type_id === CompanyLead::class) {
//        }
    }
}
