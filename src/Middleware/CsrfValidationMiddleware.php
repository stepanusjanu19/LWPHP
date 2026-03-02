<?php

namespace Kei\Lwphp\Middleware;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CsrfValidationMiddleware implements MiddlewareInterface
{
    private readonly Psr17Factory $factory;

    public function __construct()
    {
        $this->factory = new Psr17Factory();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $method = strtoupper($request->getMethod());

        // 1. Globally generate the CSRF token ONLY on safe methods (GET/HEAD/OPTIONS)
        //    so they are universally available to the Twig View engine prior to form submission.
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
        }

        // 2. Protect state-mutating requests
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {

            $validToken = $_SESSION['csrf_token'] ?? null;
            if (!$validToken) {
                // If the session has no token, reject immediately.
                return $this->secError($request, 403, 'CSRF verification failed: No active session token.');
            }

            // Check header first (common for AJAX/HTMX)
            $providedToken = $request->getHeaderLine('X-CSRF-Token');

            if ($providedToken === '') {
                // Check body for standard form submissions
                $body = $request->getParsedBody();
                if (is_array($body) && isset($body['csrf_token'])) {
                    $providedToken = $body['csrf_token'];
                }
            }

            if (!hash_equals($validToken, (string) $providedToken)) {
                return $this->secError($request, 403, 'CSRF verification failed: Token mismatch.');
            }
        }

        $response = $handler->handle($request);

        // 3. Auto-inject CSRF token into all outgoing HTML forms natively
        $contentType = $response->getHeaderLine('Content-Type');
        if (str_contains($contentType, 'text/html') || $contentType === '') {
            $html = (string) $response->getBody();
            if (stripos($html, '<form') !== false) {

                // If there's a global flash error pending, inject it physically into the form DOM!
                if (!empty($_SESSION['global_form_error'])) {
                    $errMsg = htmlspecialchars($_SESSION['global_form_error']);
                    $errorBlock = "<div class=\"error-msg global-error-msg\" style=\"color: #ef4444; background: rgba(239, 68, 68, 0.1); padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.85rem; border: 1px solid rgba(239, 68, 68, 0.3);\">{$errMsg}</div>";
                    $html = preg_replace('/(<form\b[^>]*>)/i', "$1\n    " . $errorBlock, $html);
                    unset($_SESSION['global_form_error']);
                }

                if (isset($_SESSION['csrf_token'])) {
                    $token = $_SESSION['csrf_token'];
                    $html = preg_replace('/(<form\b[^>]*>)/i', "$1\n    <input type=\"hidden\" name=\"csrf_token\" value=\"{$token}\">", $html);
                }

                $response = $response->withBody($this->factory->createStream($html));
            }
        }

        return $response;
    }

    private function secError(ServerRequestInterface $request, int $status, string $msg): ResponseInterface
    {
        $isHtmx = $request->getHeaderLine('HX-Request') === 'true';
        $accept = $request->getHeaderLine('Accept');

        if ($isHtmx || str_contains($accept, 'application/json')) {
            $body = $this->factory->createStream(json_encode(['error' => $msg, 'status' => $status]));
            return $this->factory->createResponse($status)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withBody($body);
        }

        // Standard browser form submission (e.g., /login or /register)
        $referer = $request->getHeaderLine('Referer');
        if ($referer) {
            $_SESSION['global_form_error'] = $msg;
            return $this->factory->createResponse(302)->withHeader('Location', $referer);
        }

        $body = $this->factory->createStream("<h2 style='color:#ef4444'>Security Error: {$status}</h2><p>{$msg}</p>");
        return $this->factory->createResponse($status)->withHeader('Content-Type', 'text/html')->withBody($body);
    }
}
