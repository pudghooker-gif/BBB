<?php

namespace VanguardLTE\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use VanguardLTE\B2B\Models\B2BOperator;
use VanguardLTE\B2B\Models\B2BOperatorApiKey;

class VerifyB2BSignature
{
    /**
     * Expected signature payload:
     *   X-Timestamp + "." + X-Nonce + "." + raw_request_body
     * Signature:
     *   hash_hmac('sha256', payload, operator_secret)
     */
    public function handle($request, Closure $next)
    {
        $operatorId = $request->header('X-Operator-Id');
        $apiKey = $request->header('X-Api-Key');
        $timestamp = $request->header('X-Timestamp');
        $nonce = $request->header('X-Nonce');
        $signature = $request->header('X-Signature');

        if (!$operatorId || !$apiKey || !$timestamp || !$nonce || !$signature) {
            return $this->deny('Missing B2B authentication headers', 401);
        }

        if (!ctype_digit((string) $timestamp) || abs(time() - (int) $timestamp) > 300) {
            return $this->deny('Invalid or expired timestamp', 401);
        }

        $operator = B2BOperator::where('operator_uid', $operatorId)
            ->where('status', B2BOperator::STATUS_ACTIVE)
            ->first();

        if (!$operator) {
            return $this->deny('Unknown or inactive operator', 401);
        }

        if (!$this->isIpAllowed($operator->ip_whitelist, $request->ip())) {
            return $this->deny('IP is not allowed for this operator', 403);
        }

        $apiCredential = B2BOperatorApiKey::where('operator_id', $operator->id)
            ->where('key_id', $apiKey)
            ->where('status', B2BOperatorApiKey::STATUS_ACTIVE)
            ->first();

        if (!$apiCredential) {
            return $this->deny('Invalid API key', 401);
        }

        $replayKey = 'b2b:nonce:' . $operator->id . ':' . sha1($nonce);
        if (!Cache::add($replayKey, 1, now()->addMinutes(5))) {
            return $this->deny('Replay detected', 409);
        }

        try {
            $secret = Crypt::decryptString($apiCredential->secret_encrypted);
        } catch (\Exception $e) {
            return $this->deny('API secret cannot be decrypted', 500);
        }

        $payload = $timestamp . '.' . $nonce . '.' . $request->getContent();
        $expected = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($expected, $signature)) {
            return $this->deny('Invalid signature', 401);
        }

        $request->attributes->set('b2b_operator', $operator);
        $request->attributes->set('b2b_api_key', $apiCredential);

        return $next($request);
    }

    private function deny($message, $status)
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'B2B_AUTH_FAILED',
                'message' => $message,
            ],
        ], $status);
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

        return in_array($requestIp, $ipWhitelist, true);
    }
}
