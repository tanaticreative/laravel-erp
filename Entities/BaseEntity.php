<?php

namespace Tan\ERP\Entities;

use Tan\ERP\Contracts\EntityEvent;
use Tan\ERP\Contracts\EntitySyncState;
use Tan\ERP\Models\SyncState;
use Tan\ERP\SimpleQuery;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Concerns\GuardsAttributes;
use Illuminate\Database\Eloquent\Concerns\HasAttributes;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HidesAttributes;
use Illuminate\Database\Eloquent\JsonEncodingException;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

//TODO: when have time, rewrite with proper query builder!
/**
 * @property int $id
 * @property int $version used for optimistic locks
 * @property string $description entity description. Some entities don't have this field, but we don't care :)
 * @property Carbon $createdDate
 * @property Carbon $lastModifiedDate
 *
 * @property Model|EntitySyncState $model
 */
abstract class BaseEntity implements \JsonSerializable, Jsonable
{
    use HasAttributes,
        HasTimestamps,
        HidesAttributes,
        GuardsAttributes;
    use EntityMetaTrait;

    /**
     * @var string ERP entity name
     */
    const ENTITY_NAME = 'UNKNOWN';

    const CREATED_AT = 'createdDate';
    const UPDATED_AT = 'lastModifiedDate';

    // TODO: understand how to mimic Laravel relations
    //protected $relations = [];

    protected $primaryKey = 'id';
    protected $_model;


    /**
     * {@inheritdoc}
     */
    public function usesTimestamps()
    {
        return false;
    }


    public function getModelAttribute()
    {
        return $this->_model;
    }


    public function setModelAttribute(?EntitySyncState $model)
    {
        $this->_model = $model;
    }


    /**
     * @param array $options RESERVED
     * @throws \Throwable
     */
    public function save(array $options = [])
    {
        if (!$this->model || !($this->model instanceof EntitySyncState)) {
            Log::channel('erp')->error("Tried to save entity without proper model", ['model' => $this->model]);
            throw new \InvalidArgumentException("Tried to save entity without proper model. See ERP error log for more details");
        }

        $this->withMetaDescription();

        DB::transaction(function () use (&$event) {
            $erpEntity = static::find($this->id);

            //TODO: support dirty smart update
            if ($erpEntity) {
                if ($erpEntity->version != $this->version) {
                    Log::channel('erp')->warning("Conflict for " . get_called_class() . " ({$this->id})", ['erpEntity' => $erpEntity, 'this' => $this]);
                    $this->fill($erpEntity->toArray());
                    $this->syncModel();
                } else {
                    // WORKAROUND: ERP wants all data for entity so we give it
                    $this->fill(array_merge($erpEntity->toArray(), $this->toArray()));
                }
                $data = $this->newQuery()->update();
                $this->fill($data);
                $event = 'updated';
            } else {
                $data = $this->newQuery()->create();
                $this->fill($data);
                $event = 'created';
            }

            $syncState = $this->model->syncState ?? new SyncState();
            $syncState->entity_id = $this->id;
            $syncState->entity_type_id = get_class($this);
            $syncState->version = $this->version;
            if (!$syncState->save() || !$syncState->target()->associate($this->model)->save()) {
                throw new \Exception("Failed to save syncState of model ({$this->model->id}) during entity save");
            }
        });

        $this->syncOriginal();

        Event::dispatch(EntityEvent::class, [$this, $event]);
    }


    /**
     * Get the format for stored dates.
     *
     * @return string
     */
    public function getDateFormat()
    {
        return 'Uv';
    }


    /**
     * Fill entity from its AGR model
     *
     * NOTE! Please extend this method for your use case!
     *
     * <code>
     * public function fillFromModel(EntitySyncState $model) {
     *   parent::fillFromModel($model);
     *   // YOUR CODE
     * }
     * </code>
     *
     * @param EntitySyncState $model
     * @return void
     */
    public function fillFromModel(EntitySyncState $model)
    {
        $this->model = $model;

        // EXTEND WITH YOUR OWN STUFF
    }


    /**
     * Creates new model from ERP entity
     *
     * @return Model|EntitySyncState
     */
    abstract public function createModel();

    /**
     * Fill AGR model from ERP entity and save it
     *
     * @return void
     */
    abstract public function syncModel();

    /**
     * @throws \Exception
     * @return void
     */
    public function delete()
    {
        $this->newQuery()->delete();

        if (!empty($this->model->syncState)) {
            $this->model->syncState->forceDelete();
        }

        Event::dispatch(EntityEvent::class, [$this, 'deleted']);
    }


    /**
     * @param int $id
     * @return BaseEntity|null
     */
    public static function find($id)
    {
        return static::query()->find($id);
    }


    /**
     * @return SimpleQuery
     */
    public function newQuery()
    {
        return new SimpleQuery($this);
    }


    /**
     * @return SimpleQuery
     */
    public static function query()
    {
        return (new static())->newQuery();
    }


    /**
     * @return BaseEntity[]|Collection|static[]
     */
    public static function all()
    {
        return static::query()->get();
    }


    /**
     * Create a new Eloquent model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        static::unguard();
        // WORKAROUND
        $this->fillable = array_merge($this->fillable, ['id', 'version', 'createdDate', 'lastModifiedDate']);

        //$this->bootIfNotBooted();
        //$this->initializeTraits();
        $this->syncOriginal();
        $this->fill($attributes);
    }


    /**
     * Get a relationship.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getRelationValue($key)
    {
        return null; // TODO: proper empty value handling, for now if not set, we won't send it to ERP
//        // If the key already exists in the relationships array, it just means the
//        // relationship has already been loaded, so we'll just return it out of
//        // here because there is no need to query within the relations twice.
//        if ($this->relationLoaded($key)) {
//            return $this->relations[$key];
//        }
//
//        // If the "attribute" exists as a method on the model, we will just assume
//        // it is a relationship and will load and return results from the query
//        // and hydrate the relationship's value on the "relationships" array.
//        if (method_exists($this, $key)) {
//            return $this->getRelationshipFromMethod($key);
//        }
    }


    /**
     * Dynamically retrieve attributes on the model.
     *
     * @param  string $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the model.
     *
     * @param  string $key
     * @param  mixed $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array $attributes
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     * @return $this
     *
     */
    public function fill(array $attributes)
    {
        $totallyGuarded = $this->totallyGuarded();
        foreach ($this->fillableFromArray($attributes) as $key => $value) {
            // The developers may choose to place some attributes in the "fillable" array
            // which means only those attributes may be set through mass assignment to
            // the model, and all others will just get ignored for security reasons.
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            } elseif ($totallyGuarded) {
                throw new MassAssignmentException(sprintf(
                    'Add [%s] to fillable property to allow mass assignment on [%s].',
                    $key,
                    get_class($this)
                ));
            }
        }

        return $this;
    }


    /**
     * Convert the model to its string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toJson(JSON_PRETTY_PRINT);
    }


    /**
     * Get the value of the model's primary key.
     *
     * @return mixed
     */
    public function getKey()
    {
        return $this->getAttribute($this->getKeyName());
    }


    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    public function getKeyName()
    {
        return $this->primaryKey;
    }


    /**
     * Convert the model instance to JSON.
     *
     * @param  int $options
     * @throws \Illuminate\Database\Eloquent\JsonEncodingException
     * @return string
     *
     */
    public function toJson($options = 0)
    {
        $json = json_encode($this->jsonSerialize(), $options);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw JsonEncodingException::forModel($this, json_last_error_msg());
        }

        return $json;
    }


    public function getIncrementing()
    {
        return false;
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->attributesToArray();
        //return array_merge($this->attributesToArray(), $this->relationsToArray());
    }
}
