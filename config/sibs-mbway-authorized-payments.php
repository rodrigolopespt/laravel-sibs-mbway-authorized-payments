<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | This value determines which SIBS environment to use.
    | Supported: "sandbox", "production"
    |
    */
    'environment' => env('SIBS_ENVIRONMENT', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | API Endpoints
    |--------------------------------------------------------------------------
    |
    | SIBS Gateway endpoints for different environments
    |
    */
    'endpoints' => [
        'sandbox' => [
            'api' => 'https://api-qly.sibspayments.com',
            'gateway' => 'https://spg.qly.site1.sibs.pt',
        ],
        'production' => [
            'api' => 'https://api.sibspayments.com',
            'gateway' => 'https://spg.site1.sibs.pt',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Credentials
    |--------------------------------------------------------------------------
    |
    | Your SIBS Gateway credentials
    |
    */
    'credentials' => [
        'terminal_id' => env('SIBS_TERMINAL_ID'),
        'auth_token' => env('SIBS_AUTH_TOKEN'),
        'client_id' => env('SIBS_CLIENT_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for webhook notifications from SIBS
    |
    */
    'webhook' => [
        'url' => env('SIBS_WEBHOOK_URL'),
        'secret' => env('SIBS_WEBHOOK_SECRET'),
        'route_prefix' => 'webhooks',
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Values
    |--------------------------------------------------------------------------
    |
    | Default values for transactions
    |
    */
    'defaults' => [
        'currency' => 'EUR',
        'channel' => 'web',
        'timeout' => 30, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorized Payments Configuration
    |--------------------------------------------------------------------------
    |
    | Specific configuration for MBWay Authorized Payments
    |
    */
    'authorized_payments' => [
        'default_validity_days' => env('SIBS_AUTH_VALIDITY_DAYS', 365),
        'max_amount' => env('SIBS_MAX_AMOUNT', 1000.00),
        'auto_retry_failed_charges' => env('SIBS_AUTO_RETRY', true),
        'retry_attempts' => env('SIBS_RETRY_ATTEMPTS', 3),
        'retry_delay_minutes' => env('SIBS_RETRY_DELAY', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure logging for SIBS operations
    |
    */
    'logging' => [
        'enabled' => env('SIBS_LOGGING_ENABLED', true),
        'channel' => env('SIBS_LOG_CHANNEL', 'stack'),
        'level' => env('SIBS_LOG_LEVEL', 'info'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for improved performance
    |
    */
    'cache' => [
        'enabled' => env('SIBS_CACHE_ENABLED', true),
        'ttl' => env('SIBS_CACHE_TTL', 3600), // 1 hour
        'prefix' => 'sibs_mbway_ap',
    ],
];
