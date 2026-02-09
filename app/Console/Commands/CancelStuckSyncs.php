<?php

namespace App\Console\Commands;

use App\Models\SyncLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CancelStuckSyncs - Automatically cancel syncs that appear stuck
 * 
 * This command should be scheduled to run every 5 minutes:
 * $schedule->command('sync:cancel-stuck')->everyFiveMinutes();
 */
class CancelStuckSyncs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:cancel-stuck 
                            {--timeout=30 : Minutes of inactivity before marking as stuck}
                            {--dry-run : Show what would be cancelled without actually cancelling}
                            {--force : Skip confirmation prompt (for scheduled runs)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel sync jobs that appear to be stuck (no heartbeat activity)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $timeoutMinutes = (int) $this->option('timeout');
        $dryRun = $this->option('dry-run');

        $this->info("Checking for stuck syncs (timeout: {$timeoutMinutes} minutes)...");

        // Find running syncs with no recent heartbeat
        $stuckSyncs = SyncLog::where('status', 'running')
            ->where(function ($query) use ($timeoutMinutes) {
                // No heartbeat at all and created more than timeout ago
                $query->whereNull('last_heartbeat_at')
                    ->where('created_at', '<', now()->subMinutes($timeoutMinutes));
            })
            ->orWhere(function ($query) use ($timeoutMinutes) {
                // Has heartbeat but it's old
                $query->where('status', 'running')
                    ->whereNotNull('last_heartbeat_at')
                    ->where('last_heartbeat_at', '<', now()->subMinutes($timeoutMinutes));
            })
            ->get();

        if ($stuckSyncs->isEmpty()) {
            $this->info('No stuck syncs found.');
            return Command::SUCCESS;
        }

        $this->warn("Found {$stuckSyncs->count()} stuck sync(s):");

        $headers = ['ID', 'Wholesaler', 'Type', 'Started', 'Last Heartbeat', 'Progress'];
        $rows = [];

        foreach ($stuckSyncs as $sync) {
            $rows[] = [
                $sync->id,
                $sync->wholesaler_id,
                $sync->sync_type,
                $sync->created_at->diffForHumans(),
                $sync->last_heartbeat_at?->diffForHumans() ?? 'Never',
                "{$sync->progress_percent}% ({$sync->processed_items}/{$sync->total_items})",
            ];
        }

        $this->table($headers, $rows);

        if ($dryRun) {
            $this->warn('Dry run mode - no changes made.');
            return Command::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm('Do you want to cancel these stuck syncs?', true)) {
            $this->info('Cancelled by user.');
            return Command::SUCCESS;
        }

        // Cancel stuck syncs
        $cancelled = 0;
        foreach ($stuckSyncs as $sync) {
            $sync->update([
                'status' => 'timeout',
                'cancelled_at' => now(),
                'cancel_reason' => "Heartbeat timeout after {$timeoutMinutes} minutes of inactivity",
                'completed_at' => now(),
            ]);

            // Release cache lock for this wholesaler (stored in cache_locks table)
            $lockKey = "sync_lock:wholesaler:{$sync->wholesaler_id}";
            try {
                Cache::lock($lockKey)->forceRelease();
                $this->line("  Released lock: {$lockKey}");
            } catch (\Exception $e) {
                $this->warn("  Failed to release lock {$lockKey}: {$e->getMessage()}");
            }

            Log::warning('CancelStuckSyncs: Cancelled stuck sync', [
                'sync_log_id' => $sync->id,
                'wholesaler_id' => $sync->wholesaler_id,
                'timeout_minutes' => $timeoutMinutes,
            ]);

            $cancelled++;
        }

        // Also clean up any expired locks in cache_locks table
        $expiredLocks = DB::table('cache_locks')
            ->where('key', 'like', '%sync_lock%')
            ->where('expiration', '<', time())
            ->delete();
        if ($expiredLocks > 0) {
            $this->info("Cleaned up {$expiredLocks} expired lock(s) from cache_locks table.");
        }

        $this->info("Cancelled {$cancelled} stuck sync(s).");

        return Command::SUCCESS;
    }
}

