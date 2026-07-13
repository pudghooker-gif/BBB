<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use VanguardLTE\B2B\Services\B2BGameCatalogCache;

class B2BGameCatalogCacheTest extends TestCase
{
    public function testIndexPayloadsAreCachedUntilCatalogVersionIsInvalidated()
    {
        config([
            'b2b.game_catalog_cache_enabled' => true,
            'b2b.game_catalog_cache_store' => 'array',
            'b2b.game_catalog_cache_ttl_seconds' => 60,
        ]);
        Cache::store('array')->flush();

        $cache = app(B2BGameCatalogCache::class);
        $operator = (object) ['id' => 7, 'operator_uid' => 'op_cache'];
        $filters = ['limit' => 10, 'sort' => 'title', 'mode' => 'real'];
        $calls = 0;

        $first = $cache->rememberIndex($operator, $filters, function () use (&$calls) {
            $calls++;

            return ['data' => ['first']];
        });
        $second = $cache->rememberIndex($operator, $filters, function () use (&$calls) {
            $calls++;

            return ['data' => ['second']];
        });

        $this->assertSame(['data' => ['first']], $first);
        $this->assertSame(['data' => ['first']], $second);
        $this->assertSame(1, $calls);

        $keyBefore = $cache->indexKey($operator, $filters);
        $this->assertTrue($cache->invalidate());
        $keyAfter = $cache->indexKey($operator, $filters);
        $this->assertNotSame($keyBefore, $keyAfter);

        $third = $cache->rememberIndex($operator, $filters, function () use (&$calls) {
            $calls++;

            return ['data' => ['third']];
        });

        $this->assertSame(['data' => ['third']], $third);
        $this->assertSame(2, $calls);
    }

    public function testDisabledCacheAlwaysRunsResolver()
    {
        config([
            'b2b.game_catalog_cache_enabled' => false,
            'b2b.game_catalog_cache_store' => 'array',
        ]);
        Cache::store('array')->flush();

        $cache = app(B2BGameCatalogCache::class);
        $operator = (object) ['id' => 7, 'operator_uid' => 'op_cache'];
        $calls = 0;

        $cache->rememberIndex($operator, ['limit' => 10], function () use (&$calls) {
            $calls++;

            return ['data' => ['first']];
        });
        $second = $cache->rememberIndex($operator, ['limit' => 10], function () use (&$calls) {
            $calls++;

            return ['data' => ['second']];
        });

        $this->assertSame(['data' => ['second']], $second);
        $this->assertSame(2, $calls);
        $this->assertFalse($cache->invalidate());
    }
}
