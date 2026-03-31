<?php

namespace App\Http\Controllers;

use App\Models\FestivalHoliday;
use App\Models\FestivalPageSetting;
use App\Models\Tour;
use App\Models\City;
use App\Models\Country;
use App\Services\CloudflareImagesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FestivalHolidayController extends Controller
{
    protected CloudflareImagesService $cloudflare;

    public function __construct(CloudflareImagesService $cloudflare)
    {
        $this->cloudflare = $cloudflare;
    }

    // ============================
    // Admin CRUD
    // ============================

    public function index(): JsonResponse
    {
        $holidays = FestivalHoliday::orderBy('sort_order')
            ->orderByDesc('start_date')
            ->get()
            ->map(function ($h) {
                $h->tour_count = $h->getMatchingTourIds()->count();
                return $h;
            });

        return response()->json(['success' => true, 'data' => $holidays]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:festival_holidays,slug',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'badge_text' => 'nullable|string|max:100',
            'badge_color' => 'nullable|string|max:50',
            'badge_icon' => 'nullable|string|max:50',
            'display_modes' => 'nullable|array',
            'display_modes.*' => 'string|in:card,period',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $holiday = FestivalHoliday::create($validated);

        return response()->json([
            'success' => true,
            'data' => $holiday,
            'message' => 'สร้างวันหยุดเทศกาลสำเร็จ',
        ], 201);
    }

    public function show(FestivalHoliday $festivalHoliday): JsonResponse
    {
        $festivalHoliday->tour_count = $festivalHoliday->getMatchingTourIds()->count();

        return response()->json(['success' => true, 'data' => $festivalHoliday]);
    }

    public function update(Request $request, FestivalHoliday $festivalHoliday): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:festival_holidays,slug,' . $festivalHoliday->id,
            'description' => 'nullable|string',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'cover_image_position' => 'nullable|string|max:50',
            'badge_text' => 'nullable|string|max:100',
            'badge_color' => 'nullable|string|max:50',
            'badge_icon' => 'nullable|string|max:50',
            'display_modes' => 'nullable|array',
            'display_modes.*' => 'string|in:card,period',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $festivalHoliday->update($validated);

        return response()->json([
            'success' => true,
            'data' => $festivalHoliday->fresh(),
            'message' => 'อัปเดตสำเร็จ',
        ]);
    }

    public function destroy(FestivalHoliday $festivalHoliday): JsonResponse
    {
        // Delete images from Cloudflare
        if ($festivalHoliday->image_cf_id) {
            $this->cloudflare->delete($festivalHoliday->image_cf_id);
        }
        if ($festivalHoliday->cover_image_cf_id) {
            $this->cloudflare->delete($festivalHoliday->cover_image_cf_id);
        }

        $festivalHoliday->delete();

        return response()->json(['success' => true, 'message' => 'ลบสำเร็จ']);
    }

    public function toggleStatus(FestivalHoliday $festivalHoliday): JsonResponse
    {
        $festivalHoliday->update(['is_active' => !$festivalHoliday->is_active]);

        return response()->json([
            'success' => true,
            'data' => $festivalHoliday->fresh(),
            'message' => $festivalHoliday->is_active ? 'เปิดใช้งานแล้ว' : 'ปิดใช้งานแล้ว',
        ]);
    }

    // ============================
    // Image uploads
    // ============================

    /**
     * Upload holiday card image
     */
    public function uploadImage(Request $request, FestivalHoliday $festivalHoliday): JsonResponse
    {
        $request->validate([
            'image' => 'required|file|mimes:jpeg,jpg,png,gif,webp|max:10240',
        ]);

        $file = $request->file('image');

        if ($festivalHoliday->image_cf_id) {
            $this->cloudflare->delete($festivalHoliday->image_cf_id);
        }

        $customId = 'festival-holiday-' . $festivalHoliday->id . '-' . time();
        $result = $this->cloudflare->uploadFromFile($file, $customId, [
            'type' => 'festival_holiday',
            'holiday_id' => $festivalHoliday->id,
        ]);

        if (!$result) {
            return response()->json(['success' => false, 'message' => 'อัปโหลดภาพไม่สำเร็จ'], 500);
        }

        $url = $this->cloudflare->getDisplayUrl($result['id'], 'public');
        $festivalHoliday->update(['image_url' => $url, 'image_cf_id' => $result['id']]);

        return response()->json(['success' => true, 'data' => $festivalHoliday->fresh()]);
    }

    public function deleteImage(FestivalHoliday $festivalHoliday): JsonResponse
    {
        if ($festivalHoliday->image_cf_id) {
            $this->cloudflare->delete($festivalHoliday->image_cf_id);
        }

        $festivalHoliday->update(['image_url' => null, 'image_cf_id' => null]);

        return response()->json(['success' => true, 'data' => $festivalHoliday->fresh()]);
    }

    /**
     * Upload cover image for detail page hero
     */
    public function uploadCoverImage(Request $request, FestivalHoliday $festivalHoliday): JsonResponse
    {
        $request->validate([
            'cover_image' => 'required|file|mimes:jpeg,jpg,png,gif,webp|max:10240',
        ]);

        $file = $request->file('cover_image');

        if ($festivalHoliday->cover_image_cf_id) {
            $this->cloudflare->delete($festivalHoliday->cover_image_cf_id);
        }

        $customId = 'festival-cover-' . $festivalHoliday->id . '-' . time();
        $result = $this->cloudflare->uploadFromFile($file, $customId, [
            'type' => 'festival_cover',
            'holiday_id' => $festivalHoliday->id,
        ]);

        if (!$result) {
            return response()->json(['success' => false, 'message' => 'อัปโหลดภาพไม่สำเร็จ'], 500);
        }

        $url = $this->cloudflare->getDisplayUrl($result['id'], 'public');
        $festivalHoliday->update(['cover_image_url' => $url, 'cover_image_cf_id' => $result['id']]);

        return response()->json(['success' => true, 'data' => $festivalHoliday->fresh()]);
    }

    public function deleteCoverImage(FestivalHoliday $festivalHoliday): JsonResponse
    {
        if ($festivalHoliday->cover_image_cf_id) {
            $this->cloudflare->delete($festivalHoliday->cover_image_cf_id);
        }

        $festivalHoliday->update(['cover_image_url' => null, 'cover_image_cf_id' => null]);

        return response()->json(['success' => true, 'data' => $festivalHoliday->fresh()]);
    }

    /**
     * Preview matching tours count
     */
    public function previewTours(FestivalHoliday $festivalHoliday): JsonResponse
    {
        $tourIds = $festivalHoliday->getMatchingTourIds();

        $preview = Tour::whereIn('id', $tourIds)
            ->select('id', 'title', 'tour_code', 'primary_country_id', 'duration_days', 'duration_nights', 'min_price', 'cover_image_url')
            ->with('primaryCountry:id,name_th,iso2')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_count' => $tourIds->count(),
                'preview_tours' => $preview,
            ],
        ]);
    }

    // ============================
    // Public endpoints
    // ============================

    /**
     * GET /tours/festival — list all active festivals as cards
     */
    public function publicList(): JsonResponse
    {
        $holidays = FestivalHoliday::active()
            ->orderBy('sort_order')
            ->orderBy('start_date')
            ->get()
            ->map(function ($h) {
                $tourIds = $h->getMatchingTourIds();
                $tours = Tour::whereIn('id', $tourIds)
                    ->with('primaryCountry:id,name_th,name_en,iso2,slug')
                    ->get();

                $countries = $tours->groupBy('primary_country_id')
                    ->map(function ($group) {
                        $country = $group->first()->primaryCountry;
                        if (!$country) return null;
                        return [
                            'id' => $country->id,
                            'name_th' => $country->name_th,
                            'slug' => $country->slug,
                            'iso2' => strtolower($country->iso2 ?? ''),
                            'tour_count' => $group->count(),
                        ];
                    })
                    ->filter()
                    ->sortByDesc('tour_count')
                    ->values();

                return [
                    'id' => $h->id,
                    'name' => $h->name,
                    'slug' => $h->slug,
                    'description' => $h->description,
                    'start_date' => $h->start_date->format('Y-m-d'),
                    'end_date' => $h->end_date->format('Y-m-d'),
                    'date_range_text' => $h->date_range_text,
                    'image_url' => $h->image_url,
                    'badge_text' => $h->badge_text,
                    'badge_color' => $h->badge_color,
                    'badge_icon' => $h->badge_icon,
                    'tour_count' => $tourIds->count(),
                    'countries' => $countries,
                ];
            })
            ->filter(fn($h) => $h['tour_count'] > 0)
            ->values();

        return response()->json(['success' => true, 'data' => $holidays]);
    }

    /**
     * GET /tours/festival/{slug} — tours matching a specific festival
     */
    public function publicShow(string $slug, Request $request): JsonResponse
    {
        $holiday = FestivalHoliday::active()->where('slug', $slug)->firstOrFail();

        $filters = [
            'search' => $request->input('search'),
            'country_id' => $request->input('country_id'),
            'city_id' => $request->input('city_id'),
            'airline_id' => $request->input('airline_id'),
            'departure_date_from' => $request->input('departure_date_from'),
            'departure_date_to' => $request->input('departure_date_to'),
            'departure_month' => $request->input('departure_month'),
            'price_min' => $request->input('price_min'),
            'price_max' => $request->input('price_max'),
            'min_seats' => $request->input('min_seats'),
            'sort_by' => $request->input('sort_by'),
        ];

        $perPage = $request->input('per_page', 10);
        $tours = $holiday->getMatchingTours($perPage, $filters);

        $formattedTours = collect($tours->items())->map(function ($tour) use ($holiday) {
            return $this->formatFestivalTourItem($tour, $holiday);
        });

        // Get filter options from matching tours
        $allTourIds = $holiday->getMatchingTourIds();
        $filterOptions = $this->getFestivalFilterOptions($allTourIds);

        return response()->json([
            'success' => true,
            'festival' => [
                'id' => $holiday->id,
                'name' => $holiday->name,
                'slug' => $holiday->slug,
                'description' => $holiday->description,
                'start_date' => $holiday->start_date->format('Y-m-d'),
                'end_date' => $holiday->end_date->format('Y-m-d'),
                'date_range_text' => $holiday->date_range_text,
                'image_url' => $holiday->image_url,
                'cover_image_url' => $holiday->cover_image_url,
                'cover_image_position' => $holiday->cover_image_position ?? 'center',
                'badge_text' => $holiday->badge_text,
                'badge_color' => $holiday->badge_color,
                'badge_icon' => $holiday->badge_icon,
            ],
            'data' => $formattedTours,
            'meta' => [
                'current_page' => $tours->currentPage(),
                'last_page' => $tours->lastPage(),
                'per_page' => $tours->perPage(),
                'total' => $tours->total(),
            ],
            'filters' => $filterOptions,
            'settings' => [
                'show_periods' => true,
                'max_periods_display' => 10,
                'show_transport' => true,
                'show_hotel_star' => true,
                'show_meal_count' => true,
                'show_commission' => false,
                'filter_search' => true,
                'filter_country' => true,
                'filter_city' => true,
                'filter_airline' => true,
                'filter_departure_month' => true,
                'filter_price_range' => true,
                'filter_festival' => true,
                'filter_promotion' => true,
                'filter_theme' => true,
                'filter_special_highlight' => true,
                'filter_advanced' => true,
                'sort_options' => [
                    'departure_date' => 'วันเดินทาง',
                    'price_asc' => 'ราคาต่ำ-สูง',
                    'price_desc' => 'ราคาสูง-ต่ำ',
                    'newest' => 'ใหม่ล่าสุด',
                    'popular' => 'ยอดนิยม',
                ],
            ],
        ]);
    }

    /**
     * GET /tours/festival-badges — for badge context
     */
    public function publicBadges(): JsonResponse
    {
        $holidays = FestivalHoliday::active()
            ->whereNotNull('badge_text')
            ->get();

        $badges = $holidays->map(function ($h) {
            $tourIds = $h->getMatchingTourIds()->toArray();
            $periodIds = $h->getMatchingPeriodIds()->toArray();

            return [
                'id' => $h->id,
                'name' => $h->name,
                'badge_text' => $h->badge_text,
                'badge_color' => $h->badge_color,
                'badge_icon' => $h->badge_icon,
                'display_modes' => $h->display_modes ?? [],
                'tour_ids' => $tourIds,
                'period_ids' => $periodIds,
                'slug' => $h->slug,
            ];
        })->filter(fn($b) => count($b['tour_ids']) > 0)->values();

        return response()->json(['success' => true, 'data' => $badges]);
    }

    // ============================
    // Private helpers
    // ============================

    private function formatFestivalTourItem(Tour $tour, FestivalHoliday $holiday): array
    {
        $breakfasts = $tour->itineraries->where('has_breakfast', true)->count();
        $lunches = $tour->itineraries->where('has_lunch', true)->count();
        $dinners = $tour->itineraries->where('has_dinner', true)->count();

        $item = [
            'id' => $tour->id,
            'slug' => $tour->slug,
            'tour_code' => $tour->tour_code,
            'title' => $tour->title,
            'description' => $tour->description,
            'cover_image_url' => $tour->cover_image_url,
            'cover_image_alt' => $tour->cover_image_alt,
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
            'pdf_url' => $tour->pdf_url,
            'highlights' => is_array($tour->highlights) ? $tour->highlights : [],
            'hashtags' => is_array($tour->hashtags) ? $tour->hashtags : [],
            'hotel_star' => $tour->hotel_star,
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
            'meal_count' => [
                'breakfast' => $breakfasts,
                'lunch' => $lunches,
                'dinner' => $dinners,
                'total' => $breakfasts + $lunches + $dinners,
            ],
            'transports' => $tour->transports->map(fn($t) => [
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
            ])->values(),
            'periods' => $tour->periods->map(function ($period) {
                $data = [
                    'id' => $period->id,
                    'start_date' => $period->start_date?->format('Y-m-d'),
                    'end_date' => $period->end_date?->format('Y-m-d'),
                    'capacity' => $period->capacity,
                    'booked' => $period->booked,
                    'available' => $period->available,
                    'status' => $period->status,
                ];

                if ($period->offer) {
                    $offer = $period->offer;
                    $data['offer'] = [
                        'price_adult' => (float) $offer->price_adult,
                        'discount_adult' => (float) $offer->discount_adult,
                        'net_price_adult' => (float) ($offer->price_adult - $offer->discount_adult),
                        'price_single' => $offer->price_single ? (float) $offer->price_single : null,
                        'discount_single' => (float) ($offer->discount_single ?? 0),
                        'net_price_single' => $offer->price_single ? (float) ($offer->price_single - ($offer->discount_single ?? 0)) : null,
                        'promo_name' => $offer->promo_name ?? $offer->promotion?->name,
                        'promo_start_date' => $offer->promo_start_date?->format('Y-m-d'),
                        'promo_end_date' => $offer->promo_end_date?->format('Y-m-d'),
                    ];
                } else {
                    $data['offer'] = null;
                }

                return $data;
            })->values(),
        ];

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

    private function getFestivalFilterOptions($tourIds): array
    {
        $tours = Tour::whereIn('id', $tourIds)
            ->with(['primaryCountry:id,name_th,name_en,iso2,slug', 'cities:id,name_th,name_en,slug,country_id', 'cities.country:id,name_th'])
            ->get();

        $countries = $tours->pluck('primaryCountry')->filter()->unique('id')->map(fn($c) => [
            'id' => $c->id,
            'name_th' => $c->name_th,
            'slug' => $c->slug ?? '',
            'iso2' => strtolower($c->iso2 ?? ''),
            'tour_count' => $tours->where('primary_country_id', $c->id)->count(),
        ])->sortBy('name_th')->values();

        $cities = $tours->pluck('cities')->flatten()->unique('id')->map(fn($c) => [
            'id' => $c->id,
            'name_th' => $c->name_th,
            'slug' => $c->slug,
            'country_id' => $c->country_id,
            'country_name' => $c->country?->name_th,
            'tour_count' => $tours->filter(fn($t) => $t->cities->contains('id', $c->id))->count(),
        ])->sortBy('name_th')->values();

        // Airlines
        $airlineIds = \DB::table('tour_transports')
            ->whereIn('tour_id', $tourIds)
            ->whereNotNull('transport_id')
            ->distinct()
            ->pluck('transport_id');

        $airlines = \App\Models\Transport::whereIn('id', $airlineIds)
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'code' => $t->code,
                'name' => $t->name,
                'image' => $t->image,
            ]);

        // Departure months
        $today = now()->toDateString();
        $departureMonths = \DB::table('periods')
            ->join('tours', 'tours.id', '=', 'periods.tour_id')
            ->whereIn('tours.id', $tourIds)
            ->where('periods.status', 'open')
            ->where('periods.start_date', '>=', $today)
            ->selectRaw("DISTINCT DATE_FORMAT(periods.start_date, '%Y-%m') as month")
            ->orderBy('month')
            ->pluck('month')
            ->map(function ($m) {
                $parts = explode('-', $m);
                $thMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
                $buddhistYear = (int)$parts[0] + 543;
                return [
                    'value' => $m,
                    'label' => $thMonths[(int)$parts[1]] . ' ' . substr($buddhistYear, -2),
                ];
            });

        return [
            'countries' => $countries,
            'cities' => $cities,
            'airlines' => $airlines,
            'departure_months' => $departureMonths,
        ];
    }

    // ===== Page Settings (Cover Image for listing page) =====

    public function getPageSettings(): JsonResponse
    {
        $setting = FestivalPageSetting::getSettings();
        return response()->json(['data' => $setting]);
    }

    public function updatePageSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cover_image_position' => 'nullable|string|max:50',
        ]);

        $setting = FestivalPageSetting::getSettings();
        $setting->update($validated);

        return response()->json(['data' => $setting]);
    }

    public function uploadPageCoverImage(Request $request): JsonResponse
    {
        $request->validate([
            'cover_image' => 'required|image|mimes:jpeg,png,gif,webp|max:10240',
        ]);

        $setting = FestivalPageSetting::getSettings();

        // Delete old image from Cloudflare
        if ($setting->cover_image_cf_id) {
            try {
                $this->cloudflare->delete($setting->cover_image_cf_id);
            } catch (\Exception $e) {
                \Log::warning('Failed to delete old festival page cover: ' . $e->getMessage());
            }
        }

        $file = $request->file('cover_image');
        $customId = 'festival-page-cover-' . time();

        try {
            $result = $this->cloudflare->uploadFromFile($file, $customId);
            $url = $this->cloudflare->getDisplayUrl($result['id'], 'public');
            $setting->update([
                'cover_image_url' => $url,
                'cover_image_cf_id' => $result['id'],
            ]);

            return response()->json([
                'message' => 'อัปโหลดภาพปกสำเร็จ',
                'data' => $setting->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'อัปโหลดไม่สำเร็จ: ' . $e->getMessage()], 500);
        }
    }

    public function deletePageCoverImage(): JsonResponse
    {
        $setting = FestivalPageSetting::getSettings();

        if ($setting->cover_image_cf_id) {
            try {
                $this->cloudflare->delete($setting->cover_image_cf_id);
            } catch (\Exception $e) {
                \Log::warning('Failed to delete festival page cover: ' . $e->getMessage());
            }
        }

        $setting->update([
            'cover_image_url' => null,
            'cover_image_cf_id' => null,
        ]);

        return response()->json(['message' => 'ลบภาพปกสำเร็จ', 'data' => $setting]);
    }

    public function publicPageSettings(): JsonResponse
    {
        $setting = FestivalPageSetting::getSettings();

        return response()->json([
            'cover_image_url' => $setting->cover_image_url,
            'cover_image_position' => $setting->cover_image_position ?? 'center',
        ]);
    }
}
