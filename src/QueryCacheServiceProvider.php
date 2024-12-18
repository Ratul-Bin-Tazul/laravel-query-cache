<?php

namespace Hishabee\LaravelQueryCache;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Query\Builder;

class QueryCacheServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/query-cache.php', 'query-cache'
        );

        $this->app->singleton(QueryCacheService::class, function ($app) {
            return new QueryCacheService();
        });
    }

    public function boot()
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../config/query-cache.php' => config_path('query-cache.php'),
        ], 'query-cache-config');

        // Initialize caching service
        $this->app->make(QueryCacheService::class)->enableQueryCache();
    }
}