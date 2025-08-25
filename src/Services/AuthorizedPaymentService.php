<?php

namespace Rodrigolopespt\SibsMbwayAP\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Rodrigolopespt\SibsMbwayAP\Api\Client;
use Rodrigolopespt\SibsMbwayAP\Api\Endpoints\AuthorizedPaymentEndpoint;
use Rodrigolopespt\SibsMbwayAP\Events\AuthorizationCreated;
use Rodrigolopespt\SibsMbwayAP\Events\AuthorizationExpired;
use Rodrigolopespt\SibsMbwayAP\Exceptions\AuthorizationException;
use Rodrigolopespt\SibsMbwayAP\Exceptions\SibsException;
use Rodrigolopespt\SibsMbwayAP\Models\AuthorizedPayment;
use Rodrigolopespt\SibsMbwayAP\Models\Transaction;
use Rodrigolopespt\SibsMbwayAP\Support\InputValidator;

/**
 * Service for managing MBWay Authorized Payments
 */
class AuthorizedPaymentService
{
    private Client $client;

    private AuthorizedPaymentEndpoint $endpoint;

    public function __construct(Client $client)
    {
        $this->client = $client; // Used by endpoint internally
        $this->endpoint = new AuthorizedPaymentEndpoint($client);
    }

    /**
     * Create a new authorization request
     */
    public function createAuthorization(array $data): AuthorizedPayment
    {
        $data = InputValidator::validateAuthorizationData($data);

        Log::info('Creating authorization request', [
            'customer_phone' => $data['customerPhone'],
            'max_amount' => $data['maxAmount'],
            'merchant_reference' => $data['merchantReference'] ?? null,
        ]);

        try {
            // Create local authorization record first
            $authorization = AuthorizedPayment::create([
                'customer_phone' => $data['customerPhone'],
                'customer_email' => $data['customerEmail'],
                'max_amount' => $data['maxAmount'],
                'currency' => $data['currency'] ?? 'EUR',
                'validity_date' => $this->parseValidityDate($data['validityDate'] ?? null),
                'description' => $data['description'],
                'merchant_reference' => $data['merchantReference'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            // Create transaction record
            $transaction = Transaction::createForModel(
                $authorization,
                Transaction::TYPE_AUTHORIZATION_REQUEST,
                $data['maxAmount'],
                $this->sanitizeRequestData($data),
                $data['merchantReference'] ?? null
            );

            // Step 1: Create checkout
            $checkoutData = $this->prepareCheckoutData($data, $authorization);
            $checkoutResponse = $this->endpoint->createCheckout($checkoutData);

            if (! isset($checkoutResponse['transactionID'], $checkoutResponse['transactionSignature'])) {
                throw new SibsException('Invalid checkout response from SIBS');
            }

            $transactionId = $checkoutResponse['transactionID'];
            $transactionSignature = $checkoutResponse['transactionSignature'];

            // Update transaction with SIBS ID
            $transaction->updateWithSibsId($transactionId);

            // Step 2: Create authorization request
            $authRequestData = [
                'customerPhone' => $data['customerPhone'],
                'recurringTransaction' => [
                    'validityDate' => $authorization->validity_date->toISOString(),
                    'amountQualifier' => 'DEFAULT',
                    'description' => $data['description'],
                ],
            ];

            $authResponse = $this->endpoint->createAuthorizationRequest(
                $transactionId,
                $authRequestData,
                $transactionSignature
            );

            // Update transaction as successful
            $transaction->markAsSuccessful($authResponse);

            Log::info('Authorization request created successfully', [
                'authorization_id' => $authorization->id,
                'transaction_id' => $transactionId,
                'customer_phone' => $data['customerPhone'],
            ]);

            // Dispatch event
            event(new AuthorizationCreated($authorization));

            return $authorization;

        } catch (SibsException $e) {
            Log::error('Failed to create authorization request', [
                'customer_phone' => $data['customerPhone'],
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            if (isset($transaction)) {
                $transaction->markAsFailed([], null, $e->getMessage());
            }

            throw $e;
        }
    }

    /**
     * Get authorization by ID
     */
    public function getAuthorization(string $authorizationId): ?AuthorizedPayment
    {
        return AuthorizedPayment::where('authorization_id', $authorizationId)
            ->orWhere('id', $authorizationId)
            ->first();
    }

    /**
     * Cancel authorization
     */
    public function cancelAuthorization(string $authorizationId): bool
    {
        $authorization = $this->getAuthorization($authorizationId);

        if (! $authorization) {
            throw AuthorizationException::notFound($authorizationId);
        }

        if (! $authorization->isActive()) {
            throw AuthorizationException::inactive($authorizationId);
        }

        Log::info('Cancelling authorization', [
            'authorization_id' => $authorization->authorization_id,
            'customer_phone' => $authorization->customer_phone,
        ]);

        try {
            // Cancel with SIBS if we have an active authorization ID
            if ($authorization->authorization_id) {
                $this->endpoint->cancelAuthorization($authorization->authorization_id);
            }

            // Mark as cancelled locally
            $authorization->markAsCancelled();

            Log::info('Authorization cancelled successfully', [
                'authorization_id' => $authorization->authorization_id,
            ]);

            return true;

        } catch (SibsException $e) {
            Log::error('Failed to cancel authorization', [
                'authorization_id' => $authorization->authorization_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Check authorization status with SIBS
     */
    public function checkAuthorizationStatus(string $authorizationId): array
    {
        $authorization = $this->getAuthorization($authorizationId);

        if (! $authorization) {
            throw AuthorizationException::notFound($authorizationId);
        }

        if (! $authorization->authorization_id) {
            // If no SIBS ID yet, authorization is still pending
            return [
                'status' => 'pending',
                'authorization_id' => null,
                'local_status' => $authorization->status,
            ];
        }

        try {
            $response = $this->endpoint->getAuthorizationDetails($authorization->authorization_id);

            // Update local status based on SIBS response
            $this->updateAuthorizationFromSibsResponse($authorization, $response);

            return $response;

        } catch (SibsException $e) {
            Log::error('Failed to check authorization status', [
                'authorization_id' => $authorization->authorization_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * List active authorizations
     */
    public function listActiveAuthorizations(array $filters = []): Collection
    {
        $query = AuthorizedPayment::active();

        if (isset($filters['customer_phone'])) {
            $query->forCustomer($filters['customer_phone']);
        }

        if (isset($filters['merchant_reference'])) {
            $query->where('merchant_reference', $filters['merchant_reference']);
        }

        if (isset($filters['expires_before'])) {
            $query->where('validity_date', '<', $filters['expires_before']);
        }

        return $query->get();
    }

    /**
     * List authorizations expiring soon
     */
    public function listExpiringAuthorizations(int $days = 30): Collection
    {
        return AuthorizedPayment::expiringSoon($days)->get();
    }

    /**
     * Process expired authorizations
     */
    public function processExpiredAuthorizations(): int
    {
        $expiredCount = 0;

        AuthorizedPayment::active()
            ->where('validity_date', '<', now())
            ->chunk(100, function ($authorizations) use (&$expiredCount) {
                foreach ($authorizations as $authorization) {
                    $authorization->markAsExpired();

                    Log::info('Authorization marked as expired', [
                        'authorization_id' => $authorization->authorization_id,
                        'customer_phone' => $authorization->customer_phone,
                    ]);

                    event(new AuthorizationExpired($authorization));
                    $expiredCount++;
                }
            });

        if ($expiredCount > 0) {
            Log::info("Processed {$expiredCount} expired authorizations");
        }

        return $expiredCount;
    }

    /**
     * Parse validity date from various formats
     */
    private function parseValidityDate($validityDate): Carbon
    {
        if (! $validityDate) {
            $defaultDays = config('sibs-mbway-authorized-payments.authorized_payments.default_validity_days', 365);

            return now()->addDays($defaultDays);
        }

        if ($validityDate instanceof Carbon) {
            return $validityDate;
        }

        if (is_string($validityDate)) {
            return Carbon::parse($validityDate);
        }

        if (is_int($validityDate)) {
            return now()->addDays($validityDate);
        }

        throw new SibsException('Invalid validity date format');
    }

    /**
     * Prepare checkout data for SIBS API
     */
    private function prepareCheckoutData(array $data, AuthorizedPayment $authorization): array
    {
        return [
            'merchant' => [
                'terminalId' => (int) config('sibs-mbway-authorized-payments.credentials.terminal_id'),
                'channel' => config('sibs-mbway-authorized-payments.defaults.channel', 'web'),
                'merchantTransactionId' => $data['merchantReference'] ?? "AUTH_{$authorization->id}",
            ],
            'transaction' => [
                'transactionTimestamp' => now()->toISOString(),
                'description' => $data['description'],
                'moto' => false,
                'paymentType' => 'AUTH', // Authorization type
                'amount' => [
                    'value' => $data['maxAmount'],
                    'currency' => $data['currency'] ?? 'EUR',
                ],
                'paymentMethod' => ['MBWAY'],
            ],
            'recurringTransaction' => [
                'validityDate' => $authorization->validity_date->toISOString(),
                'amountQualifier' => 'DEFAULT',
                'description' => $data['description'],
            ],
        ];
    }

    /**
     * Update authorization status from SIBS response
     */
    private function updateAuthorizationFromSibsResponse(AuthorizedPayment $authorization, array $response): void
    {
        $sibsStatus = $response['status'] ?? '';

        switch ($sibsStatus) {
            case 'active':
                if ($authorization->status !== AuthorizedPayment::STATUS_ACTIVE) {
                    $authorization->markAsActive($response['authorizationId'] ?? '');
                }
                break;

            case 'cancelled':
            case 'expired':
                if ($authorization->status === AuthorizedPayment::STATUS_ACTIVE) {
                    $authorization->markAsCancelled();
                }
                break;
        }
    }

    /**
     * Sanitize request data for logging (remove sensitive info)
     */
    private function sanitizeRequestData(array $data): array
    {
        $sanitized = $data;

        if (isset($sanitized['customerPhone'])) {
            $phone = $sanitized['customerPhone'];
            $sanitized['customerPhone'] = substr($phone, 0, 6).'***'.substr($phone, -2);
        }

        return $sanitized;
    }
}
