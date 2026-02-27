<?php

/**
 * Security configuration for LWPHP.
 *
 * – rate_limit : sliding-window throttle per client IP
 * – blocked_ips: deny-list (CIDR notation supported, e.g. "192.168.0.0/24")
 * – allowed_ips: if non-empty, ONLY these IPs may access the API
 * – cors_origins: allowed CORS origins ('*' = open)
 * – max_body_kb : maximum accepted request body in kilobytes
 */
return [

    /* Rate-limit: max requests per window_seconds for a single IP */
    'rate_limit' => [
        'window_seconds' => (int) env('RATE_LIMIT_WINDOW', 60),
        'max_requests' => (int) env('RATE_LIMIT_MAX', 180),
        'burst_bonus' => (int) env('RATE_LIMIT_BURST', 20), // burst allowance on top of max
    ],

    /* Request body size limit */
    'max_body_kb' => (int) env('MAX_BODY_KB', 1024), // 1 MB default

    /*
     | IP block-list — add CIDRs or exact IPs to deny.
     | Can also be set via LWPHP_BLOCKED_IPS="1.2.3.4,5.6.7.8" in .env
     */
    'blocked_ips' => array_filter(
        array_map('trim', explode(',', (string) env('LWPHP_BLOCKED_IPS', '')))
    ),

    /*
     | IP allow-list — if non-empty, ALL other IPs are blocked.
     | Empty array = open to everyone (default).
     | Can also be set via LWPHP_ALLOWED_IPS="127.0.0.1,10.0.0.0/8"
     */
    'allowed_ips' => array_filter(
        array_map('trim', explode(',', (string) env('LWPHP_ALLOWED_IPS', '')))
    ),

    /* CORS allowed origins */
    'cors_origins' => ['*'],

    /* Paths that bypass IP allow-list check (always public) */
    'public_paths' => ['/', '/benchmark/info'],

    /* Paths that bypass rate-limiting (whitelisted) */
    'ratelimit_exempt_paths' => [],
];
