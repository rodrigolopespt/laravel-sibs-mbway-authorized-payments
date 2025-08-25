<?php

namespace Rodrigolopespt\SibsMbwayAP\Exceptions;

use Exception;

/**
 * Base exception for all SIBS MBWay Authorized Payments operations
 */
class SibsException extends Exception
{
    protected array $context = [];

    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): self
    {
        $this->context = $context;

        return $this;
    }
}
