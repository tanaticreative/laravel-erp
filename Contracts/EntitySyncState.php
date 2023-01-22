<?php


namespace Tan\ERP\Contracts;

use Tan\ERP\Models\SyncState;

/**
 * Interface EntitySyncState
 * @package Tan\ERP\Contracts
 *
 * @property SyncState $syncState
 */
interface EntitySyncState
{
    public function syncState();
}
