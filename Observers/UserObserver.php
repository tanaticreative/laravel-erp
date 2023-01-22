<?php


namespace Tan\ERP\Observers;

use Tan\ERP\Jobs\SyncUserToERP;
use App\Models\User;

class UserObserver
{
    public function created(User $user)
    {
        if ($this->isFullyVerified($user)) {
            SyncUserToERP::dispatch('created', $user->id);
        }
    }


    public function updated(User $user)
    {
        if ($this->isFullyVerified($user) || !empty($user->syncState->entity_id)) {
            SyncUserToERP::dispatch('updated', $user->id);
        }
    }


    public function deleted(User $user)
    {
        if ($user->syncState) {
            SyncUserToERP::dispatch('deleted', $user->id, $user->syncState->entity_id, $user->syncState->entity_type_id);
        }
    }


    protected function isFullyVerified(User $user): bool
    {
        return $user->isVerified && $user->company && $user->company->isVerified;
    }
}
