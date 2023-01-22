<?php


namespace App\Components\ERP\Jobs;
use Tan\ERP\Entities\PaymentMethod;
use Tan\ERP\Entities\Sales\InvoiceFee;
use Tan\ERP\Entities\Sales\InvoiceTender;
use Tan\ERP\Jobs\BaseJob;
use App\Components\ERP\Entities\Sales\Order;
use App\Components\ERP\Entities\Sales\OrderFee;
use App\Components\ERP\Entities\Sales\OrderTender;
use App\Components\PaymentSystem\BetterPayment\PaymentSystem;
use App\Models\Notification\SubscriberType;
use App\Models\Tender\Invoice;
use App\Models\Tender\Order\Type;
use App\Notifications\TenderInvoiceNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades;
use App\Notifications\Tender\UserSendInvoice;
use App\Models\Tender\Order as MOrder;

class SyncOrderToERP extends BaseJob
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
        Log::channel('erp')->debug("SyncOrderToERP: handle job for event '{$this->event}'");
        $model = MOrder::find($this->modelId);
        call_user_func([$this, $this->event], $model);
    }


    public function deleted(?MOrder $model): void
    {
        if (!empty($model)) {
            Log::channel('erp')->warning('Order was deleted in AGR but we cannot delete it at ERP', ['invoiceId' => $model->id]);
        }
    }


    protected function orderInitiated(MOrder $model): void
    {
        if ($model->syncState) {
            Log::channel('erp')->warning("Cannot create order, syncState already exists", ['class' => get_class($model), 'model' => $model]);
            return;
        }

        if ($model->type_id === Type::TYPE_SERVICE_PURCHASE) {
            $orderFee = new OrderFee();
            $orderFee->fillFromModel($model);
            $orderFee->save();
        } elseif ($model->type_id === Type::TYPE_TENDER_OFFER_FEE) {
            $orderFee = new OrderFee();
            $orderFee->fillFromModel($model);
            $orderFee->save();

            $orderTender = new OrderTender();
            $orderTender->fillFromModel($model);
            $orderTender->save();

        } else {
            throw new \InvalidArgumentException("Unsupported type for tender_order {$model->id}!");
        }

//        Notification::send($model->author, new TenderInvoiceNotification(
//            $model,
//            TenderInvoiceNotification::USER_SEND_INVOICE,
//            SubscriberType::SUBSCRIBER_TYPE_TENDER_AUTHOR
//        ));
        // Notification::send($model->author, new UserSendInvoice($model));
    }


    public function orderPaid(MOrder $model): void
    {
        info(__METHOD__);

        if($model->type_id === Type::TYPE_SERVICE_PURCHASE){
            $orderFee = new OrderFee();
            $paidTransaction = $model->invoices->first()->paidTransaction;
            $orderFee->fillFromModel($model);
            if ($paidTransaction && $paidTransaction->gateway === PaymentSystem::GATEWAY_BETTERPAYMENT) {
                $orderFee->paymentMethodId = PaymentMethod::findByName(PaymentMethod::PAYMENT_METHOD_BETTERPAYMENT_NAME)->id;
                $orderFee->paid = true;
            }
            $orderFee->status = Order::STATUS_NEW;
            $orderFee->save();
        }
//        if (!$model->syncState) {
//            $this->orderInitiated($model);
//            $model->refresh();
//        }
//
//        foreach ($model->syncStates as $syncState) {
//            if ($syncState->entity_type_id === OrderFee::class) {
//                /** @var MOrder $entityToSync */
//                $entityToSync = $syncState->entityToSync;
//              //  $entityToSync->addOrderPaidComment();
//                $paidTransaction = $model->invoices->first()->paidTransaction;
//                info('paidTransaction');
//                info($paidTransaction);
//                if ($paidTransaction && $paidTransaction->gateway === PaymentSystem::GATEWAY_BETTERPAYMENT) {
//                    $entityToSync->paymentMethodId = PaymentMethod::findByName(PaymentMethod::PAYMENT_METHOD_BETTERPAYMENT_NAME)->id;
//                }
//
//             //   $entityToSync->paid = true;
//                $entityToSync->status = Order::STATUS_NEW; // need status for this; by default the same status for all
//                info($entityToSync);
//                $entityToSync->save();
//            }
//        }
    }
}
