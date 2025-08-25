<?php

namespace Rodrigolopespt\SibsMbwayAP\Exceptions;

/**
 * Exception thrown when charge operations fail
 */
class ChargeException extends SibsException
{
    public function __construct(string $message = 'Charge operation failed', int $code = 400, ?\Exception $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous, $context);
    }

    /**
     * Create exception for amount limit exceeded
     */
    public static function amountExceeded(float $amount, float $maxAmount): self
    {
        return new self(
            "Charge amount {$amount} exceeds authorized limit {$maxAmount}",
            400,
            null,
            ['amount' => $amount, 'max_amount' => $maxAmount, 'reason' => 'amount_exceeded']
        );
    }

    /**
     * Create exception for insufficient balance
     */
    public static function insufficientBalance(float $amount): self
    {
        return new self(
            "Insufficient balance for charge amount {$amount}",
            400,
            null,
            ['amount' => $amount, 'reason' => 'insufficient_balance']
        );
    }

    /**
     * Create exception for failed charge
     */
    public static function failed(string $transactionId, string $reason = ''): self
    {
        return new self(
            "Charge {$transactionId} failed".($reason ? ": {$reason}" : ''),
            400,
            null,
            ['transaction_id' => $transactionId, 'reason' => $reason ?: 'failed']
        );
    }

    /**
     * Create exception for invalid refund
     */
    public static function invalidRefund(string $transactionId, string $reason = ''): self
    {
        return new self(
            "Cannot refund charge {$transactionId}".($reason ? ": {$reason}" : ''),
            400,
            null,
            ['transaction_id' => $transactionId, 'reason' => $reason ?: 'invalid_refund']
        );
    }
}
