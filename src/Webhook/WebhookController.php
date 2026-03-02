<?php

namespace Kei\Lwphp\Webhook;

use Kei\Lwphp\Base\Controller;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class WebhookController
 *
 * Base controller for securely receiving and verifying external Webhooks.
 */
abstract class WebhookController extends Controller
{
    /**
     * @var string The HTTP header containing the signature for validation
     */
    protected string $signatureHeader = 'X-Hub-Signature-256';

    /**
     * @var string The hashing algorithm expected (e.g., sha256)
     */
    protected string $signatureAlgo = 'sha256';

    /**
     * Handle incoming webhook POST payloads.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (strtoupper($request->getMethod()) !== 'POST') {
            return $this->json(['error' => 'Method Not Allowed'], 405);
        }

        $payload = (string) $request->getBody();
        $signature = $request->getHeaderLine($this->signatureHeader);

        if (!$this->verifySignature($payload, $signature)) {
            return $this->json(['error' => 'Invalid webhook signature.'], 403);
        }

        $data = json_decode($payload, true) ?? [];

        return $this->processEvent($data, $request);
    }

    /**
     * Cryptographically validates the incoming request payload.
     */
    protected function verifySignature(string $payload, string $signature): bool
    {
        $secret = $this->getSecret();
        if (empty($secret)) {
            return false; // Fail securely if no secret configured
        }

        $expectedSignature = hash_hmac($this->signatureAlgo, $payload, $secret);

        // Handle common prefix like 'sha256=' from GitHub/Stripe
        if (str_contains($signature, '=')) {
            $parts = explode('=', $signature, 2);
            $signature = $parts[1] ?? '';
        }

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * The environment secret key used by the third party to sign payloads.
     */
    abstract protected function getSecret(): string;

    /**
     * Implement this method to handle the actual verified event data.
     */
    abstract protected function processEvent(array $payload, ServerRequestInterface $request): ResponseInterface;
}
