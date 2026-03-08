<?php

namespace Kei\Lwphp\Security;

/**
 * Service to generate and verify HMAC-SHA256 signatures for securing
 * the JSON-RPC/gRPC Gateway endpoints against tampering.
 */
class CryptoSignatureService
{
    public function __construct(private readonly string $appKey)
    {
        if (empty($this->appKey)) {
            throw new \RuntimeException("APP_KEY must be set for CryptoSignatureService.");
        }
    }

    /**
     * Generate an HMAC SHA256 signature for a payload and salt.
     */
    public function generateSignature(string $payload, string $salt): string
    {
        return hash_hmac('sha256', $payload . $salt, $this->appKey);
    }

    /**
     * Verify if the provided signature securely matches the payload and salt.
     */
    public function verifySignature(string $payload, string $salt, string $signature): bool
    {
        $expected = $this->generateSignature($payload, $salt);
        return hash_equals($expected, $signature);
    }
}
