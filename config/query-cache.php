<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Query Cache Enable
    |--------------------------------------------------------------------------
    |
    | Enable or disable query caching globally
    |
    */
    'enabled' => env('QUERY_CACHE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Cache Strategy
    |--------------------------------------------------------------------------
    |
    | 'all': Cache all SELECT queries
    | 'manual': Only cache queries that explicitly use the cache() method
    |
    */
    'strategy' => env('QUERY_CACHE_STRATEGY', 'manual'),

    /*
    |--------------------------------------------------------------------------
    | Default Cache Duration
    |--------------------------------------------------------------------------
    |
    | Default time in seconds that a query should be cached
    |
    */
    'duration' => env('QUERY_CACHE_DURATION', 3600),

    /*
    |--------------------------------------------------------------------------
    | Excluded Tables
    |--------------------------------------------------------------------------
    |
    | Tables that should never be cached
    |
    */
    'excluded_tables' => [
        'jobs',
        'failed_jobs',
        'cache',
        'sessions',
        'personal_access_tokens',
    ],
];