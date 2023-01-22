<?php

namespace Tan\ERP\Jobs;

use Tan\ERP\Entities\BaseEntity;
use Tan\ERP\Entities\CompanyCustomer;
use Tan\ERP\Entities\CompanyLead;
use Tan\ERP\Entities\UserContact;
use Tan\ERP\Models\SyncState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncUserToERP extends BaseJob
{
    public $event;
    /** @var int */
    public $userId;
    /** @var int */
    public $entityId;
    /** @var string */
    public $entityTypeId;


    /**
     * @param string $event event name
     * @param int $userId AGR
     * @param int|null $entityId ERP entity. Will be used if syncState is NULL (model was deleted)
     * @param string|null $entityTypeId ERP entity type. Will be used if syncState is NULL (model was deleted)
     */
    public function __construct($event, $userId, $entityId = null, $entityTypeId = null)
    {
        parent::__construct();

        $this->event = $event;
        $this->userId = $userId;
        $this->entityId = $entityId;
        $this->entityTypeId = $entityTypeId;
    }


    public function handle()
    {
        Log::channel('erp')->debug('SyncUserToERP: handle job');
        $model = User::withTrashed()->find($this->userId);
        call_user_func([$this, $this->event], $model);
    }


    protected function deleted(?User $model)
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
            Log::channel('erp')->info("Deleted syncState for user ({$this->userId})");
        }
    }


    protected function created(User $model)
    {
        if ($model->syncState) {
            Log::channel('erp')->warning("Cannot create model, syncState already exists", ['class' => get_class($model), 'model' => $model]);
            return;
        }

        /** @var SyncState $companySyncState */
        $companySyncState = SyncState::where(['entity_id' => $this->entityId])
            ->whereIn('entity_type_id', [CompanyLead::class, CompanyCustomer::class])
            ->first();
        $companyEntity = $companySyncState->entityToSync ?? new CompanyLead();

        $modelEntity = new UserContact();
        $modelEntity->fillFromModel($model);
        $modelEntity->save();

        $companyEntity->fillFromModel($model->company);
        $contacts = $companyEntity->contacts;
        $contacts[] = $modelEntity;
        $companyEntity->contacts = $contacts;
        $companyEntity->save();
    }


    protected function updated(User $model)
    {
        if (!$model->syncState) {
            $this->created($model);
            return;
        }

        $model->syncState->entityToSync->fillFromModel($model);
        $model->syncState->entityToSync->save();
    }
}
