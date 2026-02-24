<?php

namespace App\Http\Controllers;

use App\Models\GalleryImage;
use App\Models\GalleryVideo;
use App\Models\Tour;
use App\Models\TourView;
use App\Models\InternationalTourSetting;
use App\Models\DomesticTourSetting;
use App\Models\Country;
use App\Models\City;
use App\Models\Transport;
use App\Models\FestivalHoliday;
use App\Services\PointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicTourController extends Controller
{
    /**
     * Ensure a value is an array (handles double-encoded JSON strings)
     */
    private function ensureArray(mixed $value): array
    {
        if (is_array($value)) return $value;
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /**
     * เนเธชเธ”เธเธเนเธญเธกเธนเธฅเธ—เธฑเธงเธฃเนเธชเธณเธซเธฃเธฑเธ public (เนเธกเนเธ•เนเธญเธ auth)
     * GET /tours/{slug}
     */
    public function show(string $slug): JsonResponse
    {
        $tour = Tour::where('slug', $slug)
            ->where('status', 'active')
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
                          ->with('offer.promotion');
                },
            ])
            ->first();

        if (!$tour) {
            return response()->json([
                'success' => false,
                'message' => 'เนเธกเนเธเธเธ—เธฑเธงเธฃเนเธ—เธตเนเธ•เนเธญเธเธเธฒเธฃ',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatTourDetail($tour),
        ]);
    }

    /**
     * เธเธฑเธเธ—เธถเธเธชเธ–เธดเธ•เธดเธเธฒเธฃเน€เธเนเธฒเธเธก
     * POST /tours/{slug}/view
     */
    public function recordView(Request $request, string $slug): JsonResponse
    {
        $tour = Tour::where('slug', $slug)
            ->where('status', 'active')
            ->with(['primaryCountry:id,name_th', 'cities:id,name_th'])
            ->first();

        if (!$tour) {
            return response()->json(['success' => false], 404);
        }

        $userAgent = $request->userAgent();
        $sessionId = $request->input('session_id') ?: $request->ip() . '_' . substr(md5($userAgent ?? ''), 0, 8);

        // เธเนเธญเธเธเธฑเธเธเธฑเธเธเนเธณ โ€” เธ–เนเธฒ session เน€เธ”เธตเธขเธงเธเธฑเธเธ”เธนเธ—เธฑเธงเธฃเนเน€เธ”เธตเธขเธงเธเธฑเธเธ เธฒเธขเนเธ 30 เธเธฒเธ—เธต เนเธกเนเธเธฑเธ
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
            'hashtags' => $this->ensureArray($tour->hashtags),
            'themes' => $this->ensureArray($tour->themes),
            'region' => $tour->region,
            'sub_region' => $tour->sub_region,
            'price' => $tour->min_price ?? $tour->display_price,
            'duration_days' => $tour->duration_days,
            'referrer' => $request->input('referrer'),
            'utm_source' => $request->input('utm_source'),
            'utm_medium' => $request->input('utm_medium'),
            'utm_campaign' => $request->input('utm_campaign'),
            'device_type' => TourView::detectDeviceType($userAgent),
            'viewed_at' => now(),
        ]);

        // เธญเธฑเธเน€เธ”เธ—เธเธณเธเธงเธเน€เธเนเธฒเธเธกเนเธเธ•เธฒเธฃเธฒเธ tours
        $tour->increment('view_count');

        // เนเธซเนเธเธฐเนเธเธเธชเธกเธฒเธเธดเธเธ—เธตเนเธฅเนเธญเธเธญเธดเธเนเธฅเนเธง
        if ($member = $request->user()) {
            try {
                app(PointService::class)->earnPoints(
                    $member, 'page_view', 0, Tour::class, $tour->id,
                    "เธ”เธนเธ—เธฑเธงเธฃเน: {$tour->title}"
                );
            } catch (\Throwable $e) {
                // Silent fail โ€” don't break page view recording
            }
        }

        // เธญเธฑเธเน€เธ”เธ— daily stats
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
     * เธชเธฃเธธเธเธชเธ–เธดเธ•เธดเธเธฒเธฃเน€เธเนเธฒเธเธก (เธชเธณเธซเธฃเธฑเธ admin)
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
                    'promo_name' => $offer->promo_name ?? $offer->promotion?->name,
                    'promo_start_date' => $offer->promo_start_date?->format('Y-m-d'),
                    'promo_end_date' => $offer->promo_end_date?->format('Y-m-d'),
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
            'iso2' => strtolower($c->iso2 ?? ''),
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
                'iso2' => strtolower($tour->primaryCountry->iso2 ?? ''),
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
            'highlights' => $this->ensureArray($tour->highlights),
            'shopping_highlights' => $this->ensureArray($tour->shopping_highlights),
            'food_highlights' => $this->ensureArray($tour->food_highlights),
            'special_highlights' => $this->ensureArray($tour->special_highlights),

            // Hotel
            'hotel_star' => $tour->hotel_star,
            'hotel_star_min' => $tour->hotel_star_min,
            'hotel_star_max' => $tour->hotel_star_max,

            // Terms
            'inclusions' => $tour->inclusions,
            'exclusions' => $tour->exclusions,
            'conditions' => $tour->conditions,

            // Media (effective = custom override or API source)
            'cover_image_url' => $tour->effective_cover_image_url,
            'cover_image_alt' => $tour->effective_cover_image_alt,
            'gallery' => $gallery,
            'gallery_images' => $this->getGalleryImagesForTour($tour),
            'gallery_videos' => $this->getGalleryVideosForTour($tour),
            'pdf_url' => $tour->effective_pdf_url,

            // Tags & classification
            'hashtags' => $this->ensureArray($tour->hashtags),
            'themes' => $this->ensureArray($tour->themes),
            'suitable_for' => $this->ensureArray($tour->suitable_for),
            'keywords' => $this->ensureArray($tour->keywords),
            'badge' => $tour->badge,

            // Pricing (aggregated)
            'min_price' => $tour->min_price ? (float) $tour->min_price : null,
            'display_price' => $tour->display_price ? (float) $tour->display_price : null,
            'price_adult' => $tour->price_adult ? (float) $tour->price_adult : null,
            'discount_adult' => $tour->discount_adult ? (float) $tour->discount_adult : null,
            'discount_amount' => $tour->discount_amount ? (float) $tour->discount_amount : null,
            'max_discount_percent' => $tour->max_discount_percent ? (float) $tour->max_discount_percent : null,
            'discount_label' => $tour->discount_label,

            // Departures & transport
            'departure_airports' => $this->ensureArray($tour->departure_airports),
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

    /**
     * Get gallery images matching tour's hashtags, city names, or country name
     * Random images from GalleryImage table where tags match
     */
    private function getGalleryImagesForTour(Tour $tour): array
    {
        $hashtags = $this->ensureArray($tour->hashtags);
        $cityNames = $tour->cities->pluck('name_th')->filter()->values()->toArray();
        $countryName = $tour->primaryCountry?->name_th;

        $allTags = array_values(array_unique(array_filter(
            array_merge($hashtags, $cityNames, $countryName ? [$countryName] : [])
        )));

        if (empty($allTags)) {
            return [];
        }

        $images = GalleryImage::active()
            ->byTags($allTags)
            ->inRandomOrder()
            ->limit(6)
            ->get();

        return $images->map(fn($img) => [
            'url' => $img->url,
            'thumbnail_url' => $img->thumbnail_url,
            'alt' => $img->alt,
            'caption' => $img->caption,
        ])->values()->toArray();
    }

    /**
     * Get gallery videos matching tour's hashtags, city names, or country name
     * Random videos from GalleryVideo table where tags match, limit 3
     */
    private function getGalleryVideosForTour(Tour $tour): array
    {
        // Collect all possible matching tags: hashtags + city names + country name
        $hashtags = $this->ensureArray($tour->hashtags);
        $cityNames = $tour->cities->pluck('name_th')->filter()->values()->toArray();
        $countryName = $tour->primaryCountry?->name_th;

        $allTags = array_values(array_unique(array_filter(
            array_merge($hashtags, $cityNames, $countryName ? [$countryName] : [])
        )));

        if (empty($allTags)) {
            return [];
        }

        $videos = GalleryVideo::active()
            ->byTags($allTags)
            ->inRandomOrder()
            ->limit(3)
            ->get();

        return $videos->map(fn($v) => [
            'id' => $v->id,
            'video_url' => $v->video_url,
            'thumbnail_url' => $v->thumbnail_url,
            'title' => $v->title,
            'description' => $v->description,
        ])->values()->toArray();
    }

    /**
     * เน€เธกเธเธนเธ—เธฑเธงเธฃเนเธ•เนเธฒเธเธเธฃเธฐเน€เธ—เธจ - เนเธชเธ”เธเธเธฃเธฐเน€เธ—เธจ+เน€เธกเธทเธญเธเธ—เธตเนเธกเธตเธ—เธฑเธงเธฃเน เธเธฑเธ”เธเธฅเธธเนเธกเธ•เธฒเธกเธ—เธงเธตเธ
     * เน€เธเธทเนเธญเธเนเธ: เธ—เธฑเธงเธฃเน status=active + เธกเธต period เธ—เธตเน start_date >= เธงเธฑเธเธเธตเน & status=open
     * GET /tours/international-menu
     */
    public function internationalMenu(): JsonResponse
    {
        $today = now()->toDateString();
        $thailandId = \App\Models\Country::where('slug', 'thailand')->value('id');

        // Sub-query: tour IDs เธ—เธตเน active + เธกเธตเธฃเธญเธเน€เธ”เธดเธเธ—เธฒเธเนเธเธญเธเธฒเธเธ•
        $activeTourIds = Tour::where('status', 'active')
            ->whereHas('periods', function ($q) use ($today) {
                $q->where('status', 'open')
                  ->where('start_date', '>=', $today);
            })
            ->pluck('id');

        if ($activeTourIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        // เธ”เธถเธเธเธฃเธฐเน€เธ—เธจ (เนเธกเนเธฃเธงเธกเนเธ—เธข) เธ—เธตเนเธกเธตเธ—เธฑเธงเธฃเน active เธเนเธฒเธ tour_countries pivot
        $countries = \App\Models\Country::active()
            ->when($thailandId, fn($q) => $q->where('id', '!=', $thailandId))
            ->whereHas('tours', function ($q) use ($activeTourIds) {
                $q->whereIn('tours.id', $activeTourIds);
            })
            ->withCount(['tours' => function ($q) use ($activeTourIds) {
                $q->whereIn('tours.id', $activeTourIds);
            }])
            ->with(['cities' => function ($q) use ($activeTourIds) {
                $q->active()
                  ->whereHas('tours', function ($q2) use ($activeTourIds) {
                      $q2->whereIn('tours.id', $activeTourIds);
                  })
                  ->withCount(['tours' => function ($q2) use ($activeTourIds) {
                      $q2->whereIn('tours.id', $activeTourIds);
                  }])
                  ->orderBy('name_th');
            }])
            ->orderBy('name_th')
            ->get();

        // เนเธเธฅเธเน€เธเนเธ flat array เน€เธฃเธตเธขเธเธ•เธฒเธกเธเธณเธเธงเธเธ—เธฑเธงเธฃเนเธกเธฒเธเธชเธธเธ” + เน€เธกเธทเธญเธเธกเธฒเธเธชเธธเธ”
        $result = $countries->map(function ($country) {
            return [
                'id' => $country->id,
                'name_th' => $country->name_th,
                'name_en' => $country->name_en,
                'slug' => $country->slug,
                'iso2' => strtolower($country->iso2 ?? ''),
                'flag_emoji' => $country->flag_emoji,
                'tour_count' => $country->tours_count,
                'cities' => $country->cities->map(fn($city) => [
                    'id' => $city->id,
                    'name_th' => $city->name_th,
                    'name_en' => $city->name_en,
                    'slug' => $city->slug,
                    'tour_count' => $city->tours_count,
                ])->values(),
            ];
        })->sortByDesc('tour_count')->sortByDesc(fn($c) => count($c['cities']))->values();

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * เธฃเธฒเธขเธเธฒเธฃเธ—เธฑเธงเธฃเนเธ•เนเธฒเธเธเธฃเธฐเน€เธ—เธจ - เธเธฃเนเธญเธก filter, pagination, periods
     * GET /tours/international
     */
    public function internationalTours(Request $request): JsonResponse
    {
        // Get active setting or use defaults
        $setting = InternationalTourSetting::active()
            ->with('countryCovers')
            ->orderBy('sort_order')
            ->first();
        
        if (!$setting) {
            $setting = new InternationalTourSetting([
                'conditions' => [],
                'sort_by' => 'popular',
                'display_limit' => 50,
                'per_page' => 10,
                'max_periods_display' => 6,
            ]);
        }

        // Collect user filters from query params
        // Resolve slug-based filters to IDs
        $countryId = $request->input('country_id');
        $cityId = $request->input('city_id');

        if (!$countryId && $request->input('country_slug')) {
            $countryId = Country::where('slug', $request->input('country_slug'))->value('id');
        }
        if (!$cityId && $request->input('city_slug')) {
            $cityId = City::where('slug', $request->input('city_slug'))->value('id');
        }

        // Get country-specific cover if a country filter is applied
        $countryCover = null;
        if ($countryId && $setting->countryCovers) {
            $countryCover = $setting->countryCovers->firstWhere('country_id', (int)$countryId);
        }

        $filters = [
            'country_id' => $countryId,
            'city_id' => $cityId,
            'search' => $request->input('search'),
            'airline_id' => $request->input('airline_id'),
            'departure_month' => $request->input('departure_month'),
            'departure_date_from' => $request->input('departure_date_from'),
            'departure_date_to' => $request->input('departure_date_to'),
            'return_date' => $request->input('return_date'),
            'price_min' => $request->input('price_min'),
            'price_max' => $request->input('price_max'),
            'min_seats' => $request->input('min_seats'),
            'sort_by' => $request->input('sort_by'),
            'festival_id' => $request->input('festival_id'),
        ];

        $perPage = $request->input('per_page', $setting->per_page);
        $tours = $setting->getTours($perPage, $filters);

        // Format response
        $formattedTours = collect($tours->items())->map(function ($tour) use ($setting) {
            return $this->formatTourListItem($tour, $setting);
        });

        // Get filter options
        $filterOptions = $this->getInternationalFilterOptions($setting);

        return response()->json([
            'success' => true,
            'data' => $formattedTours,
            'meta' => [
                'current_page' => $tours->currentPage(),
                'last_page' => $tours->lastPage(),
                'per_page' => $tours->perPage(),
                'total' => $tours->total(),
            ],
            'filters' => $filterOptions,
            'settings' => [
                'show_periods' => $setting->show_periods,
                'max_periods_display' => $setting->max_periods_display,
                'show_transport' => $setting->show_transport,
                'show_hotel_star' => $setting->show_hotel_star,
                'show_meal_count' => $setting->show_meal_count,
                'show_commission' => $setting->show_commission,
                'filter_country' => $setting->filter_country ?? true,
                'filter_city' => $setting->filter_city ?? true,
                'filter_search' => $setting->filter_search ?? true,
                'filter_airline' => $setting->filter_airline ?? true,
                'filter_departure_month' => $setting->filter_departure_month ?? true,
                'filter_price_range' => $setting->filter_price_range ?? true,
                'sort_options' => InternationalTourSetting::SORT_OPTIONS,
                // Use country-specific cover if available, otherwise use default cover
                'cover_image_url' => $countryCover?->image_url ?? $setting->cover_image_url,
                'cover_image_position' => $countryCover?->image_position ?? $setting->cover_image_position ?? 'center',
            ],
            'active_filters' => [
                'country' => $countryId ? Country::find($countryId, ['id', 'name_th', 'name_en', 'slug', 'iso2']) : null,
                'city' => $cityId ? City::find($cityId, ['id', 'name_th', 'name_en', 'slug', 'country_id']) : null,
            ],
        ]);
    }

    /**
     * Format a tour for the listing page
     */
    private function formatTourListItem(Tour $tour, InternationalTourSetting $setting): array
    {
        $item = [
            'id' => $tour->id,
            'slug' => $tour->slug,
            'tour_code' => $tour->tour_code,
            'title' => $tour->title,
            'tour_type' => $tour->tour_type,
            'description' => $tour->description,
            'cover_image_url' => $tour->effective_cover_image_url,
            'cover_image_alt' => $tour->effective_cover_image_alt,
            'duration_days' => $tour->duration_days,
            'duration_nights' => $tour->duration_nights,
            'min_price' => $tour->min_price,
            'display_price' => $tour->display_price,
            'price_adult' => $tour->price_adult,
            'discount_adult' => $tour->discount_adult,
            'discount_amount' => $tour->discount_amount,
            'max_discount_percent' => $tour->max_discount_percent,
            'discount_label' => $tour->discount_label,
            'badge' => $tour->badge,
            'available_seats' => $tour->available_seats,
            'next_departure_date' => $tour->next_departure_date,
            'total_departures' => $tour->total_departures,
            'pdf_url' => $tour->effective_pdf_url,
            'highlights' => $this->ensureArray($tour->highlights),
            'shopping_highlights' => $this->ensureArray($tour->shopping_highlights),
            'food_highlights' => $this->ensureArray($tour->food_highlights),
            'hashtags' => $this->ensureArray($tour->hashtags),
            'departure_airports' => $this->ensureArray($tour->departure_airports),
            'country' => $tour->primaryCountry ? [
                'id' => $tour->primaryCountry->id,
                'name_th' => $tour->primaryCountry->name_th,
                'iso2' => strtolower($tour->primaryCountry->iso2 ?? ''),
            ] : null,
            'cities' => $tour->cities->map(fn($city) => [
                'id' => $city->id,
                'name_th' => $city->name_th,
                'slug' => $city->slug,
            ])->values(),
        ];

        // Hotel stars
        if ($setting->show_hotel_star) {
            $item['hotel_star'] = $tour->hotel_star;
            $item['hotel_star_min'] = $tour->hotel_star_min;
            $item['hotel_star_max'] = $tour->hotel_star_max;
        }

        // Meal count (computed from itineraries)
        if ($setting->show_meal_count) {
            $breakfasts = $tour->itineraries->where('has_breakfast', true)->count();
            $lunches = $tour->itineraries->where('has_lunch', true)->count();
            $dinners = $tour->itineraries->where('has_dinner', true)->count();
            $item['meal_count'] = [
                'breakfast' => $breakfasts,
                'lunch' => $lunches,
                'dinner' => $dinners,
                'total' => $breakfasts + $lunches + $dinners,
            ];
        }

        // Transport / Airlines
        if ($setting->show_transport) {
            $item['transports'] = $tour->transports->map(fn($t) => [
                'flight_no' => $t->flight_no,
                'route_from' => $t->route_from,
                'route_to' => $t->route_to,
                'depart_time' => $t->depart_time ? $t->depart_time->format('H:i') : null,
                'arrive_time' => $t->arrive_time ? $t->arrive_time->format('H:i') : null,
                'transport_type' => $t->transport_type,
                'airline' => $t->transport ? [
                    'code' => $t->transport->code,
                    'name' => $t->transport->name,
                    'image' => $t->transport->image,
                ] : null,
            ])->values();
        }

        // Periods with offers
        if ($setting->show_periods) {
            $item['periods'] = $tour->periods->map(function ($period) use ($setting) {
                $periodData = [
                    'id' => $period->id,
                    'start_date' => $period->start_date?->format('Y-m-d'),
                    'end_date' => $period->end_date?->format('Y-m-d'),
                    'capacity' => $period->capacity,
                    'booked' => $period->booked,
                    'available' => $period->available,
                    'status' => $period->status,
                    'sale_status' => $period->sale_status,
                    'guarantee_status' => $period->guarantee_status ?? 'pending',
                ];

                if ($period->offer) {
                    $offer = $period->offer;
                    $periodData['offer'] = [
                        'price_adult' => (float) $offer->price_adult,
                        'discount_adult' => (float) $offer->discount_adult,
                        'net_price_adult' => (float) ($offer->price_adult - $offer->discount_adult),
                        'price_child' => $offer->price_child ? (float) $offer->price_child : null,
                        'price_child_nobed' => $offer->price_child_nobed ? (float) $offer->price_child_nobed : null,
                        'price_infant' => $offer->price_infant ? (float) $offer->price_infant : null,
                        'price_joinland' => $offer->price_joinland ? (float) $offer->price_joinland : null,
                        'price_single' => $offer->price_single ? (float) $offer->price_single : null,
                        'discount_single' => (float) ($offer->discount_single ?? 0),
                        'net_price_single' => $offer->price_single ? (float) ($offer->price_single - ($offer->discount_single ?? 0)) : null,
                        'deposit' => $offer->deposit ? (float) $offer->deposit : null,
                        'promo_name' => $offer->promo_name ?? $offer->promotion?->name,
                        'promo_start_date' => $offer->promo_start_date?->format('Y-m-d'),
                        'promo_end_date' => $offer->promo_end_date?->format('Y-m-d'),
                    ];

                    if ($setting->show_commission) {
                        $periodData['offer']['commission_agent'] = $offer->commission_agent;
                        $periodData['offer']['commission_sale'] = $offer->commission_sale;
                    }
                } else {
                    $periodData['offer'] = null;
                }

                return $periodData;
            })->values();
        }

        // Collect active promotions from offers for badge display on tour card
        $today = now()->toDateString();
        $activePromos = $tour->periods
            ->filter(fn($p) => $p->offer && ($p->offer->promo_name || $p->offer->promotion))
            ->map(function ($p) use ($today) {
                $offer = $p->offer;
                $name = $offer->promo_name ?? $offer->promotion?->name;
                if (!$name) return null;
                $start = $offer->promo_start_date?->format('Y-m-d');
                $end = $offer->promo_end_date?->format('Y-m-d');
                if ($start && $today < $start) return null;
                if ($end && $today > $end) return null;
                return [
                    'name' => $name,
                    'start_date' => $start,
                    'end_date' => $end,
                ];
            })
            ->filter()
            ->unique('name')
            ->values();
        if ($activePromos->isNotEmpty()) {
            $item['active_promotions'] = $activePromos;
        }

        return $item;
    }

    /**
     * Get filter options for the international tours page
     */
    private function getInternationalFilterOptions(InternationalTourSetting $setting): array
    {
        $today = now()->toDateString();
        $thailandId = Country::where('slug', 'thailand')->value('id') ?? 8;

        // Active international tour IDs
        $activeTourIds = Tour::where('status', 'active')
            ->where(function ($q) use ($thailandId) {
                $q->where('primary_country_id', '!=', $thailandId)
                  ->orWhereNull('primary_country_id');
            })
            ->whereHas('periods', fn($q) => $q->where('status', 'open')->where('start_date', '>=', $today))
            ->pluck('id');

        $filters = [];

        // Countries
        if ($setting->filter_country ?? true) {
            $filters['countries'] = Country::active()
                ->where('id', '!=', $thailandId)
                ->whereHas('tours', fn($q) => $q->whereIn('tours.id', $activeTourIds))
                ->withCount(['tours' => fn($q) => $q->whereIn('tours.id', $activeTourIds)])
                ->orderBy('name_th')
                ->get()
                ->map(fn($c) => [
                    'id' => $c->id,
                    'name_th' => $c->name_th,
                    'slug' => $c->slug,
                    'iso2' => strtolower($c->iso2 ?? ''),
                    'tour_count' => $c->tours_count,
                ]);
        }

        // Cities (grouped by country)
        if ($setting->filter_city ?? true) {
            $filters['cities'] = City::active()
                ->whereHas('tours', fn($q) => $q->whereIn('tours.id', $activeTourIds))
                ->withCount(['tours' => fn($q) => $q->whereIn('tours.id', $activeTourIds)])
                ->with('country:id,name_th')
                ->orderBy('name_th')
                ->get()
                ->map(fn($c) => [
                    'id' => $c->id,
                    'name_th' => $c->name_th,
                    'country_id' => $c->country_id,
                    'country_name' => $c->country?->name_th,
                    'tour_count' => $c->tours_count,
                ]);
        }

        // Airlines
        if ($setting->filter_airline ?? true) {
            $airlineIds = DB::table('tour_transports')
                ->whereIn('tour_id', $activeTourIds)
                ->whereNotNull('transport_id')
                ->distinct()
                ->pluck('transport_id');

            $filters['airlines'] = Transport::whereIn('id', $airlineIds)
                ->active()
                ->orderBy('name')
                ->get()
                ->map(fn($t) => [
                    'id' => $t->id,
                    'code' => $t->code,
                    'name' => $t->name,
                    'image' => $t->image,
                ]);
        }

        // Departure months
        if ($setting->filter_departure_month ?? true) {
            $filters['departure_months'] = DB::table('periods')
                ->join('tours', 'tours.id', '=', 'periods.tour_id')
                ->whereIn('tours.id', $activeTourIds)
                ->where('periods.status', 'open')
                ->where('periods.start_date', '>=', $today)
                ->selectRaw("DISTINCT DATE_FORMAT(periods.start_date, '%Y-%m') as month")
                ->orderBy('month')
                ->pluck('month')
                ->map(fn($m) => [
                    'value' => $m,
                    'label' => $this->formatThaiMonth($m),
                ]);
        }

        // Festivals (upcoming, active)
        $filters['festivals'] = FestivalHoliday::where('is_active', true)
            ->where('end_date', '>=', $today)
            ->orderBy('start_date')
            ->take(8)
            ->get()
            ->map(fn ($f) => [
                'id'          => $f->id,
                'name'        => $f->name,
                'slug'        => $f->slug,
                'badge_text'  => $f->badge_text,
                'badge_color' => $f->badge_color,
                'badge_icon'  => $f->badge_icon,
                'start_date'  => optional($f->start_date)->toDateString(),
                'end_date'    => optional($f->end_date)->toDateString(),
            ]);

        return $filters;
    }

    // ===================== Domestic Tours =====================

    /**
     * เน€เธกเธเธนเธ—เธฑเธงเธฃเนเนเธเธเธฃเธฐเน€เธ—เธจ - เธเธฑเธเธซเธงเธฑเธ”/เน€เธกเธทเธญเธเธ—เธตเนเธกเธตเธ—เธฑเธงเธฃเน
     * GET /tours/domestic-menu
     */
    public function domesticMenu(): JsonResponse
    {
        $today = now()->toDateString();
        $thailandId = DomesticTourSetting::THAILAND_ID;

        // Active domestic tour IDs
        $activeTourIds = Tour::where('status', 'active')
            ->where('primary_country_id', $thailandId)
            ->whereHas('periods', function ($q) use ($today) {
                $q->where('status', 'open')
                  ->where('start_date', '>=', $today);
            })
            ->pluck('id');

        if ($activeTourIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        // เธ”เธถเธเน€เธกเธทเธญเธเนเธเนเธ—เธขเธ—เธตเนเธกเธตเธ—เธฑเธงเธฃเน active
        $cities = City::active()
            ->where('country_id', $thailandId)
            ->whereHas('tours', function ($q) use ($activeTourIds) {
                $q->whereIn('tours.id', $activeTourIds);
            })
            ->withCount(['tours' => function ($q) use ($activeTourIds) {
                $q->whereIn('tours.id', $activeTourIds);
            }])
            ->orderBy('name_th')
            ->get()
            ->map(fn($city) => [
                'id' => $city->id,
                'name_th' => $city->name_th,
                'name_en' => $city->name_en,
                'slug' => $city->slug,
                'tour_count' => $city->tours_count,
            ])
            ->sortByDesc('tour_count')
            ->values();

        return response()->json([
            'success' => true,
            'data' => $cities,
            'total_tours' => $activeTourIds->count(),
        ]);
    }

    /**
     * เธฃเธฒเธขเธเธฒเธฃเธ—เธฑเธงเธฃเนเนเธเธเธฃเธฐเน€เธ—เธจ (เนเธ—เธข) โ€” เธเธฃเนเธญเธก filter, pagination, periods
     * GET /tours/domestic
     */
    public function domesticTours(Request $request): JsonResponse
    {
        // Get active setting or use defaults
        $setting = DomesticTourSetting::active()
            ->with('cityCovers')
            ->orderBy('sort_order')
            ->first();

        if (!$setting) {
            $setting = new DomesticTourSetting([
                'conditions' => [],
                'sort_by' => 'popular',
                'display_limit' => 50,
                'per_page' => 10,
                'max_periods_display' => 6,
            ]);
        }

        $cityId = $request->input('city_id');
        if (!$cityId && $request->input('city_slug')) {
            $cityId = City::where('slug', $request->input('city_slug'))->value('id');
        }

        // Get city-specific cover if a city filter is applied
        $cityCover = null;
        if ($cityId && $setting->cityCovers) {
            $cityCover = $setting->cityCovers->firstWhere('city_id', (int)$cityId);
        }

        $filters = [
            'city_id' => $cityId,
            'search' => $request->input('search'),
            'airline_id' => $request->input('airline_id'),
            'departure_month' => $request->input('departure_month'),
            'departure_date_from' => $request->input('departure_date_from'),
            'departure_date_to' => $request->input('departure_date_to'),
            'return_date' => $request->input('return_date'),
            'price_min' => $request->input('price_min'),
            'price_max' => $request->input('price_max'),
            'min_seats' => $request->input('min_seats'),
            'sort_by' => $request->input('sort_by'),
        ];

        $perPage = $request->input('per_page', $setting->per_page);
        $tours = $setting->getTours($perPage, $filters);

        // Format response - reuse the same formatter
        $formattedTours = collect($tours->items())->map(function ($tour) use ($setting) {
            return $this->formatDomesticTourListItem($tour, $setting);
        });

        // Get filter options
        $filterOptions = $this->getDomesticFilterOptions($setting);

        return response()->json([
            'success' => true,
            'data' => $formattedTours,
            'meta' => [
                'current_page' => $tours->currentPage(),
                'last_page' => $tours->lastPage(),
                'per_page' => $tours->perPage(),
                'total' => $tours->total(),
            ],
            'filters' => $filterOptions,
            'settings' => [
                'show_periods' => $setting->show_periods,
                'max_periods_display' => $setting->max_periods_display,
                'show_transport' => $setting->show_transport,
                'show_hotel_star' => $setting->show_hotel_star,
                'show_meal_count' => $setting->show_meal_count,
                'show_commission' => $setting->show_commission,
                'filter_search' => $setting->filter_search ?? true,
                'filter_city' => $setting->filter_city ?? true,
                'filter_airline' => $setting->filter_airline ?? true,
                'filter_departure_month' => $setting->filter_departure_month ?? true,
                'filter_price_range' => $setting->filter_price_range ?? true,
                'sort_options' => DomesticTourSetting::SORT_OPTIONS,
                // Use city-specific cover if available, otherwise use default cover
                'cover_image_url' => $cityCover?->image_url ?? $setting->cover_image_url,
                'cover_image_position' => $cityCover?->image_position ?? $setting->cover_image_position ?? 'center',
            ],
            'active_filters' => [
                'city' => $cityId ? City::find($cityId, ['id', 'name_th', 'name_en', 'slug', 'country_id']) : null,
            ],
        ]);
    }

    /**
     * Format a domestic tour for the listing page
     */
    private function formatDomesticTourListItem(Tour $tour, DomesticTourSetting $setting): array
    {
        $item = [
            'id' => $tour->id,
            'slug' => $tour->slug,
            'tour_code' => $tour->tour_code,
            'title' => $tour->title,
            'tour_type' => $tour->tour_type,
            'description' => $tour->description,
            'cover_image_url' => $tour->effective_cover_image_url,
            'cover_image_alt' => $tour->effective_cover_image_alt,
            'duration_days' => $tour->duration_days,
            'duration_nights' => $tour->duration_nights,
            'min_price' => $tour->min_price,
            'display_price' => $tour->display_price,
            'price_adult' => $tour->price_adult,
            'discount_adult' => $tour->discount_adult,
            'discount_amount' => $tour->discount_amount,
            'max_discount_percent' => $tour->max_discount_percent,
            'discount_label' => $tour->discount_label,
            'badge' => $tour->badge,
            'available_seats' => $tour->available_seats,
            'next_departure_date' => $tour->next_departure_date,
            'total_departures' => $tour->total_departures,
            'pdf_url' => $tour->effective_pdf_url,
            'highlights' => $this->ensureArray($tour->highlights),
            'shopping_highlights' => $this->ensureArray($tour->shopping_highlights),
            'food_highlights' => $this->ensureArray($tour->food_highlights),
            'hashtags' => $this->ensureArray($tour->hashtags),
            'departure_airports' => $this->ensureArray($tour->departure_airports),
            'country' => $tour->primaryCountry ? [
                'id' => $tour->primaryCountry->id,
                'name_th' => $tour->primaryCountry->name_th,
                'iso2' => strtolower($tour->primaryCountry->iso2 ?? ''),
            ] : null,
            'cities' => $tour->cities->map(fn($city) => [
                'id' => $city->id,
                'name_th' => $city->name_th,
                'slug' => $city->slug,
            ])->values(),
        ];

        if ($setting->show_hotel_star) {
            $item['hotel_star'] = $tour->hotel_star;
            $item['hotel_star_min'] = $tour->hotel_star_min;
            $item['hotel_star_max'] = $tour->hotel_star_max;
        }

        if ($setting->show_meal_count) {
            $breakfasts = $tour->itineraries->where('has_breakfast', true)->count();
            $lunches = $tour->itineraries->where('has_lunch', true)->count();
            $dinners = $tour->itineraries->where('has_dinner', true)->count();
            $item['meal_count'] = [
                'breakfast' => $breakfasts,
                'lunch' => $lunches,
                'dinner' => $dinners,
                'total' => $breakfasts + $lunches + $dinners,
            ];
        }

        if ($setting->show_transport) {
            $item['transports'] = $tour->transports->map(fn($t) => [
                'flight_no' => $t->flight_no,
                'route_from' => $t->route_from,
                'route_to' => $t->route_to,
                'depart_time' => $t->depart_time ? $t->depart_time->format('H:i') : null,
                'arrive_time' => $t->arrive_time ? $t->arrive_time->format('H:i') : null,
                'transport_type' => $t->transport_type,
                'airline' => $t->transport ? [
                    'code' => $t->transport->code,
                    'name' => $t->transport->name,
                    'image' => $t->transport->image,
                ] : null,
            ])->values();
        }

        if ($setting->show_periods) {
            $item['periods'] = $tour->periods->map(function ($period) use ($setting) {
                $periodData = [
                    'id' => $period->id,
                    'start_date' => $period->start_date?->format('Y-m-d'),
                    'end_date' => $period->end_date?->format('Y-m-d'),
                    'capacity' => $period->capacity,
                    'booked' => $period->booked,
                    'available' => $period->available,
                    'status' => $period->status,
                    'sale_status' => $period->sale_status,
                    'guarantee_status' => $period->guarantee_status ?? 'pending',
                ];

                if ($period->offer) {
                    $offer = $period->offer;
                    $periodData['offer'] = [
                        'price_adult' => (float) $offer->price_adult,
                        'discount_adult' => (float) $offer->discount_adult,
                        'net_price_adult' => (float) ($offer->price_adult - $offer->discount_adult),
                        'price_child' => $offer->price_child ? (float) $offer->price_child : null,
                        'price_child_nobed' => $offer->price_child_nobed ? (float) $offer->price_child_nobed : null,
                        'price_infant' => $offer->price_infant ? (float) $offer->price_infant : null,
                        'price_joinland' => $offer->price_joinland ? (float) $offer->price_joinland : null,
                        'price_single' => $offer->price_single ? (float) $offer->price_single : null,
                        'discount_single' => (float) ($offer->discount_single ?? 0),
                        'net_price_single' => $offer->price_single ? (float) ($offer->price_single - ($offer->discount_single ?? 0)) : null,
                        'deposit' => $offer->deposit ? (float) $offer->deposit : null,
                        'promo_name' => $offer->promo_name ?? $offer->promotion?->name,
                        'promo_start_date' => $offer->promo_start_date?->format('Y-m-d'),
                        'promo_end_date' => $offer->promo_end_date?->format('Y-m-d'),
                    ];

                    if ($setting->show_commission) {
                        $periodData['offer']['commission_agent'] = $offer->commission_agent;
                        $periodData['offer']['commission_sale'] = $offer->commission_sale;
                    }
                } else {
                    $periodData['offer'] = null;
                }

                return $periodData;
            })->values();
        }

        // Collect active promotions from offers for badge display on tour card
        $today = now()->toDateString();
        $activePromos = $tour->periods
            ->filter(fn($p) => $p->offer && ($p->offer->promo_name || $p->offer->promotion))
            ->map(function ($p) use ($today) {
                $offer = $p->offer;
                $name = $offer->promo_name ?? $offer->promotion?->name;
                if (!$name) return null;
                $start = $offer->promo_start_date?->format('Y-m-d');
                $end = $offer->promo_end_date?->format('Y-m-d');
                if ($start && $today < $start) return null;
                if ($end && $today > $end) return null;
                return [
                    'name' => $name,
                    'start_date' => $start,
                    'end_date' => $end,
                ];
            })
            ->filter()
            ->unique('name')
            ->values();
        if ($activePromos->isNotEmpty()) {
            $item['active_promotions'] = $activePromos;
        }

        return $item;
    }

    /**
     * Get filter options for the domestic tours page
     */
    private function getDomesticFilterOptions(DomesticTourSetting $setting): array
    {
        $today = now()->toDateString();
        $thailandId = DomesticTourSetting::THAILAND_ID;

        // Active domestic tour IDs
        $activeTourIds = Tour::where('status', 'active')
            ->where('primary_country_id', $thailandId)
            ->whereHas('periods', fn($q) => $q->where('status', 'open')->where('start_date', '>=', $today))
            ->pluck('id');

        $filters = [];

        // Cities in Thailand
        if ($setting->filter_city ?? true) {
            $filters['cities'] = City::active()
                ->where('country_id', $thailandId)
                ->whereHas('tours', fn($q) => $q->whereIn('tours.id', $activeTourIds))
                ->withCount(['tours' => fn($q) => $q->whereIn('tours.id', $activeTourIds)])
                ->orderBy('name_th')
                ->get()
                ->map(fn($c) => [
                    'id' => $c->id,
                    'name_th' => $c->name_th,
                    'country_id' => $c->country_id,
                    'country_name' => 'เธเธฃเธฐเน€เธ—เธจเนเธ—เธข',
                    'tour_count' => $c->tours_count,
                ]);
        }

        // Airlines / Transport
        if ($setting->filter_airline ?? true) {
            $airlineIds = DB::table('tour_transports')
                ->whereIn('tour_id', $activeTourIds)
                ->whereNotNull('transport_id')
                ->distinct()
                ->pluck('transport_id');

            $filters['airlines'] = Transport::whereIn('id', $airlineIds)
                ->active()
                ->orderBy('name')
                ->get()
                ->map(fn($t) => [
                    'id' => $t->id,
                    'code' => $t->code,
                    'name' => $t->name,
                    'image' => $t->image,
                ]);
        }

        // Departure months
        if ($setting->filter_departure_month ?? true) {
            $filters['departure_months'] = DB::table('periods')
                ->join('tours', 'tours.id', '=', 'periods.tour_id')
                ->whereIn('tours.id', $activeTourIds)
                ->where('periods.status', 'open')
                ->where('periods.start_date', '>=', $today)
                ->selectRaw("DISTINCT DATE_FORMAT(periods.start_date, '%Y-%m') as month")
                ->orderBy('month')
                ->pluck('month')
                ->map(fn($m) => [
                    'value' => $m,
                    'label' => $this->formatThaiMonth($m),
                ]);
        }

        return $filters;
    }

    /**
     * Format YYYY-MM to Thai month label
     */
    private function formatThaiMonth(string $yearMonth): string
    {
        $thaiMonths = [
            '01' => 'เธกเธเธฃเธฒเธเธก', '02' => 'เธเธธเธกเธ เธฒเธเธฑเธเธเน', '03' => 'เธกเธตเธเธฒเธเธก',
            '04' => 'เน€เธกเธฉเธฒเธขเธ', '05' => 'เธเธคเธฉเธ เธฒเธเธก', '06' => 'เธกเธดเธ–เธธเธเธฒเธขเธ',
            '07' => 'เธเธฃเธเธเธฒเธเธก', '08' => 'เธชเธดเธเธซเธฒเธเธก', '09' => 'เธเธฑเธเธขเธฒเธขเธ',
            '10' => 'เธ•เธธเธฅเธฒเธเธก', '11' => 'เธเธคเธจเธเธดเธเธฒเธขเธ', '12' => 'เธเธฑเธเธงเธฒเธเธก',
        ];
        
        [$year, $month] = explode('-', $yearMonth);
        $buddhistYear = (int)$year + 543;
        return ($thaiMonths[$month] ?? $month) . ' ' . $buddhistYear;
    }

    /**
     * Get related/similar tours for recommendation carousel
     * GET /tours/detail/{slug}/related
     *
     * Matching logic (scored):
     *  - Same country (primary_country_id)
     *  - Shared hashtags
     *  - Shared cities
     *  - Same tour_type
     * Excludes current tour, returns max 10
     */
    public function relatedTours(string $slug): JsonResponse
    {
        $tour = Tour::where('slug', $slug)
            ->where('status', 'active')
            ->with(['cities:id,name_th', 'primaryCountry:id,name_th,iso2'])
            ->first();

        if (!$tour) {
            return response()->json(['success' => false, 'message' => 'Tour not found'], 404);
        }

        // Build matching criteria
        $hashtags = $this->ensureArray($tour->hashtags);
        $cityIds = $tour->cities->pluck('id')->toArray();
        $countryId = $tour->primary_country_id;
        $tourType = $tour->tour_type;

        // Build a scored query to find the most similar tours
        $query = Tour::query()
            ->where('status', 'active')
            ->where('id', '!=', $tour->id)
            ->where('available_seats', '>', 0)
            ->whereHas('periods', function ($q) {
                $q->where('start_date', '>=', now()->toDateString())
                  ->where('status', 'open');
            });

        // Build scoring with selectRaw
        $scoreExpressions = [];
        $bindings = [];

        // Score: same country = 10 points
        if ($countryId) {
            $scoreExpressions[] = "(CASE WHEN primary_country_id = ? THEN 10 ELSE 0 END)";
            $bindings[] = $countryId;
        }

        // Score: same tour_type = 5 points
        if ($tourType) {
            $scoreExpressions[] = "(CASE WHEN tour_type = ? THEN 5 ELSE 0 END)";
            $bindings[] = $tourType;
        }

        // Score: matching hashtags = 3 points each
        foreach ($hashtags as $tag) {
            $scoreExpressions[] = "(CASE WHEN JSON_SEARCH(hashtags, 'one', ?) IS NOT NULL THEN 3 ELSE 0 END)";
            $bindings[] = $tag;
        }

        // Score: shared cities = 2 points each
        if (!empty($cityIds)) {
            foreach ($cityIds as $cityId) {
                $scoreExpressions[] = "(CASE WHEN EXISTS (SELECT 1 FROM tour_cities WHERE tour_cities.tour_id = tours.id AND tour_cities.city_id = ?) THEN 2 ELSE 0 END)";
                $bindings[] = $cityId;
            }
        }

        $scoreExpression = !empty($scoreExpressions)
            ? implode(' + ', $scoreExpressions)
            : '0';

        $query->selectRaw("tours.*, ({$scoreExpression}) as relevance_score", $bindings)
              ->having('relevance_score', '>', 0)
              ->orderByDesc('relevance_score')
              ->orderByDesc('view_count')
              ->limit(10);

        $tours = $query->get();

        // If not enough results, fill with popular tours from same country
        if ($tours->count() < 10 && $countryId) {
            $existingIds = $tours->pluck('id')->toArray();
            $existingIds[] = $tour->id;

            $fillTours = Tour::where('status', 'active')
                ->where('primary_country_id', $countryId)
                ->whereNotIn('id', $existingIds)
                ->where('available_seats', '>', 0)
                ->whereHas('periods', function ($q) {
                    $q->where('start_date', '>=', now()->toDateString())
                      ->where('status', 'open');
                })
                ->orderByDesc('view_count')
                ->limit(10 - $tours->count())
                ->get();

            $tours = $tours->concat($fillTours);
        }

        // If still not enough, fill with popular tours globally
        if ($tours->count() < 10) {
            $existingIds = $tours->pluck('id')->toArray();
            $existingIds[] = $tour->id;

            $fillTours = Tour::where('status', 'active')
                ->whereNotIn('id', $existingIds)
                ->where('available_seats', '>', 0)
                ->whereHas('periods', function ($q) {
                    $q->where('start_date', '>=', now()->toDateString())
                      ->where('status', 'open');
                })
                ->orderByDesc('view_count')
                ->limit(10 - $tours->count())
                ->get();

            $tours = $tours->concat($fillTours);
        }

        // Eager load relations for formatting
        $tours->load(['transports.transport', 'periods.offer.promotion', 'country']);

        // Format in TourTabTour format
        $today = now()->toDateString();
        $formatted = $tours->map(function (Tour $t) use ($today) {
            $airlineTransport = $t->transports
                ->where('transport_type', 'outbound')
                ->first();
            $airline = $airlineTransport
                ? ($airlineTransport->transport?->name ?? $airlineTransport->transport_name)
                : null;

            $openPeriods = $t->periods
                ->where('status', 'open')
                ->filter(fn($p) => $p->start_date >= now()->toDateString());
            $minDeparture = $openPeriods->min('start_date');
            $maxDeparture = $openPeriods->max('start_date');
            $availableSeats = $openPeriods->sum('available');

            // Collect active promotions from offers
            $activePromos = $t->periods
                ->filter(fn($p) => $p->offer && ($p->offer->promo_name || $p->offer->promotion))
                ->map(function ($p) use ($today) {
                    $offer = $p->offer;
                    $name = $offer->promo_name ?? $offer->promotion?->name;
                    if (!$name) return null;
                    $start = $offer->promo_start_date?->format('Y-m-d');
                    $end = $offer->promo_end_date?->format('Y-m-d');
                    if ($start && $today < $start) return null;
                    if ($end && $today > $end) return null;
                    return [
                        'name' => $name,
                        'start_date' => $start,
                        'end_date' => $end,
                    ];
                })
                ->filter()
                ->unique('name')
                ->values()
                ->toArray();

            return [
                'id' => $t->id,
                'slug' => $t->slug,
                'title' => $t->title,
                'tour_code' => $t->tour_code,
                'country' => [
                    'id' => $t->primary_country_id ?? $t->country_id,
                    'name' => $t->country?->name_th ?? $t->country?->name_en,
                    'iso2' => $t->country?->iso2,
                ],
                'days' => $t->duration_days ?? $t->days,
                'nights' => $t->duration_nights ?? $t->nights,
                'price' => $t->min_price,
                'original_price' => $t->price_adult,
                'discount_adult' => $t->discount_adult,
                'discount_percent' => $t->max_discount_percent,
                'departure_date' => $minDeparture,
                'max_departure_date' => $maxDeparture,
                'airline' => $airline,
                'image_url' => $t->effective_cover_image_url,
                'badge' => $t->badge,
                'rating' => $t->rating,
                'review_count' => $t->review_count,
                'available_seats' => $availableSeats,
                'view_count' => $t->view_count ?? 0,
                'hotel_star' => $t->hotel_star,
                'active_promotions' => $activePromos,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $formatted,
        ]);
    }
}
