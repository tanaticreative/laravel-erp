<?php


namespace Tan\ERP\Support;

use Illuminate\Support\Facades\Facade as SupportFacade;

class Facade extends SupportFacade
{
    /**
     * {@inheritdoc}
     */
    public static function getFacadeAccessor()
    {
        return 'ERPManager';
    }
}
