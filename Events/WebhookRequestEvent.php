<?php

namespace Tan\ERP\Events;

use Illuminate\Foundation\Events\Dispatchable;

class WebhookRequestEvent
{
    use Dispatchable;

    public $entityId;
    public $entityName;

    public function __construct($entityId, $entityName)
    {
        $this->entityId = $entityId;
        $this->entityName = $entityName;
    }
}
