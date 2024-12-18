<?php

namespace Hishabee\LaravelQueryCache;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Query\Builder;

class QueryCacheServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../config/query-cache.php' => config_path('query-cache.php'),
        ], 'query-cache-config');

        // Register cache macro
        Builder::macro('cache', function ($duration = null) {
            $this->shouldCache = true;
            $this->cacheDuration = $duration;
            return $this;
        });

        // Initialize caching service
        $this->app->singleton(QueryCacheService::class, function ($app) {
            return new QueryCacheService();
        });

        $this->app->make(QueryCacheService::class)->enableQueryCache();
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/query-cache.php', 'query-cache'
        );
    }
}