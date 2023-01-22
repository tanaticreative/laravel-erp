<?php


namespace App\Components\ERP\Entities\Sales;
use Tan\ERP\Contracts\EntitySyncState;
use Tan\ERP\Entities\FakeArticle\GoodsCategory;
use Tan\ERP\Entities\Sales\Channel;
use Tan\ERP\Models\SyncState;
use Tan\ERP\Support\Facade;
use \App\Models\Tender\Order as MOrder;
use \App\Models\Tender\Invoice as MInvoice;
use Illuminate\Support\Facades\Log;
use Tan\ERP\Entities\PaymentMethod;
use App\Components\PaymentSystem\BetterPayment\PaymentSystem;
use Tan\ERP\Entities\CompanyCustomer;
use Tan\ERP\Entities\FakeArticle\Service;

class OrderFee extends Order
{
    protected $attributes = [
        'paid' => false,
        'salesOrderPaymentType' => self::TYPE_STANDARD,
        'salesChannel' => Channel::CHANNEL_KEY_FEE
    ];

    /**
     * {@inheritdoc}
     */
    public function fillFromModel(EntitySyncState $model)
    {
        if (!($model instanceof MOrder)) {
            throw new \InvalidArgumentException("Only instance of " . MOrder::class . ' is supported');
        }

        parent::fillFromModel($model);

        /** @var SyncState $syncState */
        $syncState = $model->syncStates()->where('entity_type_id', static::class)->first();

        if ($syncState) { // cannot update
            return;
        }

        if ($model->type_id === MOrder\Type::TYPE_SERVICE_PURCHASE) {
            $this->fillForServicePurchase($model);
        } elseif ($model->type_id === MOrder\Type::TYPE_TENDER_OFFER_FEE) {
            $this->fillForTenderOffer($model);
        }
    }


    /**
     * @param MOrder $model
     * @throws \Throwable
     */
    protected function fillForTenderOffer(MOrder $model)
    {
        $tenderOffer = $model->tenderOffer;
        $company = $tenderOffer->tender->is_fixed_price ?  $tenderOffer->tender->company: $tenderOffer->company;
        // $company = $model->company;

        if (!$company->syncState) {
            Log::channel('erp')->debug("createCompanyLeadOrCustomer");
            Facade::createCompany($company, true);
        } elseif ($company->syncState->entity_type_id !== CompanyCustomer::class) {
            Log::channel('erp')->debug("convertToCompanyCustomer");
            Facade::convertToCompanyCustomer($company);
        }

        $this->customerId = $company->syncState->entity_id;
        $this->orderDate = $model->created_at;
        $this->dueDate = $model->due_date;
        $this->status = __('Open');

        $item = new OrderItem();
        $item->note = $tenderOffer->tender->slug;
        $item->articleId = GoodsCategory::findByModel($tenderOffer->tender->category,$tenderOffer->tender->is_fixed_price )->id;
        $item->quantity = $tenderOffer->price;
        $this->orderItems = [$item];

        $this->status = Order::STATUS_NEW;
    }


    /**
     * @param MOrder $model
     * @throws \Tan\ERP\Exceptions\ApiErrorException
     * @throws \Tan\ERP\Exceptions\ApiNotFoundException
     * @throws \Throwable
     */
    protected function fillForServicePurchase(MOrder $model)
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
        $this->orderDate = $model->created_at;
        $this->dueDate = $model->due_date;

        $items = [];
        foreach ($model->invoices->first()->purchases as $purchase) {
            $item = new OrderItem();
            $this->note = '';

            foreach ($model->tenderOffer()->get() as $tender){
                $this->note .= $tender->slug.' ';
            }

            $item->articleId = Service::findByModel($purchase->service)->id;
            $item->quantity = 1;
            $items[] = $item;
        }
        $this->orderItems = $items;

        //
        $paidTransaction = $model->invoices->first()->paidTransaction;
        if ($paidTransaction && $paidTransaction->gateway === PaymentSystem::GATEWAY_BETTERPAYMENT) {
            $this->paymentMethodId = PaymentMethod::findByName(PaymentMethod::PAYMENT_METHOD_BETTERPAYMENT_NAME)->id;
        }

        $this->status = Order::STATUS_NEW;
    }
}
