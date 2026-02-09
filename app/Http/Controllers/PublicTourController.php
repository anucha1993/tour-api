<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use App\Models\TourView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicTourController extends Controller
{
    /**
     * แสดงข้อมูลทัวร์สำหรับ public (ไม่ต้อง auth)
     * GET /tours/{slug}
     */
    public function show(string $slug): JsonResponse
    {
        $tour = Tour::where('slug', $slug)
            ->where('status', 'active')
            ->where('is_published', true)
            ->with([
                'primaryCountry:id,iso2,name_en,name_th,flag_emoji',
                'countries:id,iso2,name_en,name_th,flag_emoji',
                'cities:id,name_en,name_th,country_id',
                'locations.city:id,name_en,name_th',
                'gallery',
                'transports.transport:id,code,name,type,image',
                'itineraries',
                'periods' => function ($query) {
                    $query->where('start_date', '>=', now()->toDateString())
                          ->where('status', 'open')
                          ->where('is_visible', true)
                          ->orderBy('start_date')
                          ->with('offer');
                },
            ])
            ->first();

        if (!$tour) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบทัวร์ที่ต้องการ',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatTourDetail($tour),
        ]);
    }

    /**
     * บันทึกสถิติการเข้าชม
     * POST /tours/{slug}/view
     */
    public function recordView(Request $request, string $slug): JsonResponse
    {
        $tour = Tour::where('slug', $slug)
            ->where('status', 'active')
            ->where('is_published', true)
            ->with(['primaryCountry:id,name_th', 'cities:id,name_th'])
            ->first();

        if (!$tour) {
            return response()->json(['success' => false], 404);
        }

        $userAgent = $request->userAgent();
        $sessionId = $request->input('session_id') ?: $request->ip() . '_' . substr(md5($userAgent ?? ''), 0, 8);

        // ป้องกันนับซ้ำ — ถ้า session เดียวกันดูทัวร์เดียวกันภายใน 30 นาที ไม่นับ
        $recentView = TourView::where('tour_id', $tour->id)
            ->where('session_id', $sessionId)
            ->where('viewed_at', '>=', now()->subMinutes(30))
            ->exists();

        if ($recentView) {
            return response()->json(['success' => true, 'duplicate' => true]);
        }

        // Collect city info
        $cityIds = $tour->cities->pluck('id')->toArray();
        $cityNames = $tour->cities->pluck('name_th')->toArray();

        TourView::create([
            'tour_id' => $tour->id,
            'session_id' => $sessionId,
            'ip_address' => $request->ip(),
            'user_agent' => substr($userAgent ?? '', 0, 500),
            'member_id' => $request->user()?->id,
            'country_id' => $tour->primary_country_id,
            'country_name' => $tour->primaryCountry?->name_th,
            'city_ids' => $cityIds,
            'city_names' => $cityNames,
            'hashtags' => $tour->hashtags,
            'themes' => $tour->themes,
            'region' => $tour->region,
            'sub_region' => $tour->sub_region,
            'price' => $tour->min_price ?? $tour->display_price,
            'promotion_type' => $tour->promotion_type,
            'duration_days' => $tour->duration_days,
            'referrer' => $request->input('referrer'),
            'utm_source' => $request->input('utm_source'),
            'utm_medium' => $request->input('utm_medium'),
            'utm_campaign' => $request->input('utm_campaign'),
            'device_type' => TourView::detectDeviceType($userAgent),
            'viewed_at' => now(),
        ]);

        // อัพเดทจำนวนเข้าชมในตาราง tours
        $tour->increment('view_count');

        // อัพเดท daily stats
        DB::table('tour_view_daily_stats')->updateOrInsert(
            ['tour_id' => $tour->id, 'date' => now()->toDateString()],
            [
                'views' => DB::raw('views + 1'),
                'unique_visitors' => DB::raw(
                    '(SELECT COUNT(DISTINCT session_id) FROM tour_views WHERE tour_id = ' . $tour->id . ' AND DATE(viewed_at) = "' . now()->toDateString() . '")'
                ),
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * สรุปสถิติการเข้าชม (สำหรับ admin)
     * GET /tours/view-stats/summary
     */
    public function viewStatsSummary(Request $request): JsonResponse
    {
        $days = (int) ($request->input('days', 30));
        $since = now()->subDays($days)->startOfDay();

        // Top viewed countries
        $topCountries = TourView::where('viewed_at', '>=', $since)
            ->whereNotNull('country_id')
            ->select('country_id', 'country_name', DB::raw('COUNT(*) as views'))
            ->groupBy('country_id', 'country_name')
            ->orderByDesc('views')
            ->limit(20)
            ->get();

        // Top viewed cities
        $topCities = DB::table('tour_views')
            ->where('viewed_at', '>=', $since)
            ->whereNotNull('city_names')
            ->selectRaw("JSON_UNQUOTE(city_name.value) as city_name, COUNT(*) as views")
            ->crossJoin(DB::raw("JSON_TABLE(city_names, '$[*]' COLUMNS(value VARCHAR(100) PATH '$')) as city_name"))
            ->groupBy('city_name')
            ->orderByDesc('views')
            ->limit(20)
            ->get();

        // Top hashtags
        $topHashtags = DB::table('tour_views')
            ->where('viewed_at', '>=', $since)
            ->whereNotNull('hashtags')
            ->selectRaw("JSON_UNQUOTE(tag.value) as hashtag, COUNT(*) as views")
            ->crossJoin(DB::raw("JSON_TABLE(hashtags, '$[*]' COLUMNS(value VARCHAR(100) PATH '$')) as tag"))
            ->groupBy('hashtag')
            ->orderByDesc('views')
            ->limit(20)
            ->get();

        // Top themes
        $topThemes = DB::table('tour_views')
            ->where('viewed_at', '>=', $since)
            ->whereNotNull('themes')
            ->selectRaw("JSON_UNQUOTE(t.value) as theme, COUNT(*) as views")
            ->crossJoin(DB::raw("JSON_TABLE(themes, '$[*]' COLUMNS(value VARCHAR(100) PATH '$')) as t"))
            ->groupBy('theme')
            ->orderByDesc('views')
            ->limit(20)
            ->get();

        // Top regions
        $topRegions = TourView::where('viewed_at', '>=', $since)
            ->whereNotNull('region')
            ->select('region', DB::raw('COUNT(*) as views'))
            ->groupBy('region')
            ->orderByDesc('views')
            ->get();

        // Top tours
        $topTours = TourView::where('viewed_at', '>=', $since)
            ->select('tour_id', DB::raw('COUNT(*) as views'), DB::raw('COUNT(DISTINCT session_id) as unique_visitors'))
            ->groupBy('tour_id')
            ->orderByDesc('views')
            ->limit(20)
            ->with('tour:id,title,slug,tour_code')
            ->get();

        // Device breakdown
        $deviceBreakdown = TourView::where('viewed_at', '>=', $since)
            ->select('device_type', DB::raw('COUNT(*) as views'))
            ->groupBy('device_type')
            ->get();

        // Promotion type breakdown
        $promoBreakdown = TourView::where('viewed_at', '>=', $since)
            ->select('promotion_type', DB::raw('COUNT(*) as views'))
            ->groupBy('promotion_type')
            ->get();

        // Duration breakdown
        $durationBreakdown = TourView::where('viewed_at', '>=', $since)
            ->whereNotNull('duration_days')
            ->select('duration_days', DB::raw('COUNT(*) as views'))
            ->groupBy('duration_days')
            ->orderBy('duration_days')
            ->get();

        // Daily trend
        $dailyTrend = DB::table('tour_view_daily_stats')
            ->where('date', '>=', $since->toDateString())
            ->select('date', DB::raw('SUM(views) as views'), DB::raw('SUM(unique_visitors) as unique_visitors'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Totals
        $totalViews = TourView::where('viewed_at', '>=', $since)->count();
        $uniqueVisitors = TourView::where('viewed_at', '>=', $since)->distinct('session_id')->count('session_id');

        return response()->json([
            'success' => true,
            'data' => [
                'period_days' => $days,
                'total_views' => $totalViews,
                'unique_visitors' => $uniqueVisitors,
                'top_countries' => $topCountries,
                'top_cities' => $topCities,
                'top_hashtags' => $topHashtags,
                'top_themes' => $topThemes,
                'top_regions' => $topRegions,
                'top_tours' => $topTours,
                'device_breakdown' => $deviceBreakdown,
                'promotion_type_breakdown' => $promoBreakdown,
                'duration_breakdown' => $durationBreakdown,
                'daily_trend' => $dailyTrend,
            ],
        ]);
    }

    /**
     * Format tour data for public display
     */
    private function formatTourDetail(Tour $tour): array
    {
        // Format periods with offers
        $periods = $tour->periods->map(function ($period) {
            $offer = $period->offer;
            return [
                'id' => $period->id,
                'start_date' => $period->start_date->format('Y-m-d'),
                'end_date' => $period->end_date->format('Y-m-d'),
                'capacity' => $period->capacity,
                'booked' => $period->booked,
                'available' => $period->available,
                'status' => $period->status,
                'sale_status' => $period->sale_status,
                'guarantee_status' => $period->guarantee_status ?? 'pending',
                'offer' => $offer ? [
                    'price_adult' => (float) $offer->price_adult,
                    'discount_adult' => (float) ($offer->discount_adult ?? 0),
                    'net_price_adult' => (float) ($offer->price_adult - ($offer->discount_adult ?? 0)),
                    'price_child' => $offer->price_child ? (float) $offer->price_child : null,
                    'discount_child_bed' => (float) ($offer->discount_child_bed ?? 0),
                    'price_child_nobed' => $offer->price_child_nobed ? (float) $offer->price_child_nobed : null,
                    'discount_child_nobed' => (float) ($offer->discount_child_nobed ?? 0),
                    'price_infant' => $offer->price_infant ? (float) $offer->price_infant : null,
                    'price_joinland' => $offer->price_joinland ? (float) $offer->price_joinland : null,
                    'price_single' => $offer->price_single ? (float) $offer->price_single : null,
                    'discount_single' => (float) ($offer->discount_single ?? 0),
                    'deposit' => $offer->deposit ? (float) $offer->deposit : null,
                    'promo_name' => $offer->promo_name,
                ] : null,
            ];
        });

        // Format itineraries
        $itineraries = $tour->itineraries
            ->sortBy('day_number')
            ->values()
            ->map(function ($item) {
                return [
                    'day_number' => $item->day_number,
                    'title' => $item->title,
                    'description' => $item->description,
                    'places' => $item->places,
                    'accommodation' => $item->accommodation,
                    'hotel_star' => $item->hotel_star,
                    'has_breakfast' => (bool) $item->has_breakfast,
                    'has_lunch' => (bool) $item->has_lunch,
                    'has_dinner' => (bool) $item->has_dinner,
                    'meals_note' => $item->meals_note,
                    'images' => $item->images,
                ];
            });

        // Format transports
        $transports = $tour->transports
            ->sortBy('sort_order')
            ->values()
            ->map(function ($t) {
                return [
                    'transport_code' => $t->transport_code,
                    'transport_name' => $t->transport_name,
                    'flight_no' => $t->flight_no,
                    'route_from' => $t->route_from,
                    'route_to' => $t->route_to,
                    'depart_time' => $t->depart_time,
                    'arrive_time' => $t->arrive_time,
                    'transport_type' => $t->transport_type,
                    'day_no' => $t->day_no,
                    'airline' => $t->transport ? [
                        'code' => $t->transport->code,
                        'name' => $t->transport->name,
                        'image' => $t->transport->image,
                    ] : null,
                ];
            });

        // Format gallery
        $gallery = $tour->gallery
            ->sortBy('sort_order')
            ->values()
            ->map(fn($img) => [
                'url' => $img->url,
                'thumbnail_url' => $img->thumbnail_url,
                'alt' => $img->alt,
                'caption' => $img->caption,
            ]);

        // Countries & cities
        $countries = $tour->countries->map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name_th ?? $c->name_en,
            'name_en' => $c->name_en,
            'iso2' => $c->iso2,
            'flag_emoji' => $c->flag_emoji,
        ]);

        $cities = $tour->cities->map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name_th ?? $c->name_en,
            'name_en' => $c->name_en,
            'country_id' => $c->pivot->country_id ?? $c->country_id,
        ]);

        // Locations
        $locations = $tour->locations->map(fn($l) => [
            'name' => $l->name,
            'name_en' => $l->name_en,
            'city' => $l->city ? ($l->city->name_th ?? $l->city->name_en) : null,
        ]);

        return [
            'id' => $tour->id,
            'slug' => $tour->slug,
            'tour_code' => $tour->tour_code,
            'title' => $tour->title,
            'tour_type' => $tour->tour_type,
            'description' => $tour->description,

            // Location
            'primary_country' => $tour->primaryCountry ? [
                'id' => $tour->primaryCountry->id,
                'name' => $tour->primaryCountry->name_th ?? $tour->primaryCountry->name_en,
                'iso2' => $tour->primaryCountry->iso2,
                'flag_emoji' => $tour->primaryCountry->flag_emoji,
            ] : null,
            'countries' => $countries,
            'cities' => $cities,
            'locations' => $locations,
            'region' => $tour->region,
            'sub_region' => $tour->sub_region,

            // Duration
            'duration_days' => $tour->duration_days,
            'duration_nights' => $tour->duration_nights,

            // Highlights
            'highlights' => $tour->highlights,
            'shopping_highlights' => $tour->shopping_highlights,
            'food_highlights' => $tour->food_highlights,
            'special_highlights' => $tour->special_highlights,

            // Hotel
            'hotel_star' => $tour->hotel_star,
            'hotel_star_min' => $tour->hotel_star_min,
            'hotel_star_max' => $tour->hotel_star_max,

            // Terms
            'inclusions' => $tour->inclusions,
            'exclusions' => $tour->exclusions,
            'conditions' => $tour->conditions,

            // Media
            'cover_image_url' => $tour->cover_image_url,
            'cover_image_alt' => $tour->cover_image_alt,
            'gallery' => $gallery,
            'pdf_url' => $tour->pdf_url,

            // Tags & classification
            'hashtags' => $tour->hashtags,
            'themes' => $tour->themes,
            'suitable_for' => $tour->suitable_for,
            'keywords' => $tour->keywords,
            'badge' => $tour->badge,
            'tour_category' => $tour->tour_category,

            // Pricing (aggregated)
            'min_price' => $tour->min_price ? (float) $tour->min_price : null,
            'display_price' => $tour->display_price ? (float) $tour->display_price : null,
            'price_adult' => $tour->price_adult ? (float) $tour->price_adult : null,
            'discount_adult' => $tour->discount_adult ? (float) $tour->discount_adult : null,
            'discount_amount' => $tour->discount_amount ? (float) $tour->discount_amount : null,
            'max_discount_percent' => $tour->max_discount_percent ? (float) $tour->max_discount_percent : null,
            'promotion_type' => $tour->promotion_type,
            'discount_label' => $tour->discount_label,

            // Departures & transport
            'departure_airports' => $tour->departure_airports,
            'transports' => $transports,
            'next_departure_date' => $tour->next_departure_date?->format('Y-m-d'),
            'total_departures' => $tour->total_departures,
            'available_seats' => $tour->available_seats,

            // Periods with offers
            'periods' => $periods,

            // Itinerary
            'itineraries' => $itineraries,

            // Stats
            'view_count' => $tour->view_count ?? 0,
            'popularity_score' => $tour->popularity_score ?? 0,

            // SEO
            'meta_title' => $tour->meta_title,
            'meta_description' => $tour->meta_description,
        ];
    }
}
