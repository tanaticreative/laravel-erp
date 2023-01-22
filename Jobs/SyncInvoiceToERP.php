<?php


namespace Tan\ERP\Jobs;

use Tan\ERP\Entities\PaymentMethod;
use Tan\ERP\Entities\Sales\InvoiceFee;
use Tan\ERP\Entities\Sales\InvoiceTender;
use App\Components\ERP\Entities\Sales\Order;
use App\Components\ERP\Entities\Sales\OrderFee;
use App\Components\ERP\Entities\Sales\OrderTender;
use App\Components\PaymentSystem\BetterPayment\PaymentSystem;
use App\Models\Notification\SubscriberType;
use App\Models\Tender\Invoice;
use App\Notifications\TenderInvoiceNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades;
use App\Notifications\Tender\UserSendInvoice;

class SyncInvoiceToERP extends BaseJob
{
    public $event;
    /** @var int */
    public $modelId;
    /** @var int */
    public $entityId;
    public $entityTypeId;


    /**
     * @param string $event event name
     * @param int $modelId AGR
     * @param int|null $entityId ERP entity. If NULL, consider as new record or just take from syncState
     * @param string|null $entityTypeId
     */
    public function __construct($event, $modelId, $entityId = null, $entityTypeId = null)
    {
        parent::__construct();

        $this->event = $event;
        $this->modelId = $modelId;
        $this->entityId = $entityId;
        $this->entityTypeId = $entityTypeId;
    }


    public function handle()
    {
        Log::channel('erp')->debug("SyncInvoiceToERP: handle job for event '{$this->event}'");
        $model = Invoice::find($this->modelId);
        call_user_func([$this, $this->event], $model);
    }


    public function deleted(?Invoice $model): void
    {
        if (!empty($model)) {
            Log::channel('erp')->warning('Invoice was deleted in AGR but we cannot delete it at ERP', ['invoiceId' => $model->id]);
        }
    }


    protected function invoiceInitiated(Invoice $model): void
    {
        if ($model->syncState) {
            Log::channel('erp')->warning("Cannot create invoice, syncState already exists", ['class' => get_class($model), 'model' => $model]);
            return;
        }

        if ($model->type_id === Invoice\Type::TYPE_SERVICE_PURCHASE) {
            $invoiceFee = new InvoiceFee();
            $invoiceFee->fillFromModel($model);
            $invoiceFee->save();

        } elseif ($model->type_id === Invoice\Type::TYPE_TENDER_OFFER_FEE) {

            $invoiceFee = new InvoiceFee();
            $invoiceFee->fillFromModel($model);
            $invoiceFee->save();
            $invoiceTender = new InvoiceTender();
            $invoiceTender->fillFromModel($model);
            $invoiceTender->save();
        } else {
            throw new \InvalidArgumentException("Unsupported type for tender_invoice {$model->id}!");
        }

        Notification::send($model->author, new TenderInvoiceNotification(
            $model,
            TenderInvoiceNotification::USER_SEND_INVOICE,
            SubscriberType::SUBSCRIBER_TYPE_TENDER_AUTHOR
        ));
        // Notification::send($model->author, new UserSendInvoice($model));
    }


    public function invoicePaid(Invoice $model): void
    {
        if (!$model->syncState) {
            $this->invoiceInitiated($model);
            $model->refresh();
        }

        foreach ($model->syncStates as $syncState) {
            if ($syncState->entity_type_id === InvoiceFee::class) {
                /** @var Order $entityToSync */
                $entityToSync = $syncState->entityToSync;
                $entityToSync->addInvoicePaidComment();
                $paidTransaction = $model->paidTransaction;
                if ($paidTransaction && $paidTransaction->gateway === PaymentSystem::GATEWAY_BETTERPAYMENT) {
                    $entityToSync->paymentMethodId = PaymentMethod::findByName(PaymentMethod::PAYMENT_METHOD_BETTERPAYMENT_NAME)->id;
                }
                $entityToSync->status = \Tan\ERP\Entities\Sales\Invoice::STATUS_BOOKED;
                $entityToSync->save();
            }
        }
    }
}
