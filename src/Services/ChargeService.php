<?php

namespace Rodrigolopespt\SibsMbwayAP\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Rodrigolopespt\SibsMbwayAP\Api\Client;
use Rodrigolopespt\SibsMbwayAP\Api\Endpoints\AuthorizedPaymentEndpoint;
use Rodrigolopespt\SibsMbwayAP\Events\ChargeFailed;
use Rodrigolopespt\SibsMbwayAP\Events\ChargeProcessed;
use Rodrigolopespt\SibsMbwayAP\Exceptions\AuthorizationException;
use Rodrigolopespt\SibsMbwayAP\Exceptions\ChargeException;
use Rodrigolopespt\SibsMbwayAP\Exceptions\SibsException;
use Rodrigolopespt\SibsMbwayAP\Models\AuthorizedPayment;
use Rodrigolopespt\SibsMbwayAP\Models\Charge;
use Rodrigolopespt\SibsMbwayAP\Models\Transaction;
use Rodrigolopespt\SibsMbwayAP\Support\InputValidator;

/**
 * Service for processing charges on authorized payments
 */
class ChargeService
{
    private Client $client;

    private AuthorizedPaymentEndpoint $endpoint;

    public function __construct(Client $client)
    {
        $this->client = $client; // Used by endpoint internally
        $this->endpoint = new AuthorizedPaymentEndpoint($client);
    }

    /**
     * Process charge on an authorized payment
     */
    public function processCharge(AuthorizedPayment $authorization, float $amount, ?string $description = null): Charge
    {
        $this->validateChargeRequest($authorization, $amount);

        $validatedData = InputValidator::validateChargeData($amount, $description);
        $amount = $validatedData['amount'];
        $description = $validatedData['description'] ?? "Charge for {$authorization->description}";

        Log::info('Processing charge', [
            'authorization_id' => $authorization->authorization_id,
            'amount' => $amount,
            'customer_phone' => $authorization->formatted_phone,
        ]);

        try {
            // Create charge record
            $charge = Charge::create([
                'authorized_payment_id' => $authorization->id,
                'amount' => $amount,
                'currency' => $authorization->currency,
                'charge_date' => now(),
                'description' => $description,
                'merchant_reference' => $this->generateMerchantReference($authorization),
            ]);

            // Create transaction record
            $transaction = Transaction::createForModel(
                $charge,
                Transaction::TYPE_CHARGE,
                $amount,
                $this->prepareChargeData($authorization, $amount, $description)
            );

            // Process charge with SIBS
            $chargeData = $this->prepareChargeData($authorization, $amount, $description);
            $response = $this->endpoint->processCharge($authorization->authorization_id, $chargeData);

            if (! isset($response['transactionID'])) {
                throw new SibsException('Invalid charge response from SIBS');
            }

            $transactionId = $response['transactionID'];
            $paymentStatus = $response['paymentStatus'] ?? 'Unknown';

            // Update transaction with SIBS ID
            $transaction->updateWithSibsId($transactionId);

            // Handle response based on status
            if (in_array($paymentStatus, ['Success', 'Authorized', 'Captured'])) {
                $charge->markAsSuccessful($transactionId, $response);
                $transaction->markAsSuccessful($response, $response['returnStatus']['statusCode'] ?? null);

                Log::info('Charge processed successfully', [
                    'charge_id' => $charge->id,
                    'transaction_id' => $transactionId,
                    'amount' => $amount,
                ]);

                event(new ChargeProcessed($charge, $authorization));

            } else {
                $errorMessage = $response['returnStatus']['statusDescription'] ?? 'Charge failed';
                $charge->markAsFailed($errorMessage, $response);
                $transaction->markAsFailed($response, $response['returnStatus']['statusCode'] ?? null, $errorMessage);

                Log::warning('Charge failed', [
                    'charge_id' => $charge->id,
                    'transaction_id' => $transactionId,
                    'error' => $errorMessage,
                ]);

                event(new ChargeFailed($charge, $errorMessage));
            }

            return $charge;

        } catch (SibsException $e) {
            Log::error('Failed to process charge', [
                'authorization_id' => $authorization->authorization_id,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            if (isset($charge)) {
                $charge->markAsFailed($e->getMessage());
                event(new ChargeFailed($charge, $e->getMessage()));
            }

            if (isset($transaction)) {
                $transaction->markAsFailed([], null, $e->getMessage());
            }

            throw $e;
        }
    }

    /**
     * Refund a charge (full or partial)
     */
    public function refundCharge(Charge $charge, ?float $amount = null): bool
    {
        if (! $charge->canBeRefunded()) {
            throw ChargeException::invalidRefund($charge->transaction_id, 'Charge cannot be refunded');
        }

        $refundAmount = $amount ?? $charge->getRemainingRefundableAmount();

        if ($refundAmount <= 0 || $refundAmount > $charge->getRemainingRefundableAmount()) {
            throw ChargeException::invalidRefund($charge->transaction_id, 'Invalid refund amount');
        }

        Log::info('Processing refund', [
            'charge_id' => $charge->id,
            'transaction_id' => $charge->transaction_id,
            'refund_amount' => $refundAmount,
        ]);

        try {
            // Create transaction record for refund
            $transaction = Transaction::createForModel(
                $charge,
                Transaction::TYPE_REFUND,
                $refundAmount
            );

            // Process refund with SIBS
            $refundData = [
                'amount' => [
                    'value' => $refundAmount,
                    'currency' => $charge->currency,
                ],
                'description' => "Refund for charge {$charge->id}",
            ];

            $response = $this->endpoint->processRefund($charge->transaction_id, $refundData);

            if (isset($response['transactionID'])) {
                $transaction->updateWithSibsId($response['transactionID']);
            }

            $paymentStatus = $response['paymentStatus'] ?? 'Unknown';

            if (in_array($paymentStatus, ['Success', 'Refunded'])) {
                // Update charge refund information
                $charge->processRefund($refundAmount);
                $transaction->markAsSuccessful($response);

                Log::info('Refund processed successfully', [
                    'charge_id' => $charge->id,
                    'refund_amount' => $refundAmount,
                    'total_refunded' => $charge->refunded_amount,
                ]);

                return true;

            } else {
                $errorMessage = $response['returnStatus']['statusDescription'] ?? 'Refund failed';
                $transaction->markAsFailed($response, null, $errorMessage);

                Log::error('Refund failed', [
                    'charge_id' => $charge->id,
                    'error' => $errorMessage,
                ]);

                throw new ChargeException("Refund failed: {$errorMessage}");
            }

        } catch (SibsException $e) {
            Log::error('Failed to process refund', [
                'charge_id' => $charge->id,
                'error' => $e->getMessage(),
            ]);

            if (isset($transaction)) {
                $transaction->markAsFailed([], null, $e->getMessage());
            }

            throw $e;
        }
    }

    /**
     * Get charge status from SIBS
     */
    public function getChargeStatus(string $transactionId): array
    {
        try {
            return $this->endpoint->getChargeStatus($transactionId);

        } catch (SibsException $e) {
            Log::error('Failed to get charge status', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Process multiple charges in batch
     */
    public function processBatchCharges(array $charges): array
    {
        $results = [];

        foreach ($charges as $chargeData) {
            try {
                $authorization = $chargeData['authorization'];
                $amount = $chargeData['amount'];
                $description = $chargeData['description'] ?? null;

                $charge = $this->processCharge($authorization, $amount, $description);

                $results[] = [
                    'success' => true,
                    'charge' => $charge,
                    'authorization_id' => $authorization->id,
                ];

            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'authorization_id' => $chargeData['authorization']->id ?? null,
                ];

                Log::error('Batch charge failed', [
                    'authorization_id' => $chargeData['authorization']->id ?? null,
                    'amount' => $chargeData['amount'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Batch charges processed', [
            'total' => count($charges),
            'successful' => count(array_filter($results, fn ($r) => $r['success'])),
            'failed' => count(array_filter($results, fn ($r) => ! $r['success'])),
        ]);

        return $results;
    }

    /**
     * Process recurring charges (for scheduled/automatic execution)
     */
    public function processRecurringCharges(): Collection
    {
        // This would be called by a scheduled job
        // Implementation depends on business logic for when to charge

        $processedCharges = collect();

        Log::info('Processing recurring charges');

        // Example: Process monthly charges for active authorizations
        // This is a placeholder - actual implementation would depend on
        // subscription/billing schedule logic

        return $processedCharges;
    }

    /**
     * Retry failed charges
     */
    public function retryFailedCharges(): Collection
    {
        $retriedCharges = collect();

        Charge::retryable()->chunk(50, function ($charges) use ($retriedCharges) {
            foreach ($charges as $charge) {
                try {
                    $charge->recordRetryAttempt();

                    Log::info('Retrying failed charge', [
                        'charge_id' => $charge->id,
                        'retry_count' => $charge->retry_count,
                    ]);

                    // Retry the charge with same parameters
                    if ($charge->authorizedPayment !== null) {
                        $newCharge = $this->processCharge(
                            $charge->authorizedPayment,
                            $charge->amount,
                            $charge->description.' (Retry)'
                        );

                        $retriedCharges->push($newCharge);
                    }

                } catch (\Exception $e) {
                    Log::error('Charge retry failed', [
                        'charge_id' => $charge->id,
                        'retry_count' => $charge->retry_count,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        Log::info('Failed charges retry completed', [
            'processed' => $retriedCharges->count(),
        ]);

        return $retriedCharges;
    }

    /**
     * Validate charge request
     */
    private function validateChargeRequest(AuthorizedPayment $authorization, float $amount): void
    {
        if (! $authorization->isActive()) {
            throw AuthorizationException::inactive($authorization->authorization_id);
        }

        if ($authorization->isExpired()) {
            throw AuthorizationException::expired($authorization->authorization_id);
        }

        if ($amount <= 0) {
            throw new ChargeException('Charge amount must be greater than 0');
        }

        if (! $authorization->canChargeAmount($amount)) {
            throw ChargeException::amountExceeded($amount, $authorization->getRemainingAmount());
        }
    }

    /**
     * Prepare charge data for SIBS API
     */
    private function prepareChargeData(AuthorizedPayment $authorization, float $amount, string $description): array
    {
        return [
            'amount' => [
                'value' => $amount,
                'currency' => $authorization->currency,
            ],
            'description' => $description,
            'merchantTransactionId' => $this->generateMerchantReference($authorization),
        ];
    }

    /**
     * Generate merchant reference for charge
     */
    private function generateMerchantReference(AuthorizedPayment $authorization): string
    {
        return "CHARGE_{$authorization->id}_".now()->format('YmdHis');
    }
}
