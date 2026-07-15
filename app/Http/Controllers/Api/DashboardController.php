<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wholesaler;
use App\Models\Tour;
use App\Models\SyncLog;
use App\Models\Booking;
use App\Models\WebMember;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Resolve the [from, to] window from the request. Returns null values when
     * the caller asked for the "all-time" view so the callers can skip the
     * date filter entirely.
     *
     * Supported `period` values: `all` | `day` | `week` | `month` | `custom`.
     * For `custom`, both `from` and `to` (YYYY-MM-DD) must be provided.
     */
    private function resolvePeriod(Request $request): array
    {
        $period = $request->input('period', 'all');
        $now = Carbon::now();

        switch ($period) {
            case 'day':
                return [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'day'];
            case 'week':
                return [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay(), 'week'];
            case 'month':
                return [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay(), 'month'];
            case 'custom': {
                $fromStr = $request->input('from');
                $toStr = $request->input('to');
                if ($fromStr && $toStr) {
                    try {
                        $from = Carbon::parse($fromStr)->startOfDay();
                        $to = Carbon::parse($toStr)->endOfDay();
                        if ($from->lte($to)) {
                            return [$from, $to, 'custom'];
                        }
                    } catch (\Throwable) {
                        // fall through to "all"
                    }
                }
                return [null, null, 'all'];
            }
            case 'all':
            default:
                return [null, null, 'all'];
        }
    }

    /**
     * Get dashboard summary statistics.
     */
    public function summary(Request $request): JsonResponse
    {
        $now = Carbon::now();
        [$from, $to, $resolvedPeriod] = $this->resolvePeriod($request);
        // Closure applied to any Eloquent/DB query that should be date-scoped.
        // When no window is set (all-time) it's a no-op so callers can share code.
        $scopeToPeriod = function ($query, string $column = 'created_at') use ($from, $to) {
            if ($from && $to) {
                $query->whereBetween($column, [$from, $to]);
            }
            return $query;
        };

        // ─── Core counts ───
        $totalWholesalers = Wholesaler::count();
        $activeWholesalers = Wholesaler::where('is_active', true)->count();
        $totalTours = Tour::count();
        $publishedTours = Tour::where('status', 'active')->count();
        $totalPeriods = DB::table('periods')->count();
        $upcomingPeriods = DB::table('periods')
            ->where('start_date', '>=', $now->toDateString())
            ->count();
        $totalViews = (int) Tour::sum('view_count');
        $viewsInPeriod = (int) $scopeToPeriod(DB::table('tour_view_logs'), 'viewed_at')->count();
        $totalMembers = WebMember::count();
        // Members created within the selected period. Falls back to the same
        // total when no window is set so the frontend can render both values
        // side-by-side without special-casing.
        $newMembersInPeriod = $scopeToPeriod(WebMember::query())->count();

        // Sync stats
        $todaySyncs = SyncLog::whereDate('started_at', $now->toDateString())->count();
        $successSyncs = SyncLog::whereDate('started_at', $now->toDateString())
            ->where('status', 'completed')->count();
        $failedSyncs = SyncLog::whereDate('started_at', $now->toDateString())
            ->where('status', 'failed')->count();

        // ─── Visitor interest by country (from tour view_count) ───
        // Note: tours.view_count is a lifetime counter; there is no per-day
        // breakdown available, so this widget always shows all-time popularity.
        $viewsByCountry = DB::table('tours')
            ->join('countries', 'tours.primary_country_id', '=', 'countries.id')
            ->whereNotNull('tours.primary_country_id')
            ->select(
                'countries.id',
                'countries.name_th',
                'countries.name_en',
                'countries.flag_emoji',
                DB::raw('SUM(tours.view_count) as total_views'),
                DB::raw('COUNT(tours.id) as tours_count')
            )
            ->groupBy('countries.id', 'countries.name_th', 'countries.name_en', 'countries.flag_emoji')
            ->orderByDesc('total_views')
            ->limit(10)
            ->get()
            ->map(fn($row) => [
                'id' => $row->id,
                'name' => $row->name_th ?: $row->name_en,
                'flag' => $row->flag_emoji,
                'total_views' => (int) $row->total_views,
                'tours_count' => (int) $row->tours_count,
            ]);

        // ─── Booking statistics (scoped to the selected period) ───
        $mkCount = fn(?string $status = null) => (function () use ($scopeToPeriod, $status) {
            $q = Booking::query();
            if ($status !== null) $q->where('status', $status);
            return $scopeToPeriod($q)->count();
        })();
        $bookingStats = [
            'total' => $mkCount(),
            'pending' => $mkCount('pending'),
            'confirmed' => $mkCount('confirmed'),
            'paid' => $mkCount('paid'),
            'completed' => $mkCount('completed'),
            'cancelled' => $mkCount('cancelled'),
            // "this_month" stays as calendar-month even under a custom period so
            // the badge next to the KPI keeps its familiar meaning.
            'this_month' => Booking::whereYear('created_at', $now->year)
                ->whereMonth('created_at', $now->month)->count(),
            'revenue' => (float) $scopeToPeriod(
                Booking::whereIn('status', ['confirmed', 'paid', 'completed'])
            )->sum('total_amount'),
            'from_website' => $scopeToPeriod(Booking::where('source', 'website'))->count(),
            'from_flash_sale' => $scopeToPeriod(Booking::where('source', 'flash_sale'))->count(),
        ];

        // ─── Bookings by country (scoped) ───
        $bookingsByCountryQuery = DB::table('bookings')
            ->join('tours', 'bookings.tour_id', '=', 'tours.id')
            ->join('countries', 'tours.primary_country_id', '=', 'countries.id')
            ->whereNotIn('bookings.status', ['cancelled']);
        if ($from && $to) {
            $bookingsByCountryQuery->whereBetween('bookings.created_at', [$from, $to]);
        }
        $bookingsByCountry = $bookingsByCountryQuery
            ->select(
                'countries.id',
                'countries.name_th',
                'countries.name_en',
                'countries.flag_emoji',
                DB::raw('COUNT(bookings.id) as bookings_count'),
                DB::raw('SUM(bookings.total_amount) as revenue')
            )
            ->groupBy('countries.id', 'countries.name_th', 'countries.name_en', 'countries.flag_emoji')
            ->orderByDesc('bookings_count')
            ->limit(10)
            ->get()
            ->map(fn($row) => [
                'id' => $row->id,
                'name' => $row->name_th ?: $row->name_en,
                'flag' => $row->flag_emoji,
                'bookings_count' => (int) $row->bookings_count,
                'revenue' => (float) $row->revenue,
            ]);

        // ─── Recent bookings (always latest, unfiltered by period) ───
        $recentBookings = Booking::with(['tour:id,title,primary_country_id', 'tour.country:id,name_th,name_en,flag_emoji'])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn($b) => [
                'id' => $b->id,
                'booking_code' => $b->booking_code,
                'customer_name' => trim(($b->first_name ?? '') . ' ' . ($b->last_name ?? '')) ?: '-',
                'tour_title' => $b->tour?->title ?? '-',
                'country' => $b->tour?->country
                    ? ($b->tour->country->name_th ?: $b->tour->country->name_en)
                    : null,
                'flag' => $b->tour?->country?->flag_emoji,
                'total_amount' => (float) $b->total_amount,
                'status' => $b->status,
                'source' => $b->source,
                'created_at' => $b->created_at,
            ]);

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
            ->limit(8)
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
                'period' => [
                    'key' => $resolvedPeriod,
                    'from' => $from?->toIso8601String(),
                    'to' => $to?->toIso8601String(),
                ],
                'stats' => [
                    'total_wholesalers' => $totalWholesalers,
                    'active_wholesalers' => $activeWholesalers,
                    'total_tours' => $totalTours,
                    'published_tours' => $publishedTours,
                    'total_periods' => $totalPeriods,
                    'upcoming_periods' => $upcomingPeriods,
                    'today_syncs' => $todaySyncs,
                    'success_syncs' => $successSyncs,
                    'failed_syncs' => $failedSyncs,
                    'total_views' => $totalViews,
                    'views_in_period' => $viewsInPeriod,
                    'total_members' => $totalMembers,
                    'new_members_in_period' => $newMembersInPeriod,
                ],
                'booking_stats' => $bookingStats,
                'views_by_country' => $viewsByCountry,
                'bookings_by_country' => $bookingsByCountry,
                'recent_bookings' => $recentBookings,
                'tours_per_wholesaler' => $toursPerWholesaler,
                'recent_syncs' => $recentSyncs,
            ],
        ]);
    }
}
