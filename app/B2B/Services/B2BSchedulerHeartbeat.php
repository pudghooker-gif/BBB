<?php

namespace VanguardLTE\B2B\Services;

use Illuminate\Support\Facades\Cache;

class B2BSchedulerHeartbeat
{
    const DEFAULT_MAX_AGE_SECONDS = 180;

    public function record($source = 'scheduler')
    {
        $now = now();
        $payload = [
            'recorded_at' => $now->toIso8601String(),
            'timestamp' => $now->timestamp,
            'source' => $this->sourceLabel($source),
        ];

        $this->store()->forever($this->cacheKey(), $payload);

        return $payload;
    }

    public function status($now = null)
    {
        $payload = $this->store()->get($this->cacheKey());
        $maxAge = $this->maxAgeSeconds();

        if (!is_array($payload) || empty($payload['timestamp'])) {
            return [
                'present' => false,
                'fresh' => false,
                'age_seconds' => null,
                'max_age_seconds' => $maxAge,
                'recorded_at' => null,
                'source' => null,
                'cache_store' => $this->cacheStoreName(),
            ];
        }

        $timestamp = (int) $payload['timestamp'];
        $current = $now === null ? time() : (int) $now;
        $age = max(0, $current - $timestamp);

        return [
            'present' => true,
            'fresh' => $age <= $maxAge,
            'age_seconds' => $age,
            'max_age_seconds' => $maxAge,
            'recorded_at' => isset($payload['recorded_at']) ? (string) $payload['recorded_at'] : null,
            'source' => isset($payload['source']) ? (string) $payload['source'] : null,
            'cache_store' => $this->cacheStoreName(),
        ];
    }

    public function cacheKey()
    {
        return config('b2b.scheduler_heartbeat_key') ?: 'b2b:scheduler:heartbeat';
    }

    public function cacheStoreName()
    {
        return config('b2b.scheduler_heartbeat_cache_store') ?: config('cache.default');
    }

    private function store()
    {
        return Cache::store($this->cacheStoreName());
    }

    private function maxAgeSeconds()
    {
        $configured = (int) config('b2b.scheduler_heartbeat_max_age_seconds', self::DEFAULT_MAX_AGE_SECONDS);

        return $configured > 0 ? $configured : self::DEFAULT_MAX_AGE_SECONDS;
    }

    private function sourceLabel($source)
    {
        $source = trim((string) $source);

        return $source !== '' ? substr($source, 0, 64) : 'scheduler';
    }
}
