<?php

namespace Rodrigolopespt\SibsMbwayAP\Services;

use Illuminate\Support\Facades\Log;
use Rodrigolopespt\SibsMbwayAP\Events\AuthorizationCreated;
use Rodrigolopespt\SibsMbwayAP\Events\AuthorizationExpired;
use Rodrigolopespt\SibsMbwayAP\Events\ChargeFailed;
use Rodrigolopespt\SibsMbwayAP\Events\ChargeProcessed;
use Rodrigolopespt\SibsMbwayAP\Exceptions\SibsException;
use Rodrigolopespt\SibsMbwayAP\Models\AuthorizedPayment;
use Rodrigolopespt\SibsMbwayAP\Models\Charge;

/**
 * Service for handling SIBS webhook notifications
 */
class WebhookService
{
    /**
     * Handle incoming webhook payload
     */
    public function handle(array $payload): void
    {
        Log::info('Processing SIBS webhook', [
            'payload_keys' => array_keys($payload),
        ]);

        try {
            $eventType = $this->determineEventType($payload);

            switch ($eventType) {
                case 'authorization_approved':
                    $this->handleAuthorizationApproved($payload);
                    break;

                case 'authorization_cancelled':
                    $this->handleAuthorizationCancelled($payload);
                    break;

                case 'charge_success':
                    $this->handleChargeSuccess($payload);
                    break;

                case 'charge_failed':
                    $this->handleChargeFailed($payload);
                    break;

                default:
                    Log::warning('Unknown webhook event type', [
                        'event_type' => $eventType,
                        'payload' => $payload,
                    ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to process webhook', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            throw $e;
        }
    }

    /**
     * Validate webhook signature
     */
    public function validateSignature(string $signature, string $payload): bool
    {
        $secret = config('sibs-mbway-authorized-payments.webhook.secret');

        if (! $secret) {
            Log::warning('Webhook secret not configured - skipping signature validation');

            return true;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Handle authorization approved webhook
     */
    private function handleAuthorizationApproved(array $payload): void
    {
        $merchantTransactionId = $payload['merchantTransactionId'] ?? null;
        $authorizationId = $payload['authorizationId'] ?? null;

        if (! $merchantTransactionId || ! $authorizationId) {
            throw new SibsException('Missing required fields in authorization webhook');
        }

        Log::info('Processing authorization approved webhook', [
            'merchant_transaction_id' => $merchantTransactionId,
            'authorization_id' => $authorizationId,
        ]);

        // Find authorization by merchant reference or transaction ID
        $authorization = AuthorizedPayment::where('merchant_reference', $merchantTransactionId)
            ->orWhere(function ($query) use ($merchantTransactionId) {
                $query->whereHas('transactions', function ($q) use ($merchantTransactionId) {
                    $q->where('merchant_transaction_id', $merchantTransactionId);
                });
            })
            ->first();

        if (! $authorization) {
            Log::error('Authorization not found for webhook', [
                'merchant_transaction_id' => $merchantTransactionId,
            ]);

            return;
        }

        if ($authorization->status === AuthorizedPayment::STATUS_PENDING) {
            $authorization->markAsActive($authorizationId);

            Log::info('Authorization marked as active', [
                'authorization_id' => $authorization->id,
                'sibs_authorization_id' => $authorizationId,
            ]);

            event(new AuthorizationCreated($authorization));
        }
    }

    /**
     * Handle authorization cancelled webhook
     */
    private function handleAuthorizationCancelled(array $payload): void
    {
        $authorizationId = $payload['authorizationId'] ?? null;

        if (! $authorizationId) {
            throw new SibsException('Missing authorization ID in cancellation webhook');
        }

        Log::info('Processing authorization cancelled webhook', [
            'authorization_id' => $authorizationId,
        ]);

        $authorization = AuthorizedPayment::where('authorization_id', $authorizationId)->first();

        if ($authorization && $authorization->isActive()) {
            $authorization->markAsCancelled();

            Log::info('Authorization marked as cancelled', [
                'authorization_id' => $authorization->id,
            ]);

            event(new AuthorizationExpired($authorization));
        }
    }

    /**
     * Handle successful charge webhook
     */
    private function handleChargeSuccess(array $payload): void
    {
        $transactionId = $payload['transactionID'] ?? null;

        if (! $transactionId) {
            throw new SibsException('Missing transaction ID in charge success webhook');
        }

        Log::info('Processing charge success webhook', [
            'transaction_id' => $transactionId,
        ]);

        $charge = Charge::where('transaction_id', $transactionId)->first();

        if ($charge && $charge->isPending()) {
            $charge->markAsSuccessful($transactionId, $payload);

            Log::info('Charge marked as successful via webhook', [
                'charge_id' => $charge->id,
                'transaction_id' => $transactionId,
            ]);

            if ($charge->authorizedPayment !== null) {
                event(new ChargeProcessed($charge, $charge->authorizedPayment));
            }
        }
    }

    /**
     * Handle failed charge webhook
     */
    private function handleChargeFailed(array $payload): void
    {
        $transactionId = $payload['transactionID'] ?? null;
        $errorMessage = $payload['returnStatus']['statusDescription'] ?? 'Charge failed';

        if (! $transactionId) {
            throw new SibsException('Missing transaction ID in charge failed webhook');
        }

        Log::info('Processing charge failed webhook', [
            'transaction_id' => $transactionId,
            'error_message' => $errorMessage,
        ]);

        $charge = Charge::where('transaction_id', $transactionId)->first();

        if ($charge && $charge->isPending()) {
            $charge->markAsFailed($errorMessage, $payload);

            Log::info('Charge marked as failed via webhook', [
                'charge_id' => $charge->id,
                'transaction_id' => $transactionId,
                'error' => $errorMessage,
            ]);

            event(new ChargeFailed($charge, $errorMessage));
        }
    }

    /**
     * Determine event type from webhook payload
     */
    private function determineEventType(array $payload): string
    {
        // This logic depends on how SIBS structures their webhook payloads
        // The exact implementation will need to be adjusted based on SIBS documentation

        if (isset($payload['authorizationId']) && isset($payload['status'])) {
            switch ($payload['status']) {
                case 'approved':
                case 'active':
                    return 'authorization_approved';
                case 'cancelled':
                case 'expired':
                    return 'authorization_cancelled';
            }
        }

        if (isset($payload['transactionID']) && isset($payload['paymentStatus'])) {
            switch ($payload['paymentStatus']) {
                case 'Success':
                case 'Authorized':
                case 'Captured':
                    return 'charge_success';
                case 'Failed':
                case 'Declined':
                case 'Error':
                    return 'charge_failed';
            }
        }

        return 'unknown';
    }
}
