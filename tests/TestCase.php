<?php

namespace Hishabee\LaravelQueryCache\Tests;

use Hishabee\LaravelQueryCache\QueryCacheServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            QueryCacheServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testing');
    }
}