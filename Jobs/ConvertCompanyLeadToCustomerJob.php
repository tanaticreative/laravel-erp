<?php


namespace Tan\ERP\Jobs;

use Tan\ERP\Support\Facade;
use App\Models\Company;
use Illuminate\Support\Facades\Log;

class ConvertCompanyLeadToCustomerJob extends BaseJob
{
    public $company;


    public function __construct(Company $company)
    {
        parent::__construct();

        $this->company = $company;
    }


    public function handle()
    {
        Log::channel('erp')->debug('ConvertCompanyLeadToCustomerJob: handle job');

        $entityConverted = Facade::convertToCompanyCustomer($this->company);
    }
}
