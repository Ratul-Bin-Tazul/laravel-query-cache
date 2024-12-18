<?php

namespace Hishabee\LaravelQueryCache\Facades;

use Illuminate\Support\Facades\Facade;

class QueryCache extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'query-cache';
    }
}