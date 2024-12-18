<?php

namespace Hishabee\LaravelQueryCache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Database\Events\QueryExecuted;
use PHPSQLParser\PHPSQLParser;
use InvalidArgumentException;

class QueryCacheService
{
    protected $parser;
    protected $config;

    public function __construct()
    {
        $this->parser = new PHPSQLParser();
        $this->config = config('query-cache');
    }

    public function enableQueryCache()
    {
        if (!$this->isEnabled()) {
            return;
        }

        // Use listen event for both caching and retrieving
        DB::listen(function($query) {
            if (!$this->shouldCacheQuery($query)) {
                return;
            }

            if ($this->shouldSkipCaching($query->sql)) {
                return;
            }

            $key = $this->generateCacheKey($query->sql, $query->bindings);

            if ($this->isSelectQuery($query->sql)) {
                // For SELECT queries, check cache and store if needed
                if (!Cache::tags($this->getCacheTags())->has($key)) {
                    $tags = $this->generateQueryTags($query->sql, $query->bindings);
                    Cache::tags($tags)->put(
                        $key,
                        $this->executeQuery($query->sql, $query->bindings),
                        $this->getCacheDuration($query)
                    );
                    Log::info('Query cached with tags: ' . implode(', ', $tags));
                }
            } else {
                // For non-SELECT queries, invalidate cache
                $this->invalidateRelatedCache($query->sql, $query->bindings);
            }
        });

        // Extend the Builder class to add the cache macro
        \Illuminate\Database\Query\Builder::macro('cache', function ($duration = null) {
            $this->shouldCache = true;
            $this->cacheDuration = $duration;
            return $this;
        });
    }

    protected function executeQuery($sql, $bindings)
    {
        return DB::select($sql, $bindings);
    }

    public function getCacheTags(): array
    {
        return ['query-cache'];
    }

    protected function shouldCacheQuery($query): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        // Get the query builder if available
        $builder = $query->connection->query();

        // If strategy is 'all', cache everything unless explicitly disabled
        if ($this->config['strategy'] === 'all') {
            return !isset($builder->shouldCache) || $builder->shouldCache !== false;
        }

        // If strategy is 'manual', only cache when explicitly enabled
        return isset($builder->shouldCache) && $builder->shouldCache === true;
    }

    protected function isEnabled(): bool
    {
        return $this->config['enabled'] ?? false;
    }

    protected function getCacheDuration($query): int
    {
        // Get the query builder if available
        $builder = $query->connection->query();

        if (isset($builder->cacheDuration)) {
            return $builder->cacheDuration;
        }

        return $this->config['duration'] ?? 3600;
    }


    protected function registerBeforeExecutingListener()
    {
        DB::beforeExecuting(function($query, $bindings, $connection) {
            if (!$this->shouldCacheQuery($connection->query())) {
                return;
            }

            if (!$this->isSelectQuery($query) || $this->shouldSkipCaching($query)) {
                return;
            }

            $key = $this->generateCacheKey($query, $bindings);

            if (Cache::tags($this->getCacheTags())->has($key)) {
                Log::info('Query served from cache: ' . $query);
                return Cache::tags($this->getCacheTags())->get($key);
            }
        });
    }

    protected function registerQueryListener()
    {
        DB::listen(function(QueryExecuted $query) {
            if (!$this->shouldCacheQuery($query)) {
                return;
            }

            if ($this->shouldSkipCaching($query->sql)) {
                return;
            }

            $key = $this->generateCacheKey($query->sql, $query->bindings);

            if ($this->isSelectQuery($query->sql)) {
                if (!Cache::tags($this->getCacheTags())->has($key)) {
                    $tags = $this->generateQueryTags($query->sql, $query->bindings);
                    $duration = $this->getCacheDuration($query);

                    Cache::tags($tags)->put($key, $query->sql, $duration);
                    Log::info('Query cached with tags: ' . implode(', ', $tags));
                }
            } else {
                $this->invalidateRelatedCache($query->sql, $query->bindings);
            }
        });
    }

    protected function generateCacheKey($query, $bindings): string
    {
        $fullQuery = vsprintf(str_replace('?', '%s', $query),
            array_map(function ($binding) {
                return is_numeric($binding) ? $binding : "'" . $binding . "'";
            }, $bindings)
        );

        return 'query_cache:' . md5($fullQuery);
    }

    protected function shouldSkipCaching(string $sql): bool
    {
        foreach ($this->config['excluded_tables'] ?? [] as $table) {
            if (stripos($sql, $table) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function isSelectQuery(string $sql): bool
    {
        return Str::startsWith(trim(strtolower($sql)), 'select');
    }

    protected function generateQueryTags($sql, $bindings): array
    {
        try {
            $parsed = $this->parser->parse($sql);
            $tags = ['query-cache'];

            // Add table tag
            if (isset($parsed['FROM'][0]['table'])) {
                $tableName = $parsed['FROM'][0]['table'];
                $tags[] = "table:$tableName";
            }

            // Add WHERE condition tags
            if (isset($parsed['WHERE'])) {
                $whereTags = $this->parseWhereConditions($parsed['WHERE'], $bindings);
                $tags = array_merge($tags, $whereTags);
            }

            return array_unique($tags);
        } catch (\Exception $e) {
            Log::error('Error parsing SQL for tags: ' . $e->getMessage());
            return ['query-cache'];
        }
    }

    protected function parseWhereConditions(array $where, array $bindings): array
    {
        $tags = [];
        $bindingIndex = 0;

        foreach ($where as $condition) {
            if (!isset($condition['expr_type'])) {
                continue;
            }

            if ($condition['expr_type'] === 'colref') {
                $column = $condition['base_expr'];
                $value = $bindings[$bindingIndex] ?? null;

                if ($value !== null) {
                    $tags[] = "where:$column:$value";
                    $bindingIndex++;
                }
            }
        }

        return $tags;
    }

    protected function invalidateRelatedCache(string $sql, array $bindings): void
    {
        try {
            $parsed = $this->parser->parse($sql);
            $tags = $this->getInvalidationTags($parsed, $bindings);

            foreach ($tags as $tag) {
                Cache::tags([$tag])->flush();
                Log::info("Cache invalidated for tag: $tag");
            }
        } catch (\Exception $e) {
            Log::error('Error invalidating cache: ' . $e->getMessage());
            Cache::tags(['query-cache'])->flush();
        }
    }

    protected function getInvalidationTags(array $parsed, array $bindings): array
    {
        $tags = [];

        // Get table name
        $tableName = $this->extractTableName($parsed);
        if ($tableName) {
            $tags[] = "table:$tableName";
        }

        // Get WHERE conditions for specific invalidation
        if (isset($parsed['WHERE'])) {
            $whereTags = $this->parseWhereConditions($parsed['WHERE'], $bindings);
            $tags = array_merge($tags, $whereTags);
        }

        return array_unique($tags);
    }

    protected function extractTableName(array $parsed): ?string
    {
        if (isset($parsed['UPDATE'])) {
            return $parsed['UPDATE'][0]['table'] ?? null;
        }
        if (isset($parsed['INSERT'])) {
            return $parsed['INSERT'][1]['table'] ?? null;
        }
        if (isset($parsed['DELETE'])) {
            return $parsed['DELETE'][0]['table'] ?? null;
        }
        return null;
    }
}