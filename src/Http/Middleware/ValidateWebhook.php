<?php

namespace Rodrigolopespt\SibsMbwayAP\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Rodrigolopespt\SibsMbwayAP\Services\WebhookService;

/**
 * Middleware to validate SIBS webhook signatures
 */
class ValidateWebhook
{
    private WebhookService $webhookService;

    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-SIBS-Signature');
        $payload = $request->getContent();

        Log::info('Webhook validation', [
            'has_signature' => ! empty($signature),
            'payload_length' => strlen($payload),
            'headers' => $request->headers->all(),
        ]);

        // Skip validation if no secret is configured (development mode)
        if (! config('sibs-mbway-authorized-payments.webhook.secret')) {
            Log::warning('Webhook signature validation skipped - no secret configured');

            return $next($request);
        }

        if (! $signature) {
            Log::error('Missing webhook signature');

            return response('Missing signature', 403);
        }

        if (! $this->webhookService->validateSignature($signature, $payload)) {
            Log::error('Invalid webhook signature', [
                'signature' => $signature,
                'payload_hash' => hash('sha256', $payload),
            ]);

            return response('Invalid signature', 403);
        }

        Log::info('Webhook signature validated successfully');

        return $next($request);
    }
}
