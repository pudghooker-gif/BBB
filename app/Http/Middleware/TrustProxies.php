<?php

namespace VanguardLTE\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    protected $proxies = null;
    protected $headers = Request::HEADER_X_FORWARDED_ALL;
}
