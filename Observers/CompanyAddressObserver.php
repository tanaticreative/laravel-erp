<?php


namespace Tan\ERP\Observers;

use Tan\ERP\Jobs\SyncCompanytoERP;
use App\Models\Company;

class CompanyAddressObserver
{
    public function updated(Company\Address $address)
    {
        if ($address->isLegalAddress()) {
            SyncCompanytoERP::dispatch('updated', $address->company->id);
        }
    }
}
