<?php


namespace Tan\ERP;

use Tan\ERP\Entities\Address;
use Tan\ERP\Entities\ArticleCategory;
use Tan\ERP\Entities\CompanyCustomer;
use Tan\ERP\Entities\CompanyLead;
use Tan\ERP\Entities\Contact;
use Tan\ERP\Entities\FakeArticle\GoodsCategory;
use Tan\ERP\Entities\FakeArticle\Service;
use Tan\ERP\Entities\PaymentMethod;
use Tan\ERP\Entities\Sales\Invoice;
use Tan\ERP\Entities\Sales\InvoiceFee;
use Tan\ERP\Entities\Sales\InvoiceTender;
use Tan\ERP\Entities\Unit;
use Tan\ERP\Entities\UserContact;
use Tan\ERP\Entities\Webhook;
use App\Components\ERP\Entities\Sales\Order;
use App\Components\ERP\Entities\Sales\OrderFee;
use App\Components\ERP\Entities\Sales\OrderTender;
use App\Components\PaymentSystem\BetterPayment\TransactionStatus;
use App\Models\Category;
use App\Models\Company;
use App\Models\Tender\Invoice\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class ERPManager
{
    protected $client;

    /**
     * @var array maps ERP entity classes with ERP entity internal names
     */
    public static $mapEntities = [
        Contact::ENTITY_NAME => [UserContact::class],
        CompanyLead::ENTITY_NAME => [CompanyLead::class],
        CompanyCustomer::ENTITY_NAME => [CompanyCustomer::class],
        Invoice::ENTITY_NAME => [InvoiceFee::class, InvoiceTender::class],
        Order::ENTITY_NAME => [OrderFee::class, OrderTender::class],
    ];

    /**
     * @var array maps AGR models with ERP entity internal names
     */
    public static $mapModels = [
        UserContact::ENTITY_NAME => User::class,
        CompanyLead::ENTITY_NAME => Company::class,
        CompanyCustomer::ENTITY_NAME => Company::class,
    ];

    /**
     * @var array maps ERP customer category with AGR company type by its name
     */
    public static $mapCustomerCategoryToCompanyType = [
        'Händler' => Company\Type::COMPANY_TYPE_MANUFACTURER,
        'Landwirt' => Company\Type::COMPANY_TYPE_FARMER,
    ];


    public function __construct()
    {
        $this->client = App::make('ERPClient');
    }

    /**
     * @return ERPClient;
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Init required environment data for AGR in ERP and cache it if possible
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function initEnvironmentForAGR()
    {
        Log::channel('erp')->info('run initEnvironmentForAGR()');
        Webhook::init();
        PaymentMethod::init();
        Unit::init();
        GoodsCategory::init();
        Service::init();
        ArticleCategory::init();
    }


    /**
     * @param \App\Models\Company $company
     * @return bool
     * @throws \Throwable
     */
    public function createCompanyLeadOrCustomer(\App\Models\Company $company)
    {
        if ($company->syncState) {
            Log::channel('erp')->warning("Cannot create model, syncState already exists", ['class' => get_class($company), 'model' => $company]);
            return false;
        }

        $contacts = [];
        foreach ($company->users as $user) {
            $userEntity = $user->syncState->entityToSync ?? new UserContact();
            $userEntity->fillFromModel($user);
            $userEntity->save();
            $contacts[] = $userEntity;
        }

        $companyEntity = $company->tenders()->count() ? new CompanyCustomer() : new CompanyLead();
        $companyEntity->fillFromModel($company);
        $companyEntity->contacts = $contacts;
        $companyEntity->save();

        $company->refresh();

        return true;
    }

    /**
     * @param \App\Models\Company $company
     * @param bool $asCustomer create as customer if TRUEm, as 'lead' otherwise. Default is FALSE
     * @return bool
     * @throws \Throwable
     */
    public function createCompany(\App\Models\Company $company, $asCustomer = false)
    {
        if ($company->syncState) {
            Log::channel('erp')->warning("Cannot create model, syncState already exists", ['class' => get_class($company), 'model' => $company]);
            return false;
        }

        $contacts = [];
        foreach ($company->users as $user) {
            $userEntity = $user->syncState->entityToSync ?? new UserContact();
            $userEntity->fillFromModel($user);
            $userEntity->save();
            $contacts[] = $userEntity;
        }

        $companyEntity = $asCustomer ? new CompanyCustomer() : new CompanyLead();
        // if ($company->created_by_Tan) $companyEntity->leadSourceName = 'Tan System';
        $companyEntity->leadSourceName = 'Tan System';

        $companyEntity->fillFromModel($company);
        $companyEntity->contacts = $contacts;

        $companyEntity->save();
        $company->refresh();

        return true;
    }


    /**
     * @param \App\Models\Company $company
     * @return CompanyCustomer|boolean
     */
    public function convertToCompanyCustomer(\App\Models\Company $company)
    {
        if (!$company->syncState) {
            Log::channel('erp')->warning("No sync state for company", ['companyId' => $company->id]);
            return false;
        }

        $entityTypeId = $company->syncState->entity_type_id;
        if ($entityTypeId !== CompanyLead::class) {
            Log::channel('erp')->warning("You cannot convert '{$entityTypeId}'. You can convert only " . CompanyLead::class, ['company' => $company]);
            return false;
        }

        /** @var CompanyLead $entity */
        $entity = $company->syncState->entityToSync;

        return $entity->convertToCompanyCustomer();
    }
}
