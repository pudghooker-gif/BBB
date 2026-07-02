<?php

namespace VanguardLTE\Services\Auth\Api;

use Carbon\Carbon;
use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use VanguardLTE\User;

class JWTAuth
{
    private $request;
    private $config;
    private $token;
    private $payload;
    private $user;

    public function __construct(Request $request, ConfigContract $config)
    {
        $this->request = $request;
        $this->config = $config;
    }

    public function attempt(array $credentials)
    {
        $user = $this->userFromCredentials($credentials);

        if (!$user) {
            return false;
        }

        $token = $this->fromUser($user);
        Auth::guard('api')->setUser($user);

        return $token;
    }

    public function fromUser(User $user)
    {
        if (!Schema::hasTable('api_tokens')) {
            throw new JWTException('The api_tokens table is missing.');
        }

        $claims = $user->getJWTCustomClaims();
        $now = Carbon::now();
        $ttl = $this->config->get('jwt.ttl');
        $expiresAt = is_null($ttl) ? null : $now->copy()->addMinutes((int) $ttl);

        $payload = array_merge([
            'iss' => $this->config->get('app.url'),
            'iat' => $now->timestamp,
            'nbf' => $now->timestamp,
            'exp' => $expiresAt ? $expiresAt->timestamp : null,
            'sub' => $user->getJWTIdentifier(),
        ], $claims);

        $this->payload = $payload;
        $this->user = $user;

        return $this->token = $this->encode($payload);
    }

    public function setToken($token)
    {
        $this->token = $token;
        $this->payload = null;
        $this->user = null;

        return $this;
    }

    public function getToken()
    {
        return $this->token ?: $this->tokenFromRequest();
    }

    public function parseToken()
    {
        $token = $this->tokenFromRequest();

        if (!$token) {
            throw new TokenInvalidException('Token could not be parsed from the request.');
        }

        return $this->setToken($token);
    }

    public function authenticate()
    {
        return $this->user();
    }

    public function user()
    {
        if ($this->user) {
            return $this->user;
        }

        $payload = $this->getPayload()->all();
        $user = User::find($payload['sub']);

        if (!$user) {
            throw new TokenInvalidException('Token subject does not exist.');
        }

        return $this->user = $user;
    }

    public function getPayload()
    {
        if (!$this->payload) {
            $this->payload = $this->decode($this->requireToken());
        }

        return collect($this->payload);
    }

    public function getClaim($claim)
    {
        return $this->getPayload()->get($claim);
    }

    public function invalidate($forceForever = true)
    {
        $payload = $this->decode($this->requireToken());

        if (isset($payload['jti']) && Schema::hasTable('api_tokens')) {
            Token::where('id', $payload['jti'])->delete();
        }

        $this->token = null;
        $this->payload = null;
        $this->user = null;

        return $this;
    }

    public function setRequest(Request $request)
    {
        $this->request = $request;
        $this->token = null;
        $this->payload = null;
        $this->user = null;

        return $this;
    }

    public function setUser(Authenticatable $user)
    {
        $this->user = $user;

        return $this;
    }

    private function userFromCredentials(array $credentials)
    {
        $password = $credentials['password'] ?? null;
        unset($credentials['password']);

        if (!$password || count($credentials) === 0) {
            return null;
        }

        $query = User::query();
        foreach ($credentials as $field => $value) {
            $query->where($field, $value);
        }

        $user = $query->first();

        return $user && Hash::check($password, $user->password) ? $user : null;
    }

    private function encode(array $payload)
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $segments = [
            $this->base64UrlEncode(json_encode($header)),
            $this->base64UrlEncode(json_encode($payload)),
        ];

        $segments[] = $this->signature($segments[0] . '.' . $segments[1]);

        return implode('.', $segments);
    }

    private function decode($token)
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            throw new TokenInvalidException('Token structure is invalid.');
        }

        [$encodedHeader, $encodedPayload, $signature] = $segments;
        $expected = $this->signature($encodedHeader . '.' . $encodedPayload);

        if (!hash_equals($expected, $signature)) {
            throw new TokenInvalidException('Token signature is invalid.');
        }

        $header = json_decode($this->base64UrlDecode($encodedHeader), true);
        if (!is_array($header) || ($header['alg'] ?? null) !== 'HS256' || ($header['typ'] ?? null) !== 'JWT') {
            throw new TokenInvalidException('Token header is invalid.');
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);
        if (!is_array($payload)) {
            throw new TokenInvalidException('Token payload is invalid.');
        }

        $this->validatePayload($payload);

        return $payload;
    }

    private function validatePayload(array $payload)
    {
        foreach (['sub', 'jti', 'iat', 'nbf'] as $claim) {
            if (!array_key_exists($claim, $payload)) {
                throw new TokenInvalidException('Token is missing required claim: ' . $claim);
            }
        }

        $now = Carbon::now()->timestamp;

        if (isset($payload['nbf']) && (int) $payload['nbf'] > $now) {
            throw new TokenInvalidException('Token is not active yet.');
        }

        if (isset($payload['exp']) && $payload['exp'] !== null && (int) $payload['exp'] <= $now) {
            throw new TokenInvalidException('Token has expired.');
        }

        if (!Schema::hasTable('api_tokens')) {
            throw new TokenInvalidException('Token storage is unavailable.');
        }

        $exists = Token::where('id', $payload['jti'])
            ->where('user_id', $payload['sub'])
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now());
            })
            ->exists();

        if (!$exists) {
            throw new TokenInvalidException('Token has been revoked.');
        }
    }

    private function requireToken()
    {
        $token = $this->getToken();

        if (!$token) {
            throw new TokenInvalidException('Token is required.');
        }

        return $token;
    }

    private function tokenFromRequest()
    {
        $header = $this->request->headers->get('Authorization', '');

        if (stripos($header, 'Bearer ') === 0) {
            return trim(substr($header, 7));
        }

        return $this->request->bearerToken();
    }

    private function signature($value)
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $value, $this->secret(), true));
    }

    private function secret()
    {
        $secret = (string) ($this->config->get('jwt.secret') ?: $this->config->get('app.key'));

        if (strpos($secret, 'base64:') === 0) {
            $decoded = base64_decode(substr($secret, 7), true);
            $secret = $decoded === false ? $secret : $decoded;
        }

        if ($secret === '') {
            throw new JWTException('JWT secret is not configured.');
        }

        return $secret;
    }

    private function base64UrlEncode($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode($value)
    {
        $value = strtr($value, '-_', '+/');
        $padding = strlen($value) % 4;

        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            throw new TokenInvalidException('Token segment is invalid.');
        }

        return $decoded;
    }
}
