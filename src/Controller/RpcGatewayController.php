<?php

namespace Kei\Lwphp\Controller;

use DI\Container;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class RpcGatewayController
{
    public function __construct(private Container $container)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $payload = $request->getParsedBody();
        if (!$payload && $request->getHeaderLine('Content-Type') === 'application/json') {
            $payload = json_decode((string) $request->getBody(), true);
        }

        if (empty($payload['service']) || empty($payload['method'])) {
            return $this->errorResponse('Invalid RPC Payload: "service" and "method" are required.', 400);
        }

        $serviceName = $payload['service'];
        $method = $payload['method'];
        $params = $payload['params'] ?? [];

        // Safety generic namespace prefix logic. Only resolve from allowed namespaces like Service or Domain
        $allowedNamespaces = ['\\Kei\\Lwphp\\Service\\', '\\Kei\\Lwphp\\Domain\\'];
        $isAllowed = false;

        $targetClass = null;
        foreach ($allowedNamespaces as $ns) {
            $classCand = $ns . $serviceName;
            if (class_exists($classCand)) {
                $isAllowed = true;
                $targetClass = $classCand;
                break;
            }
        }

        if (!$isAllowed) {
            return $this->errorResponse("Service '{$serviceName}' not found or not allowed for RPC.", 403);
        }

        try {
            $service = $this->container->get($targetClass);

            if (!method_exists($service, $method)) {
                return $this->errorResponse("Method '{$method}' not found on service '{$serviceName}'.", 404);
            }

            // Execute service method
            $result = call_user_func_array([$service, $method], is_array($params) ? $params : [$params]);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $payload['id'] ?? null,
            ]);

        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    private function errorResponse(string $message, int $status): ResponseInterface
    {
        return $this->jsonResponse(['error' => ['code' => $status, 'message' => $message]], $status);
    }

    private function jsonResponse(array $data, int $status = 200): ResponseInterface
    {
        $factory = new Psr17Factory();
        $response = $factory->createResponse($status);
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
