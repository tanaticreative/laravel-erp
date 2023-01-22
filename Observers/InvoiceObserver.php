<?php


namespace Tan\ERP\Observers;

use Tan\ERP\Jobs\SyncInvoiceToERP;
use App\Models\Tender\Invoice;

class InvoiceObserver
{
//    public function created(Invoice $invoice)
//    {
//        // NOTE: This event cannot be used because purchases are not available at this point. Use 'invoiceInitiated' instead
//    }


    public function invoiceInitiated(Invoice $invoice)
    {
        SyncInvoiceToERP::dispatch('invoiceInitiated', $invoice->id);
    }


//    public function updated(Invoice $invoice)
//    {
//        // NOTE: not supported by us intentionally. Use 'invoicePaid' instead
//    }

    public function invoicePaid(Invoice $invoice)
    {
        SyncInvoiceToERP::dispatch('invoicePaid', $invoice->id);
    }


    public function deleted(Invoice $invoice)
    {
        if ($invoice->syncState) {
            SyncInvoiceToERP::dispatch('deleted', $invoice->id, $invoice->syncState->entity_id, $invoice->syncState->entity_type_id);
        }
    }
}
