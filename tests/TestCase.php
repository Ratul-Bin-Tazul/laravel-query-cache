<?php

namespace Hishabee\LaravelQueryCache\Tests;

use Hishabee\LaravelQueryCache\QueryCacheServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure the macro is registered
        $this->app->register(QueryCacheServiceProvider::class);
    }

    protected function getPackageProviders($app)
    {
        return [
            QueryCacheServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('cache.default', 'redis');
        $app['config']->set('query-cache.enabled', true);
        $app['config']->set('query-cache.strategy', 'all');

        // Ensure Redis cache is properly configured
        $app['config']->set('cache.stores.redis', [
            'driver' => 'redis',
            'connection' => 'cache',
        ]);

        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        // Ensure cache is configured for testing
        $app['config']->set('cache.default', 'redis');
        $app['config']->set('cache.stores.redis', [
            'driver' => 'redis',
            'connection' => 'cache',
        ]);
    }
}