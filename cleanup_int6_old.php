<?php
/**
 * Cleanup old integration 6 tours that have only past periods or no usable data.
 * These were created before the zero-period rollback fix was in place (Mar 9-10).
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$today = '2026-03-28';
$tourIds = DB::table('tours')->where('wholesaler_id', 6)->pluck('id');

echo "=== Integration 6 Cleanup Script ===\n\n";

// Find tours that should be cleaned up:
// 1. Tours with ONLY past periods (no future start_date)
// 2. Tours with periods but NO offers at all
$toCleanup = [];

foreach ($tourIds as $tid) {
    $periodCount = DB::table('periods')->where('tour_id', $tid)->count();
    
    if ($periodCount == 0) continue; // Tours with no periods are fine (shouldn't exist but check)
    
    $futurePeriodCount = DB::table('periods')
        ->where('tour_id', $tid)
        ->where('start_date', '>=', $today)
        ->count();
    
    $pids = DB::table('periods')->where('tour_id', $tid)->pluck('id');
    $offerCount = DB::table('offers')->whereIn('period_id', $pids)->count();
    $pricedOfferCount = DB::table('offers')->whereIn('period_id', $pids)->where('price_adult', '>', 0)->count();
    
    $reasons = [];
    
    // All periods are past
    if ($futurePeriodCount === 0) {
        $reasons[] = 'all_past_periods';
    }
    
    // No offers at all (no prices)
    if ($offerCount === 0 && $periodCount > 0) {
        $reasons[] = 'no_offers';
    }
    
    // Has offers but all zero-price
    if ($offerCount > 0 && $pricedOfferCount === 0) {
        $reasons[] = 'all_zero_price';
    }
    
    if (!empty($reasons)) {
        $tour = DB::table('tours')->where('id', $tid)->first(['id', 'tour_code', 'status', 'title', 'created_at']);
        $toCleanup[] = [
            'tour' => $tour,
            'periods' => $periodCount,
            'future_periods' => $futurePeriodCount,
            'offers' => $offerCount,
            'priced' => $pricedOfferCount,
            'reasons' => $reasons,
        ];
    }
}

echo "Tours to cleanup: " . count($toCleanup) . "\n\n";
foreach ($toCleanup as $item) {
    $t = $item['tour'];
    $reasons = implode(', ', $item['reasons']);
    echo "  ID:{$t->id} {$t->tour_code} status:{$t->status} | periods:{$item['periods']} future:{$item['future_periods']} offers:{$item['offers']} priced:{$item['priced']} | reasons:{$reasons}\n";
    echo "    " . mb_substr($t->title, 0, 70) . " | created:{$t->created_at}\n";
}

// Ask for confirmation
echo "\n";
if (in_array('--execute', $argv ?? [])) {
    echo "=== EXECUTING CLEANUP ===\n\n";
    
    $deleted = 0;
    foreach ($toCleanup as $item) {
        $tid = $item['tour']->id;
        $code = $item['tour']->tour_code;
        
        DB::beginTransaction();
        try {
            // Delete offers for this tour's periods
            $pids = DB::table('periods')->where('tour_id', $tid)->pluck('id');
            $offersDeleted = DB::table('offers')->whereIn('period_id', $pids)->delete();
            
            // Delete periods
            $periodsDeleted = DB::table('periods')->where('tour_id', $tid)->delete();
            
            // Delete related tables (skip if table doesn't exist)
            $extras = 0;
            foreach (['tour_cities', 'tour_transports', 'itineraries'] as $tbl) {
                try { $extras += DB::table($tbl)->where('tour_id', $tid)->delete(); } catch (\Exception $e) {}
            }
            
            // Delete the tour
            DB::table('tours')->where('id', $tid)->delete();
            
            DB::commit();
            $deleted++;
            echo "  Deleted: {$code} (ID:{$tid}) | periods:{$periodsDeleted} offers:{$offersDeleted} extras:{$extras}\n";
        } catch (\Exception $e) {
            DB::rollBack();
            echo "  FAILED: {$code} (ID:{$tid}) | " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== Cleanup complete: {$deleted} tours deleted ===\n";
    
    // Show remaining stats
    $remaining = DB::table('tours')->where('wholesaler_id', 6)->count();
    $remainingPeriods = DB::table('periods')
        ->whereIn('tour_id', DB::table('tours')->where('wholesaler_id', 6)->pluck('id'))
        ->count();
    echo "Remaining: {$remaining} tours, {$remainingPeriods} periods\n";
} else {
    echo "DRY RUN - no changes made. Run with --execute to cleanup.\n";
}
