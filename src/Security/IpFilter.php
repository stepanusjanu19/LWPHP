<?php

namespace Kei\Lwphp\Security;

/**
 * IpFilter — IP whitelist + blocklist.
 *
 * Supports:
 * - Exact IP match (IPv4 and IPv6)
 * - CIDR range notation (e.g. 192.168.0.0/24, 10.0.0.0/8)
 *
 * Usage in SecurityMiddleware:
 *   if ($filter->isBlocked($ip))  → 403
 *   if ($filter->isDenied($ip))   → 403 (blocked OR not in allowlist)
 */
class IpFilter
{
    /** @var string[] */
    private array $blocklist;

    /** @var string[] Empty = open to all */
    private array $allowlist;

    /**
     * @param string[] $blocklist CIDR / exact IPs to always deny
     * @param string[] $allowlist CIDR / exact IPs that are allowed (empty = all)
     */
    public function __construct(array $blocklist = [], array $allowlist = [])
    {
        $this->blocklist = $blocklist;
        $this->allowlist = $allowlist;
    }

    /**
     * True if this request should be denied.
     * Denies if: IP is in blocklist OR (allowlist is set AND IP is not in it).
     */
    public function isDenied(string $ip): bool
    {
        if ($this->isBlocked($ip)) {
            return true;
        }

        // Allowlist: if configured, only the listed IPs pass through
        if (!empty($this->allowlist) && !$this->matches($ip, $this->allowlist)) {
            return true;
        }

        return false;
    }

    /** True if IP is explicitly blocked */
    public function isBlocked(string $ip): bool
    {
        return $this->matches($ip, $this->blocklist);
    }

    /** True if IP is in the allow-list (or allow-list is empty = open access) */
    public function isAllowed(string $ip): bool
    {
        return empty($this->allowlist) || $this->matches($ip, $this->allowlist);
    }

    /** Add an IP or CIDR to the block list at runtime */
    public function block(string $cidr): void
    {
        $this->blocklist[] = $cidr;
    }

    /** Add an IP or CIDR to the allow list at runtime */
    public function allow(string $cidr): void
    {
        $this->allowlist[] = $cidr;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function matches(string $ip, array $list): bool
    {
        foreach ($list as $entry) {
            if ($entry === '') {
                continue;
            }

            if (str_contains($entry, '/')) {
                if ($this->inCidr($ip, $entry)) {
                    return true;
                }
            } elseif ($ip === $entry) {
                return true;
            }
        }
        return false;
    }

    private function inCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;

        // IPv4
        if (
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        ) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $mask = ~((1 << (32 - $bits)) - 1);
            return ($ipLong & $mask) === ($subnetLong & $mask);
        }

        // IPv6
        if (
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
        ) {
            $ipBin = inet_pton($ip);
            $subnetBin = inet_pton($subnet);
            if ($ipBin === false || $subnetBin === false) {
                return false;
            }
            $fullBytes = (int) ($bits / 8);
            $remBits = $bits % 8;
            if (substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
                return false;
            }
            if ($remBits > 0 && $fullBytes < 16) {
                $mask = 0xFF & (0xFF << (8 - $remBits));
                return (ord($ipBin[$fullBytes]) & $mask) === (ord($subnetBin[$fullBytes]) & $mask);
            }
            return true;
        }

        return false;
    }
}
