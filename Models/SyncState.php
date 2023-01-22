<?php


namespace Tan\ERP\Models;

use Tan\ERP\Contracts\EntitySyncState;
use Tan\ERP\Entities\BaseEntity;
use Illuminate\Database\Eloquent\Model;
use \Illuminate\Database\Eloquent\Builder;
use \Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\ErpSync
 *
 * @property int $id
 * @property int $entity_id ERP entity
 * @property string $entity_type_id ERP entity type
 * @property int $target_id AGR target model
 * @property int $target_type_id AGR AGR target model type
 * @property int $version used for optimistic lock
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read BaseEntity $entityToSync
 * @property-read Model|EntitySyncState $target
 *
 * @method static Builder|static newModelQuery()
 * @method static Builder|static newQuery()
 * @method static Builder|static query()
 * @method static Builder|static whereCreatedAt($value)
 * @method static Builder|static whereId($value)
 * @method static Builder|static whereTargetId($value)
 * @method static Builder|static whereTargetTypeId($value)
 * @method static Builder|static whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class SyncState extends Model
{
    protected $table = 'erp_sync_state';
    protected $fillable = ['entity_id', 'entity_type_id', 'target_id', 'target_type_id', ];
    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
    ];

    protected $_entityToSync = false;


    /**
     * {@inheritdoc}
     */
    public function refresh()
    {
        $this->_entityToSync = null;
        return parent::refresh();
    }


    /**
     * @return BaseEntity|null
     */
    public function getEntityToSyncAttribute()
    {
        if ($this->_entityToSync === false) {
            $entity = null;
            if ($this->target) {
                /** @var BaseEntity $entity */
                $entity = new $this->entity_type_id();
                $entity->id = $this->entity_id;
                $entity->version = $this->version;
                $entity->fillFromModel($this->target);
            }
            $this->_entityToSync = $entity;
        }

        return $this->_entityToSync;
    }


    /**
     * @return MorphTo
     */
    public function target()
    {
        return $this->morphTo('target', 'target_type_id', 'target_id');
    }
}
