<?php

namespace Rodrigolopespt\SibsMbwayAP\Commands;

use Illuminate\Console\Command;
use Rodrigolopespt\SibsMbwayAP\Models\AuthorizedPayment;
use Rodrigolopespt\SibsMbwayAP\Models\Charge;
use Rodrigolopespt\SibsMbwayAP\Models\Transaction;

/**
 * Command to cleanup expired authorizations and old transactions
 */
class CleanupExpiredCommand extends Command
{
    protected $signature = 'sibs:cleanup-expired 
                            {--days=90 : Days to keep completed transactions}
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--force : Force deletion without confirmation}';

    protected $description = 'Cleanup expired authorizations and old transactions';

    public function handle(): int
    {
        $daysToKeep = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('🧹 SIBS MBWay Cleanup Tool');
        $this->info("Days to keep: {$daysToKeep}");
        $this->info('Dry run: '.($dryRun ? 'Yes' : 'No'));
        $this->line('');

        // 1. Process expired authorizations
        $this->processExpiredAuthorizations($dryRun);

        // 2. Cleanup old transactions
        $this->cleanupOldTransactions($daysToKeep, $dryRun);

        // 3. Cleanup old successful charges (keep failures for analysis)
        $this->cleanupOldCharges($daysToKeep, $dryRun);

        if (! $dryRun && ! $force) {
            if (! $this->confirm('Are you sure you want to proceed with cleanup?')) {
                $this->info('❌ Cleanup cancelled');

                return Command::SUCCESS;
            }
        }

        $this->info('✅ Cleanup completed successfully');

        return Command::SUCCESS;
    }

    /**
     * Mark expired authorizations as expired
     */
    private function processExpiredAuthorizations(bool $dryRun): void
    {
        $this->info('🔍 Processing expired authorizations...');

        $expiredAuths = AuthorizedPayment::active()
            ->where('validity_date', '<', now())
            ->get();

        if ($expiredAuths->isEmpty()) {
            $this->info('✅ No expired authorizations found');

            return;
        }

        $this->table(
            ['ID', 'Customer Phone', 'Max Amount', 'Expires'],
            $expiredAuths->map(fn ($auth) => [
                $auth->id,
                $auth->formatted_phone,
                '€'.number_format($auth->max_amount, 2),
                $auth->validity_date->format('Y-m-d H:i'),
            ])
        );

        if (! $dryRun) {
            $updated = 0;
            foreach ($expiredAuths as $auth) {
                $auth->markAsExpired();
                $updated++;
            }
            $this->info("✅ Marked {$updated} authorizations as expired");
        } else {
            $this->warn("🔄 Would mark {$expiredAuths->count()} authorizations as expired");
        }
    }

    /**
     * Cleanup old transactions
     */
    private function cleanupOldTransactions(int $daysToKeep, bool $dryRun): void
    {
        $this->info('🔍 Cleaning up old transactions...');

        $cutoffDate = now()->subDays($daysToKeep);

        $oldTransactions = Transaction::where('created_at', '<', $cutoffDate)
            ->whereIn('status', ['success', 'cancelled'])
            ->get();

        if ($oldTransactions->isEmpty()) {
            $this->info('✅ No old transactions to cleanup');

            return;
        }

        $groupedByType = $oldTransactions->groupBy('type');

        $this->table(
            ['Type', 'Count', 'Oldest'],
            $groupedByType->map(fn ($transactions, $type) => [
                $type,
                $transactions->count(),
                $transactions->min('created_at'),
            ])
        );

        if (! $dryRun) {
            $deleted = Transaction::where('created_at', '<', $cutoffDate)
                ->whereIn('status', ['success', 'cancelled'])
                ->delete();

            $this->info("✅ Deleted {$deleted} old transactions");
        } else {
            $this->warn("🔄 Would delete {$oldTransactions->count()} old transactions");
        }
    }

    /**
     * Cleanup old successful charges (keep failed ones for analysis)
     */
    private function cleanupOldCharges(int $daysToKeep, bool $dryRun): void
    {
        $this->info('🔍 Cleaning up old successful charges...');

        $cutoffDate = now()->subDays($daysToKeep);

        $oldCharges = Charge::successful()
            ->where('created_at', '<', $cutoffDate)
            ->get();

        if ($oldCharges->isEmpty()) {
            $this->info('✅ No old charges to cleanup');

            return;
        }

        $totalAmount = $oldCharges->sum('amount');
        $this->info("Found {$oldCharges->count()} old successful charges totaling €".number_format($totalAmount, 2));

        if (! $dryRun) {
            // Only delete charges where the authorization is also expired/cancelled
            $deleted = Charge::successful()
                ->where('created_at', '<', $cutoffDate)
                ->whereHas('authorizedPayment', function ($query) {
                    $query->whereIn('status', ['expired', 'cancelled']);
                })
                ->delete();

            $this->info("✅ Deleted {$deleted} old charges from expired/cancelled authorizations");
        } else {
            $wouldDelete = Charge::successful()
                ->where('created_at', '<', $cutoffDate)
                ->whereHas('authorizedPayment', function ($query) {
                    $query->whereIn('status', ['expired', 'cancelled']);
                })
                ->count();

            $this->warn("🔄 Would delete {$wouldDelete} old charges from expired/cancelled authorizations");
        }
    }
}
