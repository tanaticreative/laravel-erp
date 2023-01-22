<?php

namespace App\Components\ERP\Observers;
use App\Components\ERP\Jobs\SyncOrderToERP;
use App\Models\Tender\Order;

class OrderObserver
{
    public function orderInitiated(Order $order)
    {
        SyncOrderToERP::dispatch('orderInitiated', $order->id);
    }

    public function orderPaid(Order $order)
    {
        info(__METHOD__);
         SyncOrderToERP::dispatch('orderPaid', $order->id);
    }

    public function deleted(Order $order)
    {
        if ($order->syncState) {
            SyncOrderToERP::dispatch('deleted', $order->id, $order->syncState->entity_id, $order->syncState->entity_type_id);
        }
    }
}
