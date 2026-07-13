<?php

namespace VanguardLTE\B2B\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class B2BGameCatalogCache
{
    const VERSION_KEY = 'b2b:game_catalog:version';
    const DEFAULT_VERSION = '1';
    const DEFAULT_TTL_SECONDS = 300;

    public function rememberIndex($operator, array $filters, callable $resolver)
    {
        if (!$this->enabled()) {
            return $resolver();
        }

        try {
            return $this->store()->remember(
                $this->indexKey($operator, $filters),
                $this->ttlSeconds(),
                $resolver
            );
        } catch (Throwable $e) {
            Log::warning('b2b_game_catalog_cache_remember_failed', [
                'exception' => get_class($e),
            ]);

            return $resolver();
        }
    }

    public function invalidate()
    {
        if (!$this->enabled()) {
            return false;
        }

        try {
            $this->store()->forever(self::VERSION_KEY, $this->freshVersion());

            return true;
        } catch (Throwable $e) {
            Log::warning('b2b_game_catalog_cache_invalidate_failed', [
                'exception' => get_class($e),
            ]);

            return false;
        }
    }

    public function indexKey($operator, array $filters)
    {
        $operatorHash = sha1($this->operatorScope($operator));
        $filterHash = hash('sha256', json_encode($this->stableFilters($filters)));
        $version = sha1($this->currentVersion());

        return 'b2b:game_catalog:index:' . $version . ':' . $operatorHash . ':' . $filterHash;
    }

    public function storeName()
    {
        return config('b2b.game_catalog_cache_store') ?: config('cache.default');
    }

    public function ttlSeconds()
    {
        $ttl = (int) config('b2b.game_catalog_cache_ttl_seconds', self::DEFAULT_TTL_SECONDS);

        return $ttl > 0 ? $ttl : self::DEFAULT_TTL_SECONDS;
    }

    private function enabled()
    {
        return (bool) config('b2b.game_catalog_cache_enabled', true);
    }

    private function store()
    {
        return Cache::store($this->storeName());
    }

    private function currentVersion()
    {
        $version = $this->store()->get(self::VERSION_KEY);

        return $version ? (string) $version : self::DEFAULT_VERSION;
    }

    private function freshVersion()
    {
        return sprintf('%.6F:%s', microtime(true), Str::random(8));
    }

    private function operatorScope($operator)
    {
        if (is_object($operator)) {
            if (isset($operator->operator_uid) && $operator->operator_uid !== '') {
                return 'uid:' . (string) $operator->operator_uid;
            }

            if (isset($operator->id) && $operator->id !== '') {
                return 'id:' . (string) $operator->id;
            }
        }

        return 'anonymous';
    }

    private function stableFilters(array $filters)
    {
        ksort($filters);

        return $filters;
    }
}
