<?php

namespace Kei\Lwphp\Middleware;

use Kei\Lwphp\Security\CryptoSignatureService;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Secures designated gateway routes (e.g. /rpc, /gateway) by requiring and
 * verifying a cryptographic HMAC-SHA256 signature on the raw payload.
 * It also signs the outgoing JSON response.
 */
class SecureGatewayMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly CryptoSignatureService $crypto
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        // Only protect gateway/rpc endpoints
        if (!in_array($path, ['/rpc', '/gateway'], true)) {
            return $handler->handle($request);
        }

        $signature = $request->getHeaderLine('X-Payload-Signature');
        $salt = $request->getHeaderLine('X-Salt');

        // We use the raw body exactly as received for verification
        // Ensure body stream is at the beginning
        $request->getBody()->rewind();
        $rawBody = $request->getBody()->getContents();
        $request->getBody()->rewind();

        if (empty($signature) || empty($salt)) {
            return $this->unauthorized('Missing X-Payload-Signature or X-Salt headers.');
        }

        if (!$this->crypto->verifySignature($rawBody, $salt, $signature)) {
            return $this->unauthorized('Invalid payload cryptographic signature.');
        }

        // Signature is valid. Proceed directly to the gateway controller.
        $response = $handler->handle($request);

        // Sign the response payload for the client
        $response->getBody()->rewind();
        $responseBody = $response->getBody()->getContents();
        $response->getBody()->rewind();

        $responseSalt = bin2hex(random_bytes(16));
        $responseSignature = $this->crypto->generateSignature($responseBody, $responseSalt);

        return $response
            ->withHeader('X-Response-Signature', $responseSignature)
            ->withHeader('X-Response-Salt', $responseSalt);
    }

    private function unauthorized(string $message): ResponseInterface
    {
        $factory = new Psr17Factory();
        $body = $factory->createStream(json_encode([
            'jsonrpc' => '2.0',
            'error' => ['code' => 401, 'message' => $message],
            'id' => null
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $factory->createResponse(401)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }
}
