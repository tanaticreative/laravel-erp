<?php


namespace Tan\ERP\Entities\Sales;

use Tan\ERP\Contracts\EntitySyncState;
use Tan\ERP\Entities\ProductArticle;
use Tan\ERP\Entities\Unit;
use Tan\ERP\Exceptions\NotSupportedByAGRException;
use Tan\ERP\Models\SyncState;
use Tan\ERP\Support\Facade;
use \App\Models\Tender\Invoice as MInvoice;
use Illuminate\Support\Facades\Log;
use Tan\ERP\Entities\CompanyCustomer;

/**
 * Invoice specific for AGR
 */
class InvoiceTender extends Invoice
{
    protected $attributes = [
        'paid' => false,
        'salesInvoiceType' => self::TYPE_STANDARD,
        'salesChannel' => Channel::CHANNEL_KEY_TENDER,
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

        $this->fillForTenderOffer($model);
    }


    /**
     * @param MInvoice $model
     * @throws \Throwable
     */
    protected function fillForTenderOffer(MInvoice $model)
    {
        $tenderOffer = $model->tenderOffer;
        $company = $tenderOffer->tender->is_fixed_price ? $tenderOffer->company : $tenderOffer->tender->company;

        if (!$company->syncState) {
            Log::channel('erp')->debug("createCompanyLeadOrCustomer");
            Facade::createCompany($company, true);
        } elseif ($company->syncState->entity_type_id !== CompanyCustomer::class ) {
            Log::channel('erp')->debug("convertToCompanyCustomer");
            try {
                Facade::convertToCompanyCustomer($company);
            } catch (\Exception $e) {
                info($e->getMessage());
            }
        }

        $this->customerId = $company->syncState->entity_id;
        $this->invoiceDate = $model->created_at;
        $this->dueDate = $model->due_date;
        $this->status = __('Payed');

        $items = [];
        foreach ($tenderOffer->items as $offerItem) {
            // TODO: optimize
            // TODO: difference manualUnitPrice with unitPrice ?
            $product = $offerItem->tenderProduct->product;
            $productArticleId = null;
            if (!$product->syncState) {
                $productArticle = new ProductArticle();
                $productArticle->fillFromModel($product);
                $productArticle->save();
                $productArticleId = $productArticle->id;
            } else {
                $productArticleId = $product->syncState->entity_id;
            }
            $item = new InvoiceItem();
            $item->articleId = $productArticleId;
            $item->quantity = $offerItem->volume;
            $item->unitPrice = $offerItem->price_per_unit;
            $item->manualUnitPrice = $offerItem->price_per_unit;
            $item->unitId = Unit::findByName($offerItem->tenderProduct->unit)->id;
            $items[] = $item;
        }
        $this->salesInvoiceItems = $items;

        $this->status = Invoice::STATUS_ENTRY_COMPLETED;
    }


    /**
     * {@inheritdoc}
     */
    public function getPdf()
    {
        throw new NotSupportedByAGRException("Intentionally not supported! Tender invoice is only for internal usage!");
    }
}
