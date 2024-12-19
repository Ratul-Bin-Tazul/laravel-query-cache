<?php

namespace Hishabee\LaravelQueryCache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Database\Events\QueryExecuted;

class QueryCacheService
{
    protected $config;

    public function __construct()
    {
        $this->config = config('query-cache');
    }

    public function enableQueryCache()
    {
        if (!$this->isEnabled()) {
            return;
        }

        DB::listen(function($query) {
            if (!$this->shouldCacheQuery($query)) {
                return;
            }

            if ($this->shouldSkipCaching($query->sql)) {
                return;
            }

            $key = $this->generateCacheKey($query->sql, $query->bindings);


            if ($this->isSelectQuery($query->sql)) {
                if (!Cache::tags($this->generateTags($query))->has($key)) {
                    $result = DB::select($query->sql, $query->bindings);
                    Cache::tags($this->generateTags($query))->put(
                        $key,
                        $result,
                        $this->getCacheDuration($query)
                    );
                }
                return Cache::tags($this->generateTags($query))->get($key);
            } else {
                // For UPDATE/DELETE queries, invalidate related caches
                $this->invalidateRelatedTags($query);
            }

        });
    }

    protected function generateTags($query): array
    {
        $tags = ['query-cache']; // Base tag

        // Extract table name from query
        preg_match('/from\s+([^\s,]+)/i', $query->sql, $matches);
        if (!empty($matches[1])) {
            $tags[] = 'table:' . $matches[1];
        }

        // Extract WHERE conditions and values
        if (!empty($query->bindings)) {
            preg_match_all('/where\s+([^\s]+)\s*=\s*\?/i', $query->sql, $matches);
            foreach ($matches[1] as $i => $column) {
                $value = $query->bindings[$i];
                $tags[] = "where:{$matches[1][$i]}:{$value}";
            }
        }

        return $tags;
    }

    protected function invalidateRelatedTags($query): void
    {
        // Extract table name
        preg_match('/from\s+([^\s,]+)/i', $query->sql, $matches);
        if (!empty($matches[1])) {
            $table = $matches[1];
            Cache::tags(["table:$table"])->flush();
        }
    }

    protected function shouldCacheQuery($query): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $builder = $query->connection->query();

        if ($this->config['strategy'] === 'all') {
            return !isset($builder->shouldCache) || $builder->shouldCache !== false;
        }

        return isset($builder->shouldCache) && $builder->shouldCache === true;
    }

    protected function isEnabled(): bool
    {
        return $this->config['enabled'] ?? false;
    }

    protected function getCacheDuration($query): int
    {
        $builder = $query->connection->query();
        return $builder->cacheDuration ?? $this->config['duration'] ?? 3600;
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
}