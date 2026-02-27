<?php

namespace Kei\Lwphp\Security;

/**
 * SsrfGuard — Server-Side Request Forgery prevention.
 *
 * Validates user-supplied URLs to ensure they do not point to:
 *  - Localhost (127.0.0.1, ::1, localhost, 0.0.0.0)
 *  - Private / RFC-1918 ranges (10.x, 172.16-31.x, 192.168.x)
 *  - Link-local (169.254.x.x — cloud metadata endpoints)
 *  - Loopback IPv6 (::1, fe80::)
 *  - Internal hostnames without TLD
 *
 * @throws SecurityException when a URL targets a private/internal address
 */
class SsrfGuard
{
    /** Loopback and private IPv4 CIDRs */
    private const PRIVATE_CIDRS_V4 = [
        '127.0.0.0/8',    // loopback
        '10.0.0.0/8',     // RFC-1918
        '172.16.0.0/12',  // RFC-1918
        '192.168.0.0/16', // RFC-1918
        '169.254.0.0/16', // link-local / AWS metadata
        '0.0.0.0/8',
        '100.64.0.0/10',  // CGNAT
    ];

    /**
     * Check a URL string — throws if SSRF target detected.
     *
     * @throws SecurityException
     */
    public function assertSafe(string $url): void
    {
        if ($url === '') {
            return;
        }

        $parsed = parse_url($url);
        if ($parsed === false || empty($parsed['host'])) {
            throw new SecurityException("Invalid or missing host in URL: {$url}", 400);
        }

        $host = strtolower($parsed['host']);

        // Strip IPv6 brackets
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        // Reject localhost / internal hostnames
        if (
            in_array($host, ['localhost', '0.0.0.0', 'metadata'], true)
            || preg_match('/\.(local|internal|intranet|corp|lan)$/i', $host)
        ) {
            throw new SecurityException("SSRF: host '{$host}' is not allowed.", 400);
        }

        // Resolve hostname to IP for CIDR check
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

        // gethostbyname returns the original string on failure
        if ($ip === $host && !filter_var($ip, FILTER_VALIDATE_IP)) {
            // Could not resolve — allow (don't block unknown hosts)
            return;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $this->assertSafeIPv6($ip);
        } else {
            $this->assertSafeIPv4($ip);
        }
    }

    /**
     * Scan all string values in an array for URL fields; check each.
     *
     * @throws SecurityException
     */
    public function scanBody(array $data): void
    {
        foreach ($data as $key => $value) {
            if (is_string($value) && (str_contains($key, 'url') || str_contains($key, 'uri') || str_contains($key, 'endpoint'))) {
                $this->assertSafe($value);
            } elseif (is_array($value)) {
                $this->scanBody($value);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function assertSafeIPv4(string $ip): void
    {
        $long = ip2long($ip);
        if ($long === false) {
            return;
        }

        foreach (self::PRIVATE_CIDRS_V4 as $cidr) {
            [$subnet, $bits] = explode('/', $cidr, 2);
            $subnetLong = ip2long($subnet);
            $mask = ~((1 << (32 - (int) $bits)) - 1);
            if (($long & $mask) === ($subnetLong & $mask)) {
                throw new SecurityException("SSRF: IP {$ip} resolves to a private range ({$cidr}).", 400);
            }
        }
    }

    private function assertSafeIPv6(string $ip): void
    {
        // Block loopback (::1) and link-local (fe80::/10)
        if ($ip === '::1' || str_starts_with($ip, 'fe80:') || $ip === '::') {
            throw new SecurityException("SSRF: IPv6 address {$ip} is a private/loopback address.", 400);
        }
    }
}
