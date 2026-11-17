<?php

namespace Modules\Events\Tests\Feature;

use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Opt-in integration test for the shared database cache used by Events rate limits.
 *
 * Run against a local MySQL test database with EVENTS_SHARED_CACHE_TEST=1 and
 * CACHE_STORE=database. The two limiters use independent database connections,
 * representing separate application workers/nodes sharing the same cache tables.
 */
class EventSharedCacheIntegrationTest extends TestCase
{
    public function test_rate_limit_counters_are_shared_between_independent_database_connections(): void
    {
        if (env('EVENTS_SHARED_CACHE_TEST') !== '1') {
            $this->markTestSkipped('Set EVENTS_SHARED_CACHE_TEST=1 to run the MySQL shared-cache integration test.');
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('This integration test must run against MySQL.');
        }

        if (config('cache.default') !== 'database') {
            $this->markTestSkipped('CACHE_STORE must be database for this integration test.');
        }

        $cacheTable = config('cache.stores.database.table', 'cache');

        $cacheConnectionName = config('cache.stores.database.connection') ?: config('database.default');

        if (! Schema::connection($cacheConnectionName)->hasTable($cacheTable)) {
            $this->markTestSkipped("The database cache table [{$cacheTable}] does not exist.");
        }
        $peerConnectionName = 'events_shared_cache_peer';

        config([
            "database.connections.{$peerConnectionName}" => config("database.connections.{$cacheConnectionName}"),
        ]);

        DB::purge($peerConnectionName);
        $peerConnection = DB::connection($peerConnectionName);

        $peerStore = new DatabaseStore(
            $peerConnection,
            $cacheTable,
            (string) config('cache.prefix', ''),
        );

        $firstLimiter = new RateLimiter(Cache::store('database'));
        $secondLimiter = new RateLimiter(new Repository($peerStore));
        $key = 'events-shared-cache-integration:'.Str::uuid();

        try {
            $firstLimiter->hit($key, 60);

            $this->assertSame(
                1,
                $secondLimiter->attempts($key),
                'A second independent database connection must observe the first limiter increment.'
            );

            $secondLimiter->hit($key, 60);

            $this->assertSame(
                2,
                $firstLimiter->attempts($key),
                'The first limiter must observe increments written through the second connection.'
            );
        } finally {
            $firstLimiter->clear($key);
            DB::purge($peerConnectionName);
        }
    }
}
