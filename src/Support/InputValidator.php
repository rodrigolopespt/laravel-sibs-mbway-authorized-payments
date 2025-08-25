<?php

namespace Rodrigolopespt\SibsMbwayAP\Support;

use Rodrigolopespt\SibsMbwayAP\Exceptions\SibsException;

/**
 * Input validation helper for SIBS MBWay operations
 */
class InputValidator
{
    /**
     * Validate Portuguese phone number
     *
     * Format: 351XXXXXXXXX (13 digits total)
     * Examples: 351919999999, 351966999999
     *
     * Accepts various input formats:
     * - "351919999999" (clean format)
     * - "+351919999999" (international format)
     * - "351 919 999 999" (spaced format)
     * - "+351 919 999 999" (international spaced)
     *
     * All non-numeric characters (+ - spaces) are automatically removed.
     */
    public static function validatePortuguesePhone(string $phone): bool
    {
        // Remove any non-numeric characters
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

        // Must start with 351 and have 13 digits total
        return preg_match('/^351[0-9]{9}$/', $cleanPhone) === 1;
    }

    /**
     * Validate and format Portuguese phone number
     *
     * @throws SibsException
     */
    public static function formatPortuguesePhone(string $phone): string
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

        if (! self::validatePortuguesePhone($cleanPhone)) {
            throw new SibsException("Invalid Portuguese phone number format. Expected: 351XXXXXXXXX, got: {$phone}");
        }

        return $cleanPhone;
    }

    /**
     * Validate monetary amount
     *
     * @param  float  $minAmount  Minimum allowed amount (default: 0.01 EUR)
     * @param  float  $maxAmount  Maximum allowed amount (default: 10000 EUR)
     *
     * @throws SibsException
     */
    public static function validateAmount(float $amount, float $minAmount = 0.01, float $maxAmount = 10000.00): void
    {
        if ($amount < $minAmount) {
            throw new SibsException("Amount too low. Minimum: €{$minAmount}, provided: €{$amount}");
        }

        if ($amount > $maxAmount) {
            throw new SibsException("Amount too high. Maximum: €{$maxAmount}, provided: €{$amount}");
        }

        // Check for reasonable decimal precision (max 2 decimal places)
        if (round($amount, 2) !== $amount) {
            throw new SibsException("Amount must have maximum 2 decimal places. Provided: {$amount}");
        }
    }

    /**
     * Validate email address
     *
     * @throws SibsException
     */
    public static function validateEmail(string $email): void
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new SibsException("Invalid email format: {$email}");
        }

        if (strlen($email) > 320) { // RFC 5321 limit
            throw new SibsException('Email too long. Maximum 320 characters allowed.');
        }
    }

    /**
     * Validate merchant reference
     *
     * @throws SibsException
     */
    public static function validateMerchantReference(?string $reference): void
    {
        if ($reference === null) {
            return;
        }

        if (strlen($reference) > 50) {
            throw new SibsException('Merchant reference too long. Maximum 50 characters allowed.');
        }

        // Allow alphanumeric, hyphens, underscores
        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $reference)) {
            throw new SibsException('Merchant reference contains invalid characters. Only alphanumeric, hyphens and underscores allowed.');
        }
    }

    /**
     * Validate description
     *
     * @throws SibsException
     */
    public static function validateDescription(string $description): void
    {
        if (empty(trim($description))) {
            throw new SibsException('Description cannot be empty.');
        }

        if (strlen($description) > 200) {
            throw new SibsException('Description too long. Maximum 200 characters allowed.');
        }
    }

    /**
     * Validate currency code
     *
     * @throws SibsException
     */
    public static function validateCurrency(string $currency): void
    {
        $allowedCurrencies = ['EUR']; // SIBS typically only supports EUR

        if (! in_array(strtoupper($currency), $allowedCurrencies)) {
            throw new SibsException("Unsupported currency: {$currency}. Allowed: ".implode(', ', $allowedCurrencies));
        }
    }

    /**
     * Validate authorization data array
     *
     * @throws SibsException
     */
    public static function validateAuthorizationData(array $data): array
    {
        $required = ['customerPhone', 'customerEmail', 'maxAmount', 'description'];

        foreach ($required as $field) {
            if (! isset($data[$field]) || empty($data[$field])) {
                throw new SibsException("Required field missing: {$field}");
            }
        }

        // Validate and format each field
        $validated = [];

        $validated['customerPhone'] = self::formatPortuguesePhone($data['customerPhone']);

        self::validateEmail($data['customerEmail']);
        $validated['customerEmail'] = trim($data['customerEmail']);

        self::validateAmount($data['maxAmount']);
        $validated['maxAmount'] = round($data['maxAmount'], 2);

        self::validateDescription($data['description']);
        $validated['description'] = trim($data['description']);

        // Optional fields
        if (isset($data['merchantReference'])) {
            self::validateMerchantReference($data['merchantReference']);
            $validated['merchantReference'] = trim($data['merchantReference']);
        }

        if (isset($data['currency'])) {
            self::validateCurrency($data['currency']);
            $validated['currency'] = strtoupper($data['currency']);
        } else {
            $validated['currency'] = 'EUR';
        }

        if (isset($data['validityDate'])) {
            $validated['validityDate'] = $data['validityDate'];
        }

        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $validated['metadata'] = $data['metadata'];
        }

        return $validated;
    }

    /**
     * Validate charge data
     *
     * @throws SibsException
     */
    public static function validateChargeData(float $amount, ?string $description = null): array
    {
        self::validateAmount($amount);

        $validated = [
            'amount' => round($amount, 2),
        ];

        if ($description !== null) {
            self::validateDescription($description);
            $validated['description'] = trim($description);
        }

        return $validated;
    }
}
