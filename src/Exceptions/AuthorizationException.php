<?php

namespace Rodrigolopespt\SibsMbwayAP\Exceptions;

/**
 * Exception thrown when authorization operations fail
 */
class AuthorizationException extends SibsException
{
    public function __construct(string $message = 'Authorization operation failed', int $code = 400, ?\Exception $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous, $context);
    }

    /**
     * Create exception for inactive authorization
     */
    public static function inactive(string $authorizationId): self
    {
        return new self(
            "Authorization {$authorizationId} is not active",
            400,
            null,
            ['authorization_id' => $authorizationId, 'reason' => 'inactive']
        );
    }

    /**
     * Create exception for expired authorization
     */
    public static function expired(string $authorizationId): self
    {
        return new self(
            "Authorization {$authorizationId} has expired",
            400,
            null,
            ['authorization_id' => $authorizationId, 'reason' => 'expired']
        );
    }

    /**
     * Create exception for not found authorization
     */
    public static function notFound(string $authorizationId): self
    {
        return new self(
            "Authorization {$authorizationId} not found",
            404,
            null,
            ['authorization_id' => $authorizationId, 'reason' => 'not_found']
        );
    }
}
