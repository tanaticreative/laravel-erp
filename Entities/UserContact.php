<?php


namespace Tan\ERP\Entities;

use Tan\ERP\Contracts\EntitySyncState;
use Tan\ERP\Models\SyncState;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * {@inheritdoc}
 *
 * @property User $model
 */
class UserContact extends Contact
{
    const PARTY_TYPE = 'PERSON';


    /**
     * {@inheritdoc}
     */
    public function syncModel()
    {
        DB::transaction(function () {
            $this->model->name = $this->firstName;
            $this->model->surname = $this->lastName;
            $this->model->phone = $this->phone;
            $this->model->email = $this->email;
            $this->model->save();

            if ($this->model->syncState) {
                $syncState = $this->model->syncState;
            } else {
                $syncState = new SyncState();
            }

            $syncState->entity_id = $this->id;
            $syncState->version = $this->version;
            $syncState->entity_type_id = get_called_class();
            //$syncState->save();
            $syncState->target()->associate($this->model);
            $syncState->save();
        });
    }


    /**
     * {@inheritdoc}
     */
    public function createModel()
    {
        $model = new User();

        DB::transaction(function () use ($model) {
            $model->name = $this->firstName;
            $model->surname = $this->lastName;
            $model->phone = $this->phone;
            $model->email = $this->email;
            $model->save();

            $syncState = new SyncState();
            $syncState->entity_id = $this->id;
            $syncState->version = $this->version;
            $syncState->entity_type_id = get_called_class();
            $syncState->target()->associate($model);
            $syncState->save();
        });

        $this->model = $model;
    }


    /**
     * {@inheritdoc}
     */
    public function fillFromModel(EntitySyncState $model)
    {
        if (!($model instanceof User)) {
            throw new \InvalidArgumentException("Only instance of " . User::class . ' is supported');
        }

        parent::fillFromModel($model);

        if ($model->syncState && $model->syncState->entity_id) {
            $this->id = $model->syncState->entity_id;
        }

        $this->firstName = $model->name;
        $this->lastName = $model->surname;
        $this->phone = $model->phone;
        $this->email = $model->email;
        $this->company = $model->company->name;
    }
}
