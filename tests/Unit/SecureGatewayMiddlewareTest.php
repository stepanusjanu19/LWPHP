<?php

use Kei\Lwphp\Middleware\SecureGatewayMiddleware;
use Kei\Lwphp\Security\CryptoSignatureService;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Server\RequestHandlerInterface;

test('SecureGatewayMiddleware rejects request without X-Payload-Signature', function () {
    $crypto = new CryptoSignatureService('test_key');
    $middleware = new SecureGatewayMiddleware($crypto);

    $factory = new Psr17Factory();
    $request = $factory->createServerRequest('POST', '/rpc');
    $request->getBody()->write('{"jsonrpc": "2.0"}');

    $handlerCalled = new class {
        public bool $value = false;
    };
    $handler = new class ($handlerCalled) implements RequestHandlerInterface {
        public function __construct(private $handlerCalled)
        {}
        public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
        {
            $this->handlerCalled->value = true;
            return (new Nyholm\Psr7\Factory\Psr17Factory())->createResponse(200);
        }
    };

    $response = $middleware->process($request, $handler);
    expect($response->getStatusCode())->toBe(401);
    expect($handlerCalled->value)->toBeFalse();
});

test('SecureGatewayMiddleware allows valid signed payload and signs the response', function () {
    $crypto = new CryptoSignatureService('test_key');
    $middleware = new SecureGatewayMiddleware($crypto);

    $factory = new Psr17Factory();
    $payload = '{"jsonrpc": "2.0", "method": "test"}';
    $salt = bin2hex(random_bytes(16));
    $signature = $crypto->generateSignature($payload, $salt);

    $request = $factory->createServerRequest('POST', '/rpc')
        ->withHeader('X-Payload-Signature', $signature)
        ->withHeader('X-Salt', $salt);
    $request->getBody()->write($payload);

    // Mock the inner app handler returning success
    $handler = clone $factory;
    $responseInner = $factory->createResponse(200);
    $responseInner->getBody()->write('{"jsonrpc": "2.0", "result": "ok"}');

    $mockHandler = new class ($responseInner) implements RequestHandlerInterface {
        public function __construct(private \Psr\Http\Message\ResponseInterface $response)
        {}
        public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
        {
            return $this->response;
        }
    };

    $response = $middleware->process($request, $mockHandler);

    expect($response->getStatusCode())->toBe(200);
    expect($response->hasHeader('X-Response-Signature'))->toBeTrue();
    expect($response->hasHeader('X-Response-Salt'))->toBeTrue();
});

test('SecureGatewayMiddleware bypasses non-gateway routes', function () {
    $crypto = new CryptoSignatureService('test_key');
    $middleware = new SecureGatewayMiddleware($crypto);

    $factory = new Psr17Factory();
    $request = $factory->createServerRequest('GET', '/api/users');

    $responseInner = $factory->createResponse(200);

    $handler = new class ($responseInner) implements RequestHandlerInterface {
        public function __construct(private \Psr\Http\Message\ResponseInterface $response)
        {}
        public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
        {
            return $this->response;
        }
    };

    $response = $middleware->process($request, $handler);
    expect($response->getStatusCode())->toBe(200);
});
