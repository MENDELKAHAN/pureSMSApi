<?php
namespace Puresms\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

class PureSms extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'puresms';
    }
}
