<?php

namespace Rodrigolopespt\SibsMbwayAP;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Rodrigolopespt\SibsMbwayAP\Api\Client;
use Rodrigolopespt\SibsMbwayAP\Services\AuthorizedPaymentService;
use Rodrigolopespt\SibsMbwayAP\Services\ChargeService;
use Rodrigolopespt\SibsMbwayAP\Services\WebhookService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SibsMbwayAPServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('sibs-mbway-authorized-payments')
            ->hasConfigFile('sibs-mbway-authorized-payments')
            ->hasMigrations([
                'create_sibs_authorized_payments_table',
                'create_sibs_charges_table',
                'create_sibs_transactions_table',
            ])
            ->hasCommands([
                \Rodrigolopespt\SibsMbwayAP\Commands\CleanupExpiredCommand::class,
                \Rodrigolopespt\SibsMbwayAP\Commands\ProcessExpiredAuthorizationsCommand::class,
                \Rodrigolopespt\SibsMbwayAP\Commands\RetryFailedChargesCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Register HTTP Client
        $this->app->singleton(Client::class, function ($app) {
            $config = $app['config']->get('sibs-mbway-authorized-payments');

            return new Client($config);
        });

        // Register Services
        $this->app->singleton(AuthorizedPaymentService::class, function ($app) {
            return new AuthorizedPaymentService($app->make(Client::class));
        });

        $this->app->singleton(ChargeService::class, function ($app) {
            return new ChargeService($app->make(Client::class));
        });

        $this->app->singleton(WebhookService::class, function ($app) {
            return new WebhookService;
        });

        // Register main package manager
        $this->app->singleton('sibs-mbway-ap', function ($app) {
            return new \Rodrigolopespt\SibsMbwayAP\SibsMbwayAPManager(
                $app->make(AuthorizedPaymentService::class),
                $app->make(ChargeService::class)
            );
        });
    }

    public function packageBooted(): void
    {
        // Register rate limiting for webhooks
        $this->registerRateLimiting();

        // Register webhook routes
        $this->registerWebhookRoutes();
    }

    protected function registerWebhookRoutes(): void
    {
        $config = config('sibs-mbway-authorized-payments.webhook', []);

        if (empty($config['url'])) {
            return;
        }

        $routePrefix = $config['route_prefix'] ?? 'webhooks';
        $middleware = $config['middleware'] ?? ['api'];

        Route::group([
            'prefix' => $routePrefix,
            'middleware' => array_merge($middleware, ['throttle:sibs-webhook']),
        ], function () {
            Route::post('sibs', [\Rodrigolopespt\SibsMbwayAP\Http\Controllers\WebhookController::class, 'handle'])
                ->name('sibs.webhook');
        });
    }

    /**
     * Register rate limiting for SIBS webhooks
     */
    protected function registerRateLimiting(): void
    {
        RateLimiter::for('sibs-webhook', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response('Too many webhook requests', 429, $headers);
                });
        });
    }
}
