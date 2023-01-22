<?php


namespace Tan\ERP\Models;

use Tan\ERP\Entities\BaseEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use \Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Collection;

/**
 * Use this trait on target model that you want to sync with ERP
 *
 * @mixin Model
 *
 * @property-read SyncState $syncState
 * @property-read SyncState[]|Collection $syncStates
 * @property-read BaseEntity $syncEntity
 */
trait SyncTrait
{
    /**
     * @return MorphOne
     */
    public function syncState()
    {
        return $this->morphOne(SyncState::class, 'target', 'target_type_id', 'target_id');
    }


    /**
     * @return MorphMany
     */
    public function syncStates()
    {
        return $this->morphMany(SyncState::class, 'target', 'target_type_id', 'target_id');
    }


    /**
     * @return BaseEntity|null
     */
    public function getSyncEntityAttribute()
    {
        return $this->syncState->entityToSync ?? null;
    }
}
