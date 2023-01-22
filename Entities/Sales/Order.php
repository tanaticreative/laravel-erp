<?php


namespace App\Components\ERP\Entities\Sales;

use Tan\ERP\Entities\Address;
use Tan\ERP\Entities\BaseEntity;
use Tan\ERP\Exceptions\NotSupportedByAGRException;
use Tan\ERP\Models\SyncState;
use Tan\ERP\Support\Facade;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

abstract class Order extends BaseEntity
{
    const ENTITY_NAME = 'salesOrder';

    const TYPE_STANDARD = 'STANDARD';

    const STATUS_NEW = 'ORDER_ENTRY_IN_PROGRESS'; // created
    const STATUS_CONFIRM = 'ORDER_CONFIRMATION_PRINTED'; // click create order confirmation
    const STATUS_ENTRY_COMPLETED = 'ENTRY_COMPLETED'; // clicked 'complete entry'
    const STATUS_DOCUMENT_CREATED = 'DOCUMENT_CREATED'; // clicked 'generate invoice documents'
    const STATUS_BOOKED = 'BOOKED'; // clicked 'create open item'

    protected $attributes = [
        'paid' => false,
        'salesInvoiceType' => self::TYPE_STANDARD,
    ];

    protected $casts = [
        'orderDate' => 'datetime:Uv',
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
    public function addOrderPaidComment()
    {
        $paidTransaction = $this->model->invoices->first()->paidTransaction;

        if (!$paidTransaction) {
            Log::channel('erp')->warning("No paid transaction while trying to add paid comment for order", ['order' => $this]);
            return false;
        }

        return Facade::getClient()->addComment($this, "Order was paid via BetterPayment transaction '{$paidTransaction->transaction_id}'");
    }


    /**
     * @return string
     */
    public function getPdf()
    {
        return Facade::getClient()->downloadLatestSalesInvoicePdf($this);
    }


    public function getOrderItemsAttribute()
    {
        return $this->attributes['orderItems'] ?? [];
    }


    public function setOrderItemsAttribute($items)
    {
        if (!is_array($items)) {
            throw new \InvalidArgumentException("MUST BE ARRAY");
        }

        foreach ($items as &$item) {
            if ($item instanceof OrderItem) {
                $arr[] = $item;
                continue;
            }
            if (is_array($item)) {
                $item = new OrderItem($item);
                continue;
            }
            throw new \InvalidArgumentException("MUST BE INSTANCE OF " . OrderItem::class . " or array!");
        }

        $this->attributes['orderItems'] = $items;
    }

    /**
     * {@inheritdoc}
     */
    public function syncModel()
    {
        DB::transaction(function () {
            $model = $this->model;
            if (!$model) {
                Log::channel('erp')->warning("Cannot sync model without model!", ['entity' => $this]);
                return;
            }

            if ($this->paid) {
                $model->status_id = \App\Models\Tender\Order\Status::STATUS_PAID;
            }

            $model->amount = $this->netAmount;
            $model->amount_gross = $this->grossAmount;
            $model->save();

            if ($this->model->syncState) {
                $syncState = $this->model->syncState;
            } else {
                $syncState = new SyncState();
            }

            $syncState->entity_id = $this->id;
            $syncState->version = $this->version;
            if(empty($syncState->entity_type_id)){
                $syncState->entity_type_id = get_called_class();
            }
            //$syncState->save();
            $syncState->target()->associate($this->model);
            $syncState->save();
        });
    }


    /**
     * {@inheritdoc}
     */
    public function createModel()
    {
        throw new NotSupportedByAGRException();
    }
}
