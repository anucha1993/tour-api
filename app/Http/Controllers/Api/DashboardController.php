<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wholesaler;
use App\Models\Tour;
use App\Models\SyncLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get dashboard summary statistics.
     */
    public function summary(): JsonResponse
    {
        $now = Carbon::now();

        // Stats
        $totalWholesalers = Wholesaler::count();
        $activeWholesalers = Wholesaler::where('is_active', true)->count();
        $totalTours = Tour::count();
        $totalPeriods = DB::table('periods')->count();
        $upcomingPeriods = DB::table('periods')
            ->where('start_date', '>=', $now->toDateString())
            ->count();

        // Sync stats
        $todaySyncs = SyncLog::whereDate('started_at', $now->toDateString())->count();
        $successSyncs = SyncLog::whereDate('started_at', $now->toDateString())
            ->where('status', 'completed')->count();
        $failedSyncs = SyncLog::whereDate('started_at', $now->toDateString())
            ->where('status', 'failed')->count();

        // Tours per wholesaler
        $toursPerWholesaler = Wholesaler::withCount('tours')
            ->orderBy('tours_count', 'desc')
            ->get()
            ->map(fn($w) => [
                'id' => $w->id,
                'name' => $w->name,
                'code' => $w->code,
                'logo_url' => $w->logo_url,
                'tours_count' => $w->tours_count,
                'is_active' => $w->is_active,
            ]);

        // Recent sync logs
        $recentSyncs = SyncLog::with('wholesaler:id,name,code,logo_url')
            ->orderBy('started_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($log) => [
                'id' => $log->id,
                'wholesaler_name' => $log->wholesaler?->name ?? 'Unknown',
                'wholesaler_code' => $log->wholesaler?->code ?? '?',
                'wholesaler_logo' => $log->wholesaler?->logo_url,
                'status' => $log->status,
                'sync_type' => $log->sync_type,
                'tours_received' => $log->tours_received,
                'tours_created' => $log->tours_created,
                'tours_updated' => $log->tours_updated,
                'tours_failed' => $log->tours_failed,
                'started_at' => $log->started_at,
                'completed_at' => $log->completed_at,
                'duration_seconds' => $log->started_at && $log->completed_at
                    ? Carbon::parse($log->completed_at)->diffInSeconds(Carbon::parse($log->started_at))
                    : null,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'total_wholesalers' => $totalWholesalers,
                    'active_wholesalers' => $activeWholesalers,
                    'total_tours' => $totalTours,
                    'total_periods' => $totalPeriods,
                    'upcoming_periods' => $upcomingPeriods,
                    'today_syncs' => $todaySyncs,
                    'success_syncs' => $successSyncs,
                    'failed_syncs' => $failedSyncs,
                ],
                'tours_per_wholesaler' => $toursPerWholesaler,
                'recent_syncs' => $recentSyncs,
            ],
        ]);
    }
}
