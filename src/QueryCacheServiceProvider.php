<?php

namespace Hishabee\LaravelQueryCache;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;

class QueryCacheServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/query-cache.php', 'query-cache');

        // Single service registration
        $this->app->singleton(QueryCacheService::class);

        // Simplified cache macro
        Builder::macro('cache', fn($duration = null) => tap($this, function() use ($duration) {
            $this->shouldCache = true;
            $this->cacheDuration = $duration;
        }));
    }

    public function boot()
    {
        if (config('query-cache.enabled')) {
            $this->app->make(QueryCacheService::class)->enableQueryCache();
        }

        $this->publishes([
            __DIR__.'/../config/query-cache.php' => config_path('query-cache.php'),
        ], 'query-cache-config');
    }
}