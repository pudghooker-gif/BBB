<?php

namespace VanguardLTE\B2B\Services;

use Throwable;
use VanguardLTE\B2B\Contracts\GameProviderInterface;
use VanguardLTE\B2B\Providers\GoldsvetInternalProvider;

class B2BProviderHealthService
{
    public function summary()
    {
        $providers = [];
        $ok = true;
        $counts = [
            'ok' => 0,
            'degraded' => 0,
            'failed' => 0,
        ];

        foreach ($this->providers() as $provider) {
            $entry = $this->providerHealth($provider);
            $providers[] = $entry;

            if (!$entry['ok']) {
                $ok = false;
            }

            if (isset($counts[$entry['status']])) {
                $counts[$entry['status']]++;
            }
        }

        return [
            'ok' => $ok,
            'checked_at' => now()->toIso8601String(),
            'counts' => $counts,
            'providers' => $providers,
        ];
    }

    protected function providers()
    {
        return [
            app(GoldsvetInternalProvider::class),
        ];
    }

    private function providerHealth($provider)
    {
        $code = $provider instanceof GameProviderInterface
            ? $provider->providerCode()
            : (is_object($provider) ? get_class($provider) : 'unknown_provider');

        try {
            if (!$provider instanceof GameProviderInterface) {
                return $this->entry($code, false, 'failed', [], [], 'Provider does not implement the B2B provider contract.');
            }

            $health = $provider->health();
            $capabilities = $this->capabilitySummary($provider->capabilities());
            $ok = isset($health['ok']) ? (bool) $health['ok'] : false;
            $status = $ok ? 'ok' : 'failed';

            if ($ok && isset($health['status']) && $health['status'] === 'degraded') {
                $status = 'degraded';
            }

            return $this->entry($code, $ok, $status, $health, $capabilities, null);
        } catch (Throwable $e) {
            return $this->entry($code, false, 'failed', [], [], 'Provider health check failed.');
        }
    }

    private function entry($provider, $ok, $status, array $health, array $capabilities, $error)
    {
        return [
            'provider' => (string) $provider,
            'ok' => (bool) $ok,
            'status' => $status,
            'capabilities' => $capabilities,
            'health' => $this->safeHealth($health),
            'error' => $error,
        ];
    }

    private function capabilitySummary(array $capabilities)
    {
        $summary = [
            GameProviderInterface::CAPABILITY_SUPPORTED => 0,
            GameProviderInterface::CAPABILITY_UNSUPPORTED => 0,
            GameProviderInterface::CAPABILITY_NOT_APPLICABLE => 0,
            GameProviderInterface::CAPABILITY_DEGRADED => 0,
        ];

        foreach ($capabilities as $state) {
            if (isset($summary[$state])) {
                $summary[$state]++;
            }
        }

        return $summary;
    }

    private function safeHealth(array $health)
    {
        $safe = [];
        foreach (['ok', 'status', 'games_table_available', 'checked_at'] as $key) {
            if (array_key_exists($key, $health)) {
                $safe[$key] = $health[$key];
            }
        }

        return $safe;
    }
}
