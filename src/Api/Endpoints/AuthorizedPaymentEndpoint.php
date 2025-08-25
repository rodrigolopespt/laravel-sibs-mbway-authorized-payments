<?php

namespace Rodrigolopespt\SibsMbwayAP\Api\Endpoints;

use Rodrigolopespt\SibsMbwayAP\Api\Client;

/**
 * SIBS API Endpoints for Authorized Payments (MBWay)
 */
class AuthorizedPaymentEndpoint
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Create checkout for authorization request
     */
    public function createCheckout(array $data): array
    {
        return $this->client->post('/api/v2/payments', $data);
    }

    /**
     * Create MBWay authorization request
     */
    public function createAuthorizationRequest(string $transactionId, array $data, string $transactionSignature): array
    {
        return $this->client->post(
            "/api/v2/payments/{$transactionId}/mbway-id/authorize",
            $data,
            $transactionSignature
        );
    }

    /**
     * Get authorization status
     */
    public function getAuthorizationStatus(string $transactionId): array
    {
        return $this->client->get("/api/v2/payments/{$transactionId}/status");
    }

    /**
     * Process charge on authorized payment
     */
    public function processCharge(string $authorizationId, array $data): array
    {
        return $this->client->post("/api/v2/authorized-payments/{$authorizationId}/charge", $data);
    }

    /**
     * Get charge status
     */
    public function getChargeStatus(string $transactionId): array
    {
        return $this->client->get("/api/v2/payments/{$transactionId}/status");
    }

    /**
     * Process refund for a charge
     */
    public function processRefund(string $transactionId, array $data): array
    {
        return $this->client->post("/api/v2/payments/{$transactionId}/refund", $data);
    }

    /**
     * Cancel authorization
     */
    public function cancelAuthorization(string $authorizationId): array
    {
        return $this->client->delete("/api/v2/authorized-payments/{$authorizationId}");
    }

    /**
     * Get authorization details
     */
    public function getAuthorizationDetails(string $authorizationId): array
    {
        return $this->client->get("/api/v2/authorized-payments/{$authorizationId}");
    }

    /**
     * List customer authorizations
     */
    public function listCustomerAuthorizations(string $customerPhone): array
    {
        return $this->client->get('/api/v2/authorized-payments', [
            'customerPhone' => $customerPhone,
        ]);
    }
}
