# Laravel Query Cache

Smart SQL query caching for Laravel with tag-based invalidation.

## Features

- Automatic query caching with tag-based invalidation
- Support for explicit query-level cache control
- Smart cache invalidation based on WHERE clauses
- Configurable caching strategy (all queries or manual)
- Easy integration with existing Laravel applications

## Installation

```bash
composer require hishabee/laravel-query-cache
```

The package will automatically register its service provider.

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=query-cache-config
```

Add these variables to your `.env` file:

```env
QUERY_CACHE_ENABLED=true
QUERY_CACHE_STRATEGY=manual
QUERY_CACHE_DURATION=3600
```

## Usage

### Basic Usage

```php
// Cache a specific query
User::where('active', true)
    ->cache(3600)  // Cache for 1 hour
    ->get();

// Cache with default duration
Post::latest()
    ->cache()
    ->paginate();

// Disable cache for a specific query (when strategy is 'all')
Order::where('status', 'pending')
    ->cache(false)
    ->get();
```

### Configuration Options

In `config/query-cache.php`:

```php
return [
    'enabled' => env('QUERY_CACHE_ENABLED', true),
    'strategy' => env('QUERY_CACHE_STRATEGY', 'manual'),
    'duration' => env('QUERY_CACHE_DURATION', 3600),
    'excluded_tables' => [
        'jobs',
        'failed_jobs',
        'cache',
        'sessions',
    ],
];
```

## License

MIT License. See LICENSE.md for details.