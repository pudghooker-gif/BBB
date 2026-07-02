<?php

namespace VanguardLTE\Services\Auth\Api;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;

class JwtGuard implements Guard
{
    private $jwt;
    private $provider;
    private $request;
    private $user;
    private $loggedOut = false;

    public function __construct(JWTAuth $jwt, UserProvider $provider, Request $request)
    {
        $this->jwt = $jwt;
        $this->provider = $provider;
        $this->request = $request;
    }

    public function check()
    {
        return !is_null($this->user());
    }

    public function guest()
    {
        return !$this->check();
    }

    public function user()
    {
        if ($this->loggedOut) {
            return null;
        }

        if ($this->user) {
            return $this->user;
        }

        try {
            return $this->user = $this->jwt->setRequest($this->request)->user();
        } catch (JWTException $e) {
            return null;
        }
    }

    public function id()
    {
        return $this->user() ? $this->user()->getAuthIdentifier() : null;
    }

    public function validate(array $credentials = [])
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        return $user && $this->provider->validateCredentials($user, $credentials);
    }

    public function setUser(Authenticatable $user)
    {
        $this->user = $user;
        $this->loggedOut = false;
        $this->jwt->setUser($user);

        return $this;
    }

    public function logout()
    {
        try {
            $this->jwt->setRequest($this->request)->invalidate();
        } catch (JWTException $e) {
        }

        $this->user = null;
        $this->loggedOut = true;
    }

    public function setRequest(Request $request)
    {
        $this->request = $request;
        $this->user = null;
        $this->loggedOut = false;
        $this->jwt->setRequest($request);

        return $this;
    }
}
