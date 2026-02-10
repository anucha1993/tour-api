<?php

namespace App\Jobs;

use App\Models\Period;
use App\Models\Tour;
use App\Models\SystemSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * AutoCloseExpiredJob - Automatically close expired periods and tours
 * 
 * This job uses GLOBAL SystemSetting (not per-integration):
 * 1. Closes periods that have already departed (start_date < today - threshold)
 * 2. Closes tours that have ALL periods departed/closed
 * 
 * Run this job daily via scheduler
 */
class AutoCloseExpiredJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300; // 5 minutes

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('AutoCloseExpiredJob: Starting (global mode)');

        // Get global auto-close settings
        $settings = SystemSetting::getAutoCloseSettings();
        
        // Check if auto-close is enabled globally
        if (!($settings['enabled'] ?? false)) {
            Log::info('AutoCloseExpiredJob: Auto-close is disabled globally, skipping');
            return;
        }

        $stats = [
            'periods_closed' => 0,
            'tours_closed' => 0,
            'threshold_date' => null,
        ];

        $thresholdDays = $settings['threshold_days'] ?? 0;
        $thresholdDate = now()->subDays($thresholdDays)->toDateString();
        $stats['threshold_date'] = $thresholdDate;

        // Auto-close expired periods
        if ($settings['periods'] ?? true) {
            $stats['periods_closed'] = $this->closeExpiredPeriods($thresholdDate);
        }

        // Auto-close tours with all periods expired
        if ($settings['tours'] ?? true) {
            $stats['tours_closed'] = $this->closeExpiredTours();
        }

        Log::info('AutoCloseExpiredJob: Completed', $stats);
    }

    /**
     * Close periods that have already departed
     */
    protected function closeExpiredPeriods(string $thresholdDate): int
    {
        // Find periods that:
        // - Have start_date before threshold date
        // - Are still open
        $count = Period::where('start_date', '<', $thresholdDate)
            ->where('status', '!=', 'closed')
            ->where('status', '!=', 'cancelled')
            ->update([
                'status' => 'closed',
                'updated_at' => now(),
            ]);

        if ($count > 0) {
            Log::info('AutoCloseExpiredJob: Closed expired periods', [
                'count' => $count,
                'threshold_date' => $thresholdDate,
            ]);
        }

        return $count;
    }

    /**
     * Close tours that have all periods expired/closed
     */
    protected function closeExpiredTours(): int
    {
        $today = now()->toDateString();
        $count = 0;

        // Find tours that have NO open/upcoming periods
        $tours = Tour::where('status', '!=', 'closed')
            ->where('status', '!=', 'disabled')
            ->where('status', '!=', 'inactive')
            ->whereDoesntHave('periods', function ($q) use ($today) {
                $q->where('start_date', '>=', $today)
                  ->where('status', '!=', 'closed')
                  ->where('status', '!=', 'cancelled');
            })
            ->whereHas('periods') // Must have at least one period
            ->get();

        foreach ($tours as $tour) {
            $tour->status = 'closed';
            $tour->save();
            $count++;

            Log::debug('AutoCloseExpiredJob: Closed tour (all periods expired)', [
                'tour_id' => $tour->id,
                'tour_code' => $tour->tour_code,
            ]);
        }

        if ($count > 0) {
            Log::info('AutoCloseExpiredJob: Closed tours with all periods expired', [
                'count' => $count,
            ]);
        }

        return $count;
    }
}
