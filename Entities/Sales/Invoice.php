<?php


namespace Tan\ERP\Entities\Sales;

use Tan\ERP\Entities\Address;
use Tan\ERP\Entities\BaseEntity;
use Tan\ERP\Exceptions\NotSupportedByAGRException;
use Tan\ERP\Models\SyncState;
use Tan\ERP\Support\Facade;
use App\Components\ERP\Entities\Sales\Order;
use App\Components\ERP\Entities\Sales\OrderFee;
use App\Models\Company;
use App\Models\Tender\Order\Type;
use App\Models\Tender\Invoice\Type as InvoiceType;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Class Invoice
 * @package Tan\ERP\Entities
 *
 * @property string $customerId
 * @property string $customerNumber
 * @property string $commercialLanguage
 * @property Carbon $invoiceDate
 * @property string $invoiceNumber
 * @property string $commission
 * @property array $commissionSalesPartners TODO: add support if needed
 * @property string $dispatchCountryCode 'DE' etc
 * @property Carbon|null $dueDate
 * @property bool $paid
 * @property Address $recordAddress TODO: add support if needed
 * @property string $grossAmount
 * @property string $grossAmountInCompanyCurrency
 * @property string $headerDiscount
 * @property string $headerSurcharge
 * @property string $netAmount
 * @property string $netAmountInCompanyCurrency
 * @property string $nonStandardTaxId
 * @property string $nonStandardTaxName
 * @property string $orderNumberAtCustomer
 * @property string $paymentMethodId
 * @property string $paymentMethodName
 * @property Carbon $pricingDate
 * @property InvoiceItem[] $salesInvoiceItems
 * @property array $shippingCostItems TODO: add support if needed
 * @property string $status
 * @property array $statusHistory TODO: add support if needed
 * @property string $recordComment
 * @property string $recordCurrencyId
 * @property string $recordCurrencyName
 * @property string $recordFreeText
 * @property string $recordOpening
 * @property string $responsibleUserId
 * @property string $responsibleUserUsername
 * @property string $salesChannel sales channel key
 * @property string $salesInvoiceType
 * @property string $salesOrderId
 * @property string $salesOrderNumber
 * @property string $sentToRecipient
 * @property Carbon|null $servicePeriodFrom
 * @property Carbon|null $servicePeriodTo
 * @property string $shipmentMethodId
 * @property string $shipmentMethodName
 * @property string $termOfPaymentId
 * @property string $termOfPaymentName
 * @property string $vatRegistrationNumber
 *
 * @property \App\Models\Tender\Invoice $model
 */
abstract class Invoice extends BaseEntity
{
    const ENTITY_NAME = 'salesInvoice';

    const TYPE_STANDARD = 'STANDARD_INVOICE';
    const TYPE_CREDIT_NOTE = 'CREDIT_NOTE';

    const STATUS_NEW = 'NEW'; // created
    const STATUS_ENTRY_COMPLETED = 'ENTRY_COMPLETED'; // clicked 'complete entry'
    const STATUS_DOCUMENT_CREATED = 'DOCUMENT_CREATED'; // clicked 'generate invoice documents'
    const STATUS_BOOKED = 'BOOKED'; // clicked 'create open item'

    protected $attributes = [
        'paid' => false,
        'salesInvoiceType' => self::TYPE_STANDARD,
    ];

    protected $casts = [
        'invoiceDate' => 'datetime:Uv',
        'lastModifiedDate' => 'datetime:Uv',
        'createdDate' => 'datetime:Uv',
        'dueDate' => 'datetime:Uv',
        'pricingDate' => 'datetime:Uv',
        'servicePeriodFrom' => 'datetime:Uv',
        'servicePeriodTo' => 'datetime:Uv',
        'paid' => 'boolean',
        'sentToRecipient' => 'boolean',
    ];


    /**
     * @return array|bool response [RESERVED]
     */
    public function addInvoicePaidComment()
    {
        $paidTransaction = $this->model->paidTransaction;

        if (!$paidTransaction) {
            Log::channel('erp')->warning("No paid transaction while trying to add paid comment for invoice", ['invoice' => $this]);
            return false;
        }

        return Facade::getClient()->addComment($this, "Invoice was paid via BetterPayment transaction '{$paidTransaction->transaction_id}'");
    }


    /**
     * @return string
     */
    public function getPdf()
    {
        return Facade::getClient()->downloadLatestSalesInvoicePdf($this);
    }


    public function getSalesInvoiceItemsAttribute()
    {
        return $this->attributes['salesInvoiceItems'] ?? [];
    }


    public function setSalesInvoiceItemsAttribute($items)
    {
        if (!is_array($items)) {
            throw new \InvalidArgumentException("MUST BE ARRAY");
        }

        foreach ($items as &$item) {
            if ($item instanceof InvoiceItem) {
                $arr[] = $item;
                continue;
            }
            if (is_array($item)) {
                $item = new InvoiceItem($item);
                continue;
            }
            throw new \InvalidArgumentException("MUST BE INSTANCE OF " . InvoiceItem::class . " or array!");
        }

        $this->attributes['salesInvoiceItems'] = $items;
    }


    /**
     * {@inheritdoc}
     */
    public function syncModel()
    {
        $model = $this->model;
        if (!$model) {
            Log::channel('erp')->warning("Cannot sync model without model!", ['entity' => $this]);
            return;
        }

        if ($this->paid) {
            $model->status_id = \App\Models\Tender\Invoice\Status::STATUS_PAID;
        }

        $model->amount = $this->netAmount;
        $model->amount_gross = $this->grossAmount;
        $model->save();
    }


    /**
     * {@inheritdoc}
     */
    public function createModel()
    {
        $model = new \App\Models\Tender\Invoice();

        $orderId = $this->salesOrderId;

        if ($this->salesChannel == Channel::CHANNEL_KEY_FEE && !empty($orderId)) {
            $orderSyncState = SyncState::where('entity_id', $this->salesOrderId)->where('target_type_id', \App\Models\Tender\Order::ORDER_TARGET_TYPE)->firstOrFail();
            $erpOrder = OrderFee::find($orderId);

            $companyId = $erpOrder->customerId;
            $orderCompanySyncState = SyncState::where('entity_id', $companyId)->where('target_type_id', Company::COMPANY_TARGET_TYPE)->firstOrFail();

            $order = $orderSyncState->target;
            $company = $orderCompanySyncState->target;


            DB::transaction(function () use ($model, $order, $company) {
                $model->amount = $this->grossAmount; // ??
                $model->amount_gross = $this->grossAmount;
                $model->due_date = $this->dueDate;
                switch ($this->status) {
                    case Invoice::STATUS_BOOKED:
                        $model->status_id = \App\Models\Tender\Invoice\Status::STATUS_PAID;
                        break;
                    default:
                        $model->status_id = \App\Models\Tender\Invoice\Status::STATUS_OPEN;
                        break;
                }
                $model->type_id = $order->type_id ;

//                $model->type_id = $order->type_id == Type::TYPE_SERVICE_PURCHASE ?
//                    InvoiceType::TYPE_ORDER_SERVICE_PURCHASE :
//                    InvoiceType::TYPE_ORDER_TENDER_OFFER_FEE;

                $model->tender_offer_id = $order->offer_id;
                $model->tender_order_id = $order->id;
                $model->company_id = $company->id;
                $model->author_id = $company->company_manager_id;

                $model->save();

                $syncState = new SyncState();
                $syncState->entity_id = $this->id;
                $syncState->version = $this->version;
                $syncState->entity_type_id = get_called_class();
                $syncState->target()->associate($model);
                $syncState->save();
            });

            $this->model = $model;
        }


    }
}
