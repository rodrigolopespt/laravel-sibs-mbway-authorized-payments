<?php

namespace Rodrigolopespt\SibsMbwayAP\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Rodrigolopespt\SibsMbwayAP\Services\WebhookService;

/**
 * Controller for handling SIBS webhook notifications
 */
class WebhookController extends Controller
{
    private WebhookService $webhookService;

    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;

        // Apply webhook validation middleware
        $this->middleware(\Rodrigolopespt\SibsMbwayAP\Http\Middleware\ValidateWebhook::class);
    }

    /**
     * Handle incoming webhook
     */
    public function handle(Request $request): Response
    {
        Log::info('Received SIBS webhook', [
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $payload = [];
        try {
            $payload = $request->json()->all();

            if (empty($payload)) {
                Log::error('Empty webhook payload received');

                return response('Invalid payload', 400);
            }

            Log::info('Processing webhook payload', [
                'payload_structure' => $this->getPayloadStructure($payload),
            ]);

            // Process the webhook
            $this->webhookService->handle($payload);

            Log::info('Webhook processed successfully');

            return response('OK', 200);

        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload_hash' => hash('sha256', json_encode($payload)),
            ]);

            // Determine if this is a transient error that should be retried
            $isTransientError = $this->isTransientError($e);

            if ($isTransientError) {
                // Return 503 to allow SIBS to retry
                Log::info('Returning 503 for transient error - SIBS will retry');

                return response('Temporary failure', 503);
            } else {
                // Return 400 to prevent SIBS from retrying invalid webhooks
                Log::info('Returning 400 for permanent error - SIBS will not retry');

                return response('Permanent failure', 400);
            }
        }
    }

    /**
     * Get payload structure for logging (without sensitive data)
     */
    private function getPayloadStructure(array $payload): array
    {
        $structure = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $structure[$key] = $this->getPayloadStructure($value);
            } else {
                $structure[$key] = gettype($value);
            }
        }

        return $structure;
    }

    /**
     * Determine if error is transient and should be retried
     */
    private function isTransientError(\Exception $e): bool
    {
        // Database connection errors
        if ($e instanceof \PDOException ||
            str_contains($e->getMessage(), 'database') ||
            str_contains($e->getMessage(), 'connection')) {
            return true;
        }

        // HTTP timeout errors
        if (str_contains($e->getMessage(), 'timeout') ||
            str_contains($e->getMessage(), 'Connection timed out')) {
            return true;
        }

        // Memory exhaustion
        if (str_contains($e->getMessage(), 'memory')) {
            return true;
        }

        // Redis/Cache connection errors
        if (str_contains($e->getMessage(), 'redis') ||
            str_contains($e->getMessage(), 'cache')) {
            return true;
        }

        // Service unavailable errors
        if (method_exists($e, 'getStatusCode') &&
            in_array($e->getStatusCode(), [503, 504, 502])) {
            return true;
        }

        // Default to permanent error
        return false;
    }
}
