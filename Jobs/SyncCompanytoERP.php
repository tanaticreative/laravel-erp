<?php


namespace Tan\ERP\Jobs;

use Tan\ERP\Contracts\EntitySyncState;
use Tan\ERP\Entities\BaseEntity;
use Tan\ERP\Support\Facade;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncCompanytoERP extends BaseJob
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
        Log::channel('erp')->debug('SyncCompanytoERP: handle job');
        $model = Company::withTrashed()->find($this->modelId);
        call_user_func([$this, $this->event], $model);
    }


    public function deleted(?EntitySyncState $model)
    {
        if (!empty($model->syncState->entityToSync)) {
            $model->syncState->entityToSync->delete();
            return;
        }

        // model was probably deleted, so we try to delete by provided IDs
        $entityId = $model->syncState->entity_id ?? $this->entityId;
        $entityType = $model->syncState->entity_type_id ?? $this->entityTypeId;
        /** @var BaseEntity $entity */
        $entity = call_user_func([$entityType, 'find'], $entityId);
        if ($entity) {
            $entity->delete();
        }
        if (!empty($model->syncState)) {
            $model->syncState->forceDelete();
            Log::channel('erp')->info("Deleted syncState for entity '{$this->entityTypeId}' model ({$this->modelId})");
        }
    }


    protected function created(Company $company)
    {
        Facade::createCompany($company, $company->tenders()->exists());
    }


    public function updated(Company $model)
    {
        if (!$model->syncState) {
            $this->created($model);
            return;
        }

        $model->syncState->entityToSync->fillFromModel($model);
        $model->syncState->entityToSync->save();
    }
}
