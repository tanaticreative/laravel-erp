<?php


namespace Tan\ERP\Listeners;

use Tan\ERP\Entities\BaseEntity;
use Tan\ERP\Entities\Sales\InvoiceFee;
use Tan\ERP\ERPManager;
use Tan\ERP\Events\WebhookRequestCreateEvent;
use Tan\ERP\Events\WebhookRequestDeleteEvent;
use Tan\ERP\Events\WebhookRequestEvent;
use Tan\ERP\Events\WebhookRequestUpdateEvent;
use Tan\ERP\Models\SyncState;
use App\Components\ERP\Entities\Sales\Order;
use Illuminate\Support\Facades\Log;

/**
 * Handles syncs with updates that come from ERP
 */
class WebhookRequestListener
{
    public function handle(WebhookRequestEvent $event)
    {
        Log::channel('erp')->info('WebhookRequestListener: Handle ' . get_class($event), ['event' => $event]);
        if ($event->entityName === 'article') {
            Log::channel('erp')->warning('Article webhooks are not supported yet');
            return;
        }

        /** @var SyncState $syncState */
        $syncState = SyncState::where('entity_id', $event->entityId)->whereIn('entity_type_id', ERPManager::$mapEntities[$event->entityName])->first();

        if ($event instanceof WebhookRequestUpdateEvent) {
            $this->update($event, $syncState);
        } elseif ($event instanceof WebhookRequestDeleteEvent) {
            $this->delete($event, $syncState);
        } elseif ($event instanceof WebhookRequestCreateEvent) {
            $this->create($event, $syncState);
        } else {
            Log::channel('erp')->warning('Nothing to do. Are you sure you have provided action for webhook?');
        }
    }


    protected function delete(WebhookRequestEvent $event, ?SyncState $syncState)
    {
        if (!$syncState) {
            Log::channel('erp')->warning('syncState was not found. Probably we dont have this record. Skipping..');
            return;
        }

        Log::channel('erp')->info('Deleting AGR model..', ['target' => $syncState->target]);
        $syncState->delete();
        $syncState->target->delete();
    }


    protected function update(WebhookRequestEvent $event, ?SyncState $syncState)
    {
        if (!$syncState) {
            Log::channel('erp')->warning('syncState was not found. Probably we dont have this record. Skipping..');
            return;
        }

        Log::channel('erp')->info('Updating AGR model from ERP..');

        /** @var BaseEntity $erpEntity */
        $erpEntity = $syncState->entityToSync::find($event->entityId);
        if ($erpEntity->version == $syncState->entityToSync->version) {
            Log::channel('erp')->info("Recursion from ERP for entity '{$syncState->entity_type_id}' ({$syncState->entity_id}). Already up to date. Skipping..");
            return;
        }
        // WORKAROUND: disable events to not trigger recursion!
        call_user_func([$syncState->entityToSync->model, 'unsetEventDispatcher']);
        $syncState->entityToSync->fill($erpEntity->toArray());
        $syncState->entityToSync->syncModel();
    }


    protected function create(WebhookRequestEvent $event, ?SyncState $syncState)
    {
        if ($syncState) {
            Log::channel('erp')->info('syncState was found. Probably recursion from ERP for entity. Skipping..');
            return;
        }

        if($event->entityName == 'salesInvoice'){
            $invoice = new InvoiceFee();
            $erpEntity = $invoice::find($event->entityId);
        }


        /** @var BaseEntity $erpEntity */
       // $erpEntity = $syncState->entityToSync::find($event->entityId);
        $erpEntity->createModel();
        Log::channel('erp')->info('Created AGR model');
    }
}
