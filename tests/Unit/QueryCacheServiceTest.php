<?php

namespace Hishabee\LaravelQueryCache\Tests\Unit;

use Hishabee\LaravelQueryCache\Tests\TestCase;
use Hishabee\LaravelQueryCache\QueryCacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class QueryCacheServiceTest extends TestCase
{
    protected $queryCacheService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->queryCacheService = new QueryCacheService();
        
        // Set up test configuration
        Config::set('query-cache', [
            'enabled' => true,
            'strategy' => 'all',
            'duration' => 3600,
            'excluded_tables' => ['jobs', 'failed_jobs'],
        ]);

        // Create test table
        DB::statement('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY,
            name TEXT,
            email TEXT,
            active BOOLEAN
        )');
    }

    protected function tearDown(): void
    {
        DB::statement('DROP TABLE IF EXISTS users');
        Cache::flush();
        parent::tearDown();
    }

    public function test_caches_select_query()
    {
        // Execute query
        DB::table('users')
            ->where('active', true)
            ->cache()
            ->get();

        // Check if query was cached
        $key = $this->getCacheKey("SELECT * FROM users WHERE active = '1'");
        $this->assertTrue(Cache::tags(['query-cache'])->has($key));
    }

    public function test_serves_cached_query()
    {
        $queryCount = 0;
        DB::listen(function() use (&$queryCount) {
            $queryCount++;
        });

        // Execute query twice
        for ($i = 0; $i < 2; $i++) {
            DB::table('users')
                ->where('active', true)
                ->cache()
                ->get();
        }

        // Should only execute once, second time should be from cache
        $this->assertEquals(1, $queryCount);
    }

    public function test_respects_cache_duration()
    {
        DB::table('users')
            ->where('active', true)
            ->cache(60) // Cache for 1 minute
            ->get();

        $key = $this->getCacheKey("SELECT * FROM users WHERE active = '1'");
        
        // Check TTL is around 60 seconds
        $ttl = Cache::tags(['query-cache'])->getTimeToLive($key);
        $this->assertGreaterThan(55, $ttl);
        $this->assertLessThan(65, $ttl);
    }

    public function test_invalidates_cache_on_update()
    {
        // Cache a select query
        DB::table('users')
            ->where('active', true)
            ->cache()
            ->get();

        $key = $this->getCacheKey("SELECT * FROM users WHERE active = '1'");

        // Update the table
        DB::table('users')
            ->where('active', true)
            ->update(['name' => 'John']);

        // Cache should be invalidated
        $this->assertFalse(Cache::tags(['query-cache'])->has($key));
    }

    public function test_respects_excluded_tables()
    {
        // Query excluded table
        DB::table('jobs')
            ->cache()
            ->get();

        $key = $this->getCacheKey("SELECT * FROM jobs");
        $this->assertFalse(Cache::tags(['query-cache'])->has($key));
    }

    public function test_respects_cache_strategy()
    {
        // Change strategy to manual
        Config::set('query-cache.strategy', 'manual');

        // Query without explicit cache()
        DB::table('users')
            ->where('active', true)
            ->get();

        $key = $this->getCacheKey("SELECT * FROM users WHERE active = '1'");
        $this->assertFalse(Cache::tags(['query-cache'])->has($key));

        // Query with explicit cache()
        DB::table('users')
            ->where('active', true)
            ->cache()
            ->get();

        $this->assertTrue(Cache::tags(['query-cache'])->has($key));
    }

    public function test_handles_multiple_where_conditions()
    {
        DB::table('users')
            ->where('active', true)
            ->where('email', 'test@example.com')
            ->cache()
            ->get();

        $key = $this->getCacheKey("SELECT * FROM users WHERE active = '1' AND email = 'test@example.com'");
        $this->assertTrue(Cache::tags(['query-cache'])->has($key));
    }

    public function test_invalidates_specific_where_conditions()
    {
        // Cache two different queries
        DB::table('users')
            ->where('active', true)
            ->cache()
            ->get();

        DB::table('users')
            ->where('active', false)
            ->cache()
            ->get();

        $key1 = $this->getCacheKey("SELECT * FROM users WHERE active = '1'");
        $key2 = $this->getCacheKey("SELECT * FROM users WHERE active = '0'");

        // Update only active=true records
        DB::table('users')
            ->where('active', true)
            ->update(['name' => 'John']);

        // Only the first query should be invalidated
        $this->assertFalse(Cache::tags(['query-cache'])->has($key1));
        $this->assertTrue(Cache::tags(['query-cache'])->has($key2));
    }

    protected function getCacheKey(string $sql): string
    {
        return 'query_cache:' . md5($sql);
    }
}