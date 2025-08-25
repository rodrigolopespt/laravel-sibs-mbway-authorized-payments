<?php

namespace Rodrigolopespt\SibsMbwayAP;

use Rodrigolopespt\SibsMbwayAP\Models\AuthorizedPayment;
use Rodrigolopespt\SibsMbwayAP\Models\Charge;
use Rodrigolopespt\SibsMbwayAP\Services\AuthorizedPaymentService;
use Rodrigolopespt\SibsMbwayAP\Services\ChargeService;

/**
 * Main manager class for SIBS MBWay Authorized Payments
 */
class SibsMbwayAPManager
{
    private AuthorizedPaymentService $authService;

    private ChargeService $chargeService;

    public function __construct(AuthorizedPaymentService $authService, ChargeService $chargeService)
    {
        $this->authService = $authService;
        $this->chargeService = $chargeService;
    }

    /**
     * Create a new authorization request
     */
    public function createAuthorization(array $data): AuthorizedPayment
    {
        return $this->authService->createAuthorization($data);
    }

    /**
     * Get authorization by ID
     */
    public function getAuthorization(string $authorizationId): ?AuthorizedPayment
    {
        return $this->authService->getAuthorization($authorizationId);
    }

    /**
     * Cancel authorization
     */
    public function cancelAuthorization(string $authorizationId): bool
    {
        return $this->authService->cancelAuthorization($authorizationId);
    }

    /**
     * Check authorization status with SIBS
     */
    public function checkAuthorizationStatus(string $authorizationId): array
    {
        return $this->authService->checkAuthorizationStatus($authorizationId);
    }

    /**
     * List active authorizations
     */
    public function listActiveAuthorizations(array $filters = []): \Illuminate\Support\Collection
    {
        return $this->authService->listActiveAuthorizations($filters);
    }

    /**
     * List authorizations expiring soon
     */
    public function listExpiringAuthorizations(int $days = 30): \Illuminate\Support\Collection
    {
        return $this->authService->listExpiringAuthorizations($days);
    }

    /**
     * Process charge on an authorized payment
     */
    public function processCharge(AuthorizedPayment $authorization, float $amount, ?string $description = null): Charge
    {
        return $this->chargeService->processCharge($authorization, $amount, $description);
    }

    /**
     * Refund a charge
     */
    public function refundCharge(Charge $charge, ?float $amount = null): bool
    {
        return $this->chargeService->refundCharge($charge, $amount);
    }

    /**
     * Get charge status
     */
    public function getChargeStatus(string $transactionId): array
    {
        return $this->chargeService->getChargeStatus($transactionId);
    }

    /**
     * Process batch charges
     */
    public function processBatchCharges(array $charges): array
    {
        return $this->chargeService->processBatchCharges($charges);
    }

    /**
     * Process recurring charges
     */
    public function processRecurringCharges(): \Illuminate\Support\Collection
    {
        return $this->chargeService->processRecurringCharges();
    }

    /**
     * Retry failed charges
     */
    public function retryFailedCharges(): \Illuminate\Support\Collection
    {
        return $this->chargeService->retryFailedCharges();
    }
}
