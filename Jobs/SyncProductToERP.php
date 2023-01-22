<?php


namespace Tan\ERP\Jobs;

use Tan\ERP\Entities\BaseEntity;
use Tan\ERP\Entities\ProductArticle;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncProductToERP extends BaseJob
{
    public $event;
    /** @var int */
    public $productId;
    /** @var int */
    public $entityId;
    /** @var string */
    public $entityTypeId;


    /**
     * @param string $event event name
     * @param int $productId AGR
     * @param int|null $entityId ERP entity. Will be used if syncState is NULL (model was deleted)
     * @param string|null $entityTypeId ERP entity type. Will be used if syncState is NULL (model was deleted)
     */
    public function __construct($event, $productId, $entityId = null, $entityTypeId = null)
    {
        parent::__construct();

        $this->event = $event;
        $this->productId = $productId;
        $this->entityId = $entityId;
        $this->entityTypeId = $entityTypeId;
    }


    public function handle()
    {
        Log::channel('erp')->debug('SyncProductToERP: handle job');

        $model = Product::find($this->productId);
        call_user_func([$this, $this->event], $model);
    }


    protected function deleted(?Product $model)
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
            Log::channel('erp')->info("Deleted syncState for product ({$this->productId})");
        }
    }


    protected function created(Product $model)
    {
        if ($model->syncState) {
            Log::channel('erp')->warning("Cannot create product, syncState already exists", ['class' => get_class($model), 'model' => $model]);
            return;
        }

        $entity = new ProductArticle();
        $entity->fillFromModel($model);
        $entity->save();
    }


    protected function updated(Product $model)
    {
        if (!$model->syncState) {
            $this->created($model);
            return;
        }

        $model->syncState->entityToSync->fillFromModel($model);
        $model->syncState->entityToSync->save();
    }
}
