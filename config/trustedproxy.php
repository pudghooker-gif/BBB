<?php

return [

    /*
     * Set trusted reverse proxy IP addresses for Laravel's TrustProxies middleware.
     *
     * IPv4, IPv6, and CIDR notation are supported. Use a comma-separated list for
     * static load balancers, or "*" only when the direct upstream proxy address
     * cannot be known ahead of time.
     */
    'proxies' => env('TRUSTED_PROXIES') ?: null,

    /*
     * Which headers to use to detect proxy related data (For, Host, Proto, Port)
     *
     * Options include:
     *
     * - Illuminate\Http\Request::HEADER_X_FORWARDED_ALL (use all x-forwarded-* headers to establish trust)
     * - Illuminate\Http\Request::HEADER_FORWARDED (use the FORWARDED header to establish trust)
     * - Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB (If you are using AWS Elastic Load Balancer)
     *
     * - 'HEADER_X_FORWARDED_ALL' (use all x-forwarded-* headers to establish trust)
     * - 'HEADER_FORWARDED' (use the FORWARDED header to establish trust)
     * - 'HEADER_X_FORWARDED_AWS_ELB' (If you are using AWS Elastic Load Balancer)
     *
     * @link https://symfony.com/doc/current/deployment/proxies.html
     */
    'headers' => Illuminate\Http\Request::HEADER_X_FORWARDED_ALL,

];
