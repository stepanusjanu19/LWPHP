<?php

namespace Kei\Lwphp\Security;

/**
 * SecurityException — thrown by security classes on detected attack.
 *
 * Carries an HTTP status code so the middleware can return the correct response.
 */
class SecurityException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = 400,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
