<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wholesaler;
use App\Models\Tour;
use App\Models\SyncLog;
use App\Models\Booking;
use App\Models\WebMember;
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
        $totalMembers = WebMember::count();

        // Sync stats
        $todaySyncs = SyncLog::whereDate('started_at', $now->toDateString())->count();
        $successSyncs = SyncLog::whereDate('started_at', $now->toDateString())
            ->where('status', 'completed')->count();
        $failedSyncs = SyncLog::whereDate('started_at', $now->toDateString())
            ->where('status', 'failed')->count();

        // ─── Visitor interest by country (from tour view_count) ───
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

        // ─── Booking statistics ───
        $bookingStats = [
            'total' => Booking::count(),
            'pending' => Booking::where('status', 'pending')->count(),
            'confirmed' => Booking::where('status', 'confirmed')->count(),
            'paid' => Booking::where('status', 'paid')->count(),
            'completed' => Booking::where('status', 'completed')->count(),
            'cancelled' => Booking::where('status', 'cancelled')->count(),
            'this_month' => Booking::whereYear('created_at', $now->year)
                ->whereMonth('created_at', $now->month)->count(),
            'revenue' => (float) Booking::whereIn('status', ['confirmed', 'paid', 'completed'])
                ->sum('total_amount'),
            'from_website' => Booking::where('source', 'website')->count(),
            'from_flash_sale' => Booking::where('source', 'flash_sale')->count(),
        ];

        // ─── Bookings by country ───
        $bookingsByCountry = DB::table('bookings')
            ->join('tours', 'bookings.tour_id', '=', 'tours.id')
            ->join('countries', 'tours.primary_country_id', '=', 'countries.id')
            ->whereNotIn('bookings.status', ['cancelled'])
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

        // ─── Recent bookings ───
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
                    'total_members' => $totalMembers,
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
