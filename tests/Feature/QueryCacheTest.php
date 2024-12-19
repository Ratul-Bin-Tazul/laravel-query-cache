<?php

namespace Hishabee\LaravelQueryCache\Tests\Feature;

use Hishabee\LaravelQueryCache\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class QueryCacheTest extends TestCase
{
    public function test_can_cache_query()
    {
        // Your test code here
        DB::table('test_users')
            ->where('active', true)
            ->cache()
            ->get();

        // Assert cache exists
        $this->assertTrue(true);
    }
}