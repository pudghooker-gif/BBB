<?php

namespace VanguardLTE\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use VanguardLTE\B2B\Models\B2BOperator;
use VanguardLTE\B2B\Models\B2BOperatorApiKey;
use VanguardLTE\B2B\Services\B2BOperatorAuditLogger;
use VanguardLTE\B2B\Services\B2BSignature;
use VanguardLTE\B2B\Services\B2BStructuredEventLogger;
use VanguardLTE\B2B\Support\B2BApiResponse;

class VerifyB2BSignature
{
    /**
     * Headers expected from every B2B operator request:
     * X-Operator-Id, X-Api-Key, X-Timestamp, X-Nonce,
     * X-Body-Hash, X-Signature.
     *
     * Signed canonical request:
     * METHOD + "\n" + PATH + "\n" + canonical query + "\n" +
     * SHA-256(raw body) + "\n" + timestamp + "\n" + nonce
     */
    public function handle($request, Closure $next)
    {
        $requestId = $request->header('X-Request-Id') ?: (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);

        $operatorId = $request->header('X-Operator-Id');
        $apiKey = $request->header('X-Api-Key');
        $timestamp = $request->header('X-Timestamp');
        $nonce = $request->header('X-Nonce');
        $bodyHash = $request->header('X-Body-Hash');
        $signature = $request->header('X-Signature');

        if (!$operatorId || !$apiKey || !$timestamp || !$nonce || !$bodyHash || !$signature) {
            return $this->deny($request, $requestId, 'B2B_AUTH_FAILED', 'Missing B2B authentication headers', 401);
        }

        if (!ctype_digit((string) $timestamp) || abs(time() - (int) $timestamp) > $this->replayWindowSeconds()) {
            return $this->deny($request, $requestId, 'B2B_AUTH_FAILED', 'Invalid or expired timestamp', 401);
        }

        if (!hash_equals(B2BSignature::bodyHash($request->getContent()), strtolower($bodyHash))) {
            return $this->deny($request, $requestId, 'B2B_BODY_HASH_MISMATCH', 'Invalid request body hash', 401);
        }

        $operator = B2BOperator::where('operator_uid', $operatorId)
            ->whereIn('status', [B2BOperator::STATUS_ACTIVE, B2BOperator::STATUS_DEGRADED])
            ->first();

        if (!$operator) {
            return $this->deny($request, $requestId, 'B2B_AUTH_FAILED', 'Unknown or inactive operator', 401);
        }

        $request->attributes->set('b2b_operator', $operator);

        if (!$this->isIpAllowed($operator->ip_whitelist, $request->ip())) {
            return $this->deny($request, $requestId, 'B2B_AUTH_FAILED', 'IP is not allowed for this operator', 403);
        }

        $apiCredential = B2BOperatorApiKey::where('operator_id', $operator->id)
            ->where('key_id', $apiKey)
            ->where('status', B2BOperatorApiKey::STATUS_ACTIVE)
            ->first();

        if (!$apiCredential) {
            return $this->deny($request, $requestId, 'B2B_AUTH_FAILED', 'Invalid API key', 401);
        }

        $request->attributes->set('b2b_api_key', $apiCredential);

        if ($apiCredential->expires_at && $apiCredential->expires_at->isPast()) {
            return $this->deny($request, $requestId, 'B2B_AUTH_FAILED', 'API key expired', 401);
        }

        try {
            $secret = Crypt::decryptString($apiCredential->secret_encrypted);
        } catch (\Exception $e) {
            return $this->deny($request, $requestId, 'B2B_AUTH_FAILED', 'API secret cannot be decrypted', 500);
        }

        $canonical = B2BSignature::canonicalRequest($request, $bodyHash, $timestamp, $nonce);
        $expected = hash_hmac('sha256', $canonical, $secret);

        if (!hash_equals($expected, $signature)) {
            return $this->deny($request, $requestId, 'B2B_AUTH_FAILED', 'Invalid signature', 401);
        }

        $replayKey = 'b2b:nonce:' . $operator->id . ':' . sha1($nonce);
        if (!$this->cache()->add($replayKey, 1, now()->addSeconds($this->replayWindowSeconds()))) {
            return $this->deny($request, $requestId, 'B2B_REPLAY_DETECTED', 'Replay detected', 409);
        }

        $apiCredential->forceFill(['last_used_at' => now()])->save();
        try {
            app(B2BOperatorAuditLogger::class)->recordApiKeyUsed($operator, $apiCredential, $request);
        } catch (\Exception $e) {
            // API-key usage audit must never break authenticated traffic.
        }

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        $this->structuredLogger()->request('api.request', $request, [
            'status_code' => $response->getStatusCode(),
        ]);

        return $response;
    }

    private function deny($request, $requestId, $code, $message, $status)
    {
        $this->structuredLogger()->request('api.auth_failed', $request, [
            'status_code' => $status,
            'error_code' => $code,
            'failure_reason' => $message,
        ], 'warning');

        return B2BApiResponse::error($requestId, $code, $message, $status);
    }

    private function structuredLogger()
    {
        return app(B2BStructuredEventLogger::class);
    }

    private function isIpAllowed($ipWhitelist, $requestIp)
    {
        if (!$ipWhitelist) {
            return true;
        }

        if (is_string($ipWhitelist)) {
            $decoded = json_decode($ipWhitelist, true);
            $ipWhitelist = is_array($decoded) ? $decoded : [$ipWhitelist];
        }

        if (!is_array($ipWhitelist) || count($ipWhitelist) === 0) {
            return true;
        }

        foreach ($ipWhitelist as $allowedIp) {
            $allowedIp = trim((string) $allowedIp);
            if ($allowedIp === '' || $allowedIp === '*') {
                return true;
            }

            if ($allowedIp === $requestIp || $this->ipMatchesCidr($requestIp, $allowedIp)) {
                return true;
            }
        }

        return false;
    }

    private function ipMatchesCidr($ip, $cidr)
    {
        if (strpos($cidr, '/') === false) {
            return false;
        }

        list($subnet, $bits) = explode('/', $cidr, 2);
        if (!ctype_digit((string) $bits)) {
            return false;
        }

        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $bits = (int) $bits;
        $maxBits = strlen($ipBin) * 8;
        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;

        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($ipBin[$fullBytes]) & $mask) === (ord($subnetBin[$fullBytes]) & $mask);
    }

    private function replayWindowSeconds()
    {
        return (int) config('b2b.hmac_replay_window_seconds', 300);
    }

    private function cache()
    {
        $store = config('b2b.nonce_cache_store');

        return $store ? Cache::store($store) : Cache::store();
    }
}
