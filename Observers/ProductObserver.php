<?php


namespace Tan\ERP\Observers;

use Tan\ERP\Jobs\SyncProductToERP;
use App\Models\Product;

class ProductObserver
{
    public function created(Product $product)
    {
        SyncProductToERP::dispatch('created', $product->id);
    }


    public function updated(Product $product)
    {
        SyncProductToERP::dispatch('updated', $product->id);
    }


    public function deleted(Product $product)
    {
        if ($product->syncState) {
            SyncProductToERP::dispatch('deleted', $product->id, $product->syncState->entity_id, $product->syncState->entity_type_id);
        }
    }
}
