<?php


namespace Tan\ERP\Entities\Sales;

use Tan\ERP\Contracts\EntitySyncState;
use Tan\ERP\Entities\FakeArticle\GoodsCategory;
use Tan\ERP\Models\SyncState;
use Tan\ERP\Support\Facade;
use \App\Models\Tender\Invoice as MInvoice;
use Illuminate\Support\Facades\Log;
use Tan\ERP\Entities\PaymentMethod;
use App\Components\PaymentSystem\BetterPayment\PaymentSystem;
use Tan\ERP\Entities\CompanyCustomer;
use Tan\ERP\Entities\FakeArticle\Service;

/**
 * Invoice specific for AGR
 */
class InvoiceFee extends Invoice
{
    protected $attributes = [
        'paid' => false,
        'salesInvoiceType' => self::TYPE_STANDARD,
        'salesChannel' => Channel::CHANNEL_KEY_FEE
    ];


    /**
     * {@inheritdoc}
     */
    public function fillFromModel(EntitySyncState $model)
    {
        if (!($model instanceof MInvoice)) {
            throw new \InvalidArgumentException("Only instance of " . MInvoice::class . ' is supported');
        }

        parent::fillFromModel($model);

        /** @var SyncState $syncState */
        $syncState = $model->syncStates()->where('entity_type_id', static::class)->first();

        if ($syncState) { // cannot update
            return;
        }

        if ($model->type_id === MInvoice\Type::TYPE_SERVICE_PURCHASE) {
            $this->fillForServicePurchase($model);
        } elseif ($model->type_id === MInvoice\Type::TYPE_TENDER_OFFER_FEE) {
            $this->fillForTenderOffer($model);
        }
    }


    /**
     * @param MInvoice $model
     * @throws \Throwable
     */
    protected function fillForTenderOffer(MInvoice $model)
    {
        $tenderOffer = $model->tenderOffer;

        if (!$tenderOffer->company->syncState) {
            Log::channel('erp')->debug("createCompanyLeadOrCustomer");
            Facade::createCompany($tenderOffer->company, true);
        } elseif ($tenderOffer->company->syncState->entity_type_id !== CompanyCustomer::class) {
            Log::channel('erp')->debug("convertToCompanyCustomer");
            Facade::convertToCompanyCustomer($tenderOffer->company);
        }

        $this->customerId = $tenderOffer->company->syncState->entity_id;
        $this->invoiceDate = $model->created_at;
        $this->dueDate = $model->due_date;
        $this->status = __('Open');

        $item = new InvoiceItem();
        $item->note = $tenderOffer->tender->slug;
        $item->articleId = GoodsCategory::findByModel($tenderOffer->tender->category)->id;
        $item->quantity = $tenderOffer->price;
        $this->salesInvoiceItems = [$item];

        $this->status = Invoice::STATUS_DOCUMENT_CREATED;
    }


    /**
     * @param MInvoice $model
     * @throws \Tan\ERP\Exceptions\ApiErrorException
     * @throws \Tan\ERP\Exceptions\ApiNotFoundException
     * @throws \Throwable
     */
    protected function fillForServicePurchase(MInvoice $model)
    {
        if (!$model->company->syncState) {
            Log::channel('erp')->debug("createCompanyLeadOrCustomer");
            Facade::createCompany($model->company, true);
        } elseif ($model->company->syncState->entity_type_id !== CompanyCustomer::class) {
            Log::channel('erp')->debug("convertToCompanyCustomer");
            Facade::convertToCompanyCustomer($model->company);
        }
        $model->company->refresh();
        $this->customerId = $model->company->syncState->entity_id;
        $this->invoiceDate = $model->created_at;
        $this->dueDate = $model->due_date;

        $items = [];
        foreach ($model->purchases as $purchase) {
            $item = new InvoiceItem();

            $item->note = $model->tenders()->get()->first()->slug;
            $item->articleId = Service::findByModel($purchase->service)->id;
            $item->quantity = 1;
            $items[] = $item;
        }
        $this->salesInvoiceItems = $items;

        //
        $paidTransaction = $model->paidTransaction;
        if ($paidTransaction && $paidTransaction->gateway === PaymentSystem::GATEWAY_BETTERPAYMENT) {
            $this->paymentMethodId = PaymentMethod::findByName(PaymentMethod::PAYMENT_METHOD_BETTERPAYMENT_NAME)->id;
        }

        $this->status = Invoice::STATUS_DOCUMENT_CREATED;
    }
}
