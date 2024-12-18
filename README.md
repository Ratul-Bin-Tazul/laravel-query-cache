# Laravel Query Cache

Smart SQL query caching for Laravel with tag-based invalidation and precise cache clearing.

## Features

- Automatic query caching with intelligent tag-based invalidation
- Support for explicit query-level cache control
- Smart cache invalidation based on WHERE clauses
- Configurable caching strategy (all queries or manual)
- Works with Laravel 7.0 and above
- Compatible with Redis and Memcached
- Cache stampede prevention

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
QUERY_CACHE_STORE=redis
```

## Basic Usage

```php
// Cache a specific query for 1 hour
User::where('active', true)
    ->cache(3600)
    ->get();

// Cache with default duration from config
Post::latest()
    ->cache()
    ->paginate();

// Disable cache for a specific query (when strategy is 'all')
Order::where('status', 'pending')
    ->cache(false)
    ->get();
```

## How It Works

### Tag-Based Caching

The package uses a sophisticated tagging system to enable precise cache invalidation:

1. **Table-Level Tags**
```php
// Query:
User::where('active', true)->cache()->get();

// Generated Tags:
[
    'table:users',  // Base table tag
    'where:users:active:1'  // Condition tag
]
```

2. **Multiple Conditions**
```php
// Query:
User::where('active', true)
    ->where('role', 'admin')
    ->cache()
    ->get();

// Generated Tags:
[
    'table:users',
    'where:users:active:1',
    'where:users:role:admin'
]
```

### Cache Invalidation

When a table is updated, the package intelligently invalidates only relevant cached queries:

```php
// Original cached query
User::where('role', 'admin')->cache()->get();

// When this update happens:
User::where('role', 'user')->update(['active' => false]);

// Only cache entries with these tags are invalidated:
[
    'table:users',
    'where:users:role:user'
]

// The cached 'admin' query remains valid!
```

### Cache Keys

Cache keys are generated based on the full query including bindings:

```php
$key = 'query_cache:' . md5($fullQuery);

// Example:
// SELECT * FROM users WHERE active = '1'
// Becomes: query_cache:a1b2c3d4e5f6...
```

### Smart Cache Duration

You can set cache duration at multiple levels:

1. **Global Default** (in config)
```php
'duration' => env('QUERY_CACHE_DURATION', 3600),
```

2. **Per Query**
```php
User::where('active', true)
    ->cache(7200)  // Cache for 2 hours
    ->get();
```

## Advanced Usage

### Working with Complex Queries

```php
// Multiple joins
User::join('orders', 'users.id', '=', 'orders.user_id')
    ->join('products', 'orders.product_id', '=', 'products.id')
    ->where('orders.status', 'completed')
    ->cache()
    ->get();

// Nested conditions
User::where(function($query) {
    $query->where('role', 'admin')
          ->orWhere('role', 'manager');
})
->cache()
->get();
```

### Manual Cache Control

```php
// Clear cache for specific table
Cache::tags(['table:users'])->flush();

// Clear cache for specific condition
Cache::tags(['where:users:role:admin'])->flush();
```

### Debugging Cache Behavior

Enable debug mode in your .env:
```env
QUERY_CACHE_DEBUG=true
```

This will log:
- Cache hits/misses
- Generated tags
- Cache invalidations

Example log output:
```
[Query Cache] Hit: SELECT * FROM users WHERE active = '1'
[Query Cache] Tags: table:users, where:users:active:1
[Query Cache] Invalidated tags: table:users, where:users:role:admin
```

## Performance Considerations

1. **Cache Store**
   - Redis (recommended) or Memcached required for tag support
   - File cache driver doesn't support tags

2. **Cache Duration**
   - Shorter durations for frequently updated tables
   - Longer durations for static/reference data

3. **Excluded Tables**
   - Add frequently updated tables to `excluded_tables` in config
   - Consider excluding tables with sensitive data

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

MIT License. See LICENSE.md for details.