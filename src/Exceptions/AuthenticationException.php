<?php

namespace Rodrigolopespt\SibsMbwayAP\Exceptions;

/**
 * Exception thrown when SIBS API authentication fails
 */
class AuthenticationException extends SibsException
{
    public function __construct(string $message = 'Authentication failed', int $code = 401, ?\Exception $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous, $context);
    }
}
