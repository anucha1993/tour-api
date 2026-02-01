<?php

namespace App\Console\Commands;

use App\Jobs\SyncToursJob;
use App\Models\WholesalerApiConfig;
use Illuminate\Console\Command;

class SyncToursCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:tours 
                            {wholesaler? : The wholesaler ID to sync (optional, syncs all if not provided)}
                            {--type=incremental : Sync type: incremental or full}
                            {--queue : Dispatch to queue instead of running synchronously}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync tours from external wholesaler APIs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $wholesalerId = $this->argument('wholesaler');
        $syncType = $this->option('type');
        $useQueue = $this->option('queue');

        // Validate sync type
        if (!in_array($syncType, ['incremental', 'full'])) {
            $this->error("Invalid sync type: {$syncType}. Use 'incremental' or 'full'.");
            return Command::FAILURE;
        }

        // Get configs to sync
        if ($wholesalerId) {
            $configs = WholesalerApiConfig::where('wholesaler_id', $wholesalerId)
                ->where('sync_enabled', true)
                ->get();

            if ($configs->isEmpty()) {
                $this->error("No enabled sync config found for wholesaler ID: {$wholesalerId}");
                return Command::FAILURE;
            }
        } else {
            // Get all enabled configs
            $configs = WholesalerApiConfig::where('sync_enabled', true)->get();

            if ($configs->isEmpty()) {
                $this->info('No enabled sync configurations found.');
                return Command::SUCCESS;
            }
        }

        $this->info("Found {$configs->count()} config(s) to sync");
        $this->newLine();

        foreach ($configs as $config) {
            $wholesalerName = $config->wholesaler->name ?? "Wholesaler #{$config->wholesaler_id}";
            
            $this->info("Processing: {$wholesalerName}");

            try {
                if ($useQueue) {
                    // Dispatch to queue
                    SyncToursJob::dispatch(
                        $config->wholesaler_id,
                        null,
                        $syncType
                    );
                    $this->info("  → Dispatched to queue");
                } else {
                    // Run synchronously
                    $job = new SyncToursJob(
                        $config->wholesaler_id,
                        null,
                        $syncType
                    );
                    
                    $this->info("  → Running sync ({$syncType})...");
                    $job->handle();
                    $this->info("  → Completed successfully");
                }
            } catch (\Exception $e) {
                $this->error("  → Failed: " . $e->getMessage());
            }

            $this->newLine();
        }

        $this->info('Sync process completed.');
        return Command::SUCCESS;
    }
}
