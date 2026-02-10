<?php

namespace App\Console\Commands;

use App\Models\Tour;
use Illuminate\Console\Command;

class SeedManualOverridesCommand extends Command
{
    protected $signature = 'tours:seed-overrides 
                            {--mode=all : Mode: all, edited, status-only}
                            {--dry-run : Show what would be done without making changes}';
    
    protected $description = 'Seed manual_override_fields for existing tours that were edited before Smart Sync';

    // Fields commonly edited by admins
    protected $editableFields = [
        'title',
        'description',
        'short_description',
        'highlights',
        'conditions',
        'inclusions',
        'exclusions',
        'notes',
        'important_notes',
        'payment_terms',
        'cancellation_policy',
        'visa_info',
        'hashtags',
        'themes',
        'suitable_for',
        'hotel_star',
        'region',
        'sub_region',
        'badge',
        'promotion_type',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'og_title',
        'og_description',
    ];

    public function handle(): int
    {
        $mode = $this->option('mode');
        $dryRun = $this->option('dry-run');

        $this->info('=== Seeding manual_override_fields for existing tours ===');
        $this->newLine();

        // Get tours without override fields
        $query = Tour::whereNotNull('wholesaler_id')
            ->whereNull('manual_override_fields');
        
        $totalTours = $query->count();
        
        if ($totalTours === 0) {
            $this->info('No tours found without manual_override_fields. Nothing to do.');
            return Command::SUCCESS;
        }

        $this->info("Found {$totalTours} tours without manual_override_fields");
        
        if ($dryRun) {
            $this->warn('[DRY RUN] No changes will be made');
        }

        $timestamp = now()->toIso8601String();
        $processed = 0;
        $skipped = 0;

        switch ($mode) {
            case 'all':
                $this->info('Mode: Mark ALL editable fields for ALL tours');
                
                $overrides = [];
                foreach ($this->editableFields as $field) {
                    $overrides[$field] = $timestamp;
                }

                if (!$dryRun) {
                    $updated = $query->update(['manual_override_fields' => json_encode($overrides)]);
                    $this->info("Updated {$updated} tours");
                } else {
                    $this->info("[DRY RUN] Would update {$totalTours} tours with " . count($this->editableFields) . " fields");
                }
                break;

            case 'edited':
                $this->info('Mode: Mark only fields that have values (appear edited)');
                
                $progressBar = $this->output->createProgressBar($totalTours);
                $progressBar->start();

                $query->cursor()->each(function ($tour) use ($timestamp, &$processed, &$skipped, $dryRun, $progressBar) {
                    $overrides = [];
                    
                    foreach ($this->editableFields as $field) {
                        $value = $tour->$field;
                        if (!empty($value) && $value !== null) {
                            $overrides[$field] = $timestamp;
                        }
                    }
                    
                    if (!empty($overrides)) {
                        if (!$dryRun) {
                            $tour->manual_override_fields = $overrides;
                            $tour->save();
                        }
                        $processed++;
                    } else {
                        $skipped++;
                    }
                    
                    $progressBar->advance();
                });

                $progressBar->finish();
                $this->newLine(2);
                $this->info("Processed: {$processed}, Skipped (no edits detected): {$skipped}");
                break;

            case 'status-only':
                $this->info('Mode: Mark status field only (minimal protection)');
                
                if (!$dryRun) {
                    $updated = $query->update(['manual_override_fields' => json_encode(['status' => $timestamp])]);
                    $this->info("Updated {$updated} tours with status override only");
                } else {
                    $this->info("[DRY RUN] Would update {$totalTours} tours with status field only");
                }
                break;

            default:
                $this->error("Invalid mode: {$mode}. Use: all, edited, or status-only");
                return Command::FAILURE;
        }

        $this->newLine();
        $this->showSummary();

        return Command::SUCCESS;
    }

    protected function showSummary(): void
    {
        $withOverrides = Tour::whereNotNull('manual_override_fields')->count();
        $withoutOverrides = Tour::whereNull('manual_override_fields')->count();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Tours with overrides', $withOverrides],
                ['Tours without overrides', $withoutOverrides],
                ['Total tours', $withOverrides + $withoutOverrides],
            ]
        );
    }
}
