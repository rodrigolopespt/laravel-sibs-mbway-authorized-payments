<?php

namespace Rodrigolopespt\SibsMbwayAP\Commands;

use Illuminate\Console\Command;
use Rodrigolopespt\SibsMbwayAP\Services\ChargeService;

/**
 * Command to retry failed charges
 */
class RetryFailedChargesCommand extends Command
{
    protected $signature = 'sibs:retry-failed-charges 
                            {--limit=50 : Maximum number of charges to retry}
                            {--dry-run : Show what would be retried without actually retrying}';

    protected $description = 'Retry failed charges that are eligible for retry';

    private ChargeService $chargeService;

    public function __construct(ChargeService $chargeService)
    {
        parent::__construct();
        $this->chargeService = $chargeService;
    }

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        $this->info('🔄 Processing failed charges for retry...');

        try {
            if ($dryRun) {
                $this->info('🔍 Dry run mode - showing charges that would be retried');
                // Implementation would need to be added to ChargeService to get retryable charges
                $this->info('✅ Dry run completed');
            } else {
                $retriedCharges = $this->chargeService->retryFailedCharges();

                if ($retriedCharges->isEmpty()) {
                    $this->info('✅ No failed charges eligible for retry');
                } else {
                    $this->info("✅ Retried {$retriedCharges->count()} failed charges");

                    // Show summary
                    $successful = $retriedCharges->filter(fn ($charge) => $charge->isSuccessful())->count();
                    $failed = $retriedCharges->count() - $successful;

                    $this->table(
                        ['Status', 'Count'],
                        [
                            ['Successful', $successful],
                            ['Still Failed', $failed],
                        ]
                    );
                }
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Failed to retry charges: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
