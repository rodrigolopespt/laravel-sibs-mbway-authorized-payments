<?php

namespace Rodrigolopespt\SibsMbwayAP\Commands;

use Illuminate\Console\Command;
use Rodrigolopespt\SibsMbwayAP\Services\AuthorizedPaymentService;

/**
 * Command to process expired authorizations
 */
class ProcessExpiredAuthorizationsCommand extends Command
{
    protected $signature = 'sibs:process-expired-authorizations';

    protected $description = 'Process and mark expired authorizations as expired';

    private AuthorizedPaymentService $authService;

    public function __construct(AuthorizedPaymentService $authService)
    {
        parent::__construct();
        $this->authService = $authService;
    }

    public function handle(): int
    {
        $this->info('🔄 Processing expired authorizations...');

        try {
            $expiredCount = $this->authService->processExpiredAuthorizations();

            if ($expiredCount === 0) {
                $this->info('✅ No expired authorizations found');
            } else {
                $this->info("✅ Processed {$expiredCount} expired authorizations");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Failed to process expired authorizations: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
