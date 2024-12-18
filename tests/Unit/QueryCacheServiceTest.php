<?php

namespace Hishabee\LaravelQueryCache\Tests\Unit;

use Hishabee\LaravelQueryCache\Tests\TestCase;
use Hishabee\LaravelQueryCache\QueryCacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QueryCacheServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create test table
        Schema::create('test_users', function ($table) {
            $table->id();
            $table->string('name');
            $table->boolean('active')->default(true);
        });

        // Insert test data
        DB::table('test_users')->insert([
            ['name' => 'Test User', 'active' => true]
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_users');
        Cache::flush();
        parent::tearDown();
    }

    public function test_caches_select_query()
    {
        // First query - should be cached
        $result1 = DB::table('test_users')
            ->where('active', true)
            ->cache()
            ->get();

        // Get cache key
        $key = 'query_cache:' . md5("select * from test_users where active = '1'");

        // Verify query was cached
        $this->assertTrue(Cache::tags(['query-cache'])->has($key));

        // Verify cached result matches
        $cachedResult = Cache::tags(['query-cache'])->get($key);
        $this->assertEquals($result1->toArray(), $cachedResult);
    }

    public function test_serves_cached_query()
    {
        $queryCount = 0;
        DB::listen(function() use (&$queryCount) {
            $queryCount++;
        });

        // Run same query twice
        for ($i = 0; $i < 2; $i++) {
            DB::table('test_users')
                ->where('active', true)
                ->cache()
                ->get();
        }

        // Should only have executed one actual query
        $this->assertEquals(1, $queryCount);
    }

    public function test_invalidates_cache_on_update()
    {
        // Cache initial query
        DB::table('test_users')
            ->where('active', true)
            ->cache()
            ->get();

        $key = 'query_cache:' . md5("select * from test_users where active = '1'");

        // Verify it was cached
        $this->assertTrue(Cache::tags(['query-cache'])->has($key));

        // Update records
        DB::table('test_users')
            ->where('active', true)
            ->update(['name' => 'Updated Name']);

        // Cache should be invalidated
        $this->assertFalse(Cache::tags(['query-cache'])->has($key));
    }
}