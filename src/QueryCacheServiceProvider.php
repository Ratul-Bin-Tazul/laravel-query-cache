<?php

namespace Hishabee\LaravelQueryCache;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;

class QueryCacheServiceProvider extends ServiceProvider
{
    public function register()
    {
        Builder::macro('cache', function ($duration = null) {
            $this->shouldCache = true;
            $this->cacheDuration = $duration;
            return $this;
        });
    }
}