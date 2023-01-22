<?php


namespace Tan\ERP\Observers;

use Tan\ERP\Jobs\SyncCompanytoERP;
use App\Models\Company;

class CompanyObserver
{
    public function created(Company $company)
    {
        if ($company->isVerified) {
            SyncCompanytoERP::dispatch('created', $company->id);
        }
    }


    public function updated(Company $company)
    {
        if ($company->isVerified || !empty($company->syncState->entity_id)) {
            SyncCompanytoERP::dispatch('updated', $company->id);
        }
    }


    public function deleted(Company $company)
    {
        if (!$company->syncState) {
            return;
        }

        SyncCompanytoERP::dispatch('deleted', $company->id, $company->syncState->entity_id, $company->syncState->entity_type_id);
    }
}
