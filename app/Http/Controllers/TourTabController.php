<?php

namespace App\Http\Controllers;

use App\Models\TourTab;
use App\Models\Tour;
use App\Models\Country;
use App\Models\Wholesaler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TourTabController extends Controller
{
    /**
     * List all tour tabs (admin)
     */
    public function index(Request $request): JsonResponse
    {
        $query = TourTab::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $tabs = $query->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => $tabs,
        ]);
    }

    /**
     * Create a new tour tab
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tour_tabs,slug',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:50',
            'badge_text' => 'nullable|string|max:50',
            'badge_color' => 'nullable|string|max:20',
            'display_modes' => 'nullable|array',
            'display_modes.*' => 'string|in:tab,badge,period,promotion',
            'badge_icon' => 'nullable|string|max:10',
            'badge_expires_at' => 'nullable|date',
            'conditions' => 'nullable|array',
            'display_limit' => 'nullable|integer|min:1',
            'sort_by' => 'nullable|string|in:popular,price_asc,price_desc,newest,departure_date',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        // Auto-generate slug if not provided
        if (empty($validated['slug'])) {
            $baseSlug = Str::slug($validated['name']);
            if (empty($baseSlug)) {
                $baseSlug = 'tab-' . Str::random(8);
            }
            // Ensure unique
            $slug = $baseSlug;
            $counter = 1;
            while (TourTab::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter++;
            }
            $validated['slug'] = $slug;
        }

        $tab = TourTab::create($validated);

        return response()->json([
            'success' => true,
            'data' => $tab,
            'message' => 'สร้าง Tab สำเร็จ',
        ], 201);
    }

    /**
     * Show a tour tab
     */
    public function show(TourTab $tourTab): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $tourTab,
        ]);
    }

    /**
     * Update a tour tab
     */
    public function update(Request $request, TourTab $tourTab): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'slug' => 'nullable|string|max:255|unique:tour_tabs,slug,' . $tourTab->id,
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:50',
            'badge_text' => 'nullable|string|max:50',
            'badge_color' => 'nullable|string|max:20',
            'display_modes' => 'nullable|array',
            'display_modes.*' => 'string|in:tab,badge,period,promotion',
            'badge_icon' => 'nullable|string|max:10',
            'badge_expires_at' => 'nullable|date',
            'conditions' => 'nullable|array',
            'display_limit' => 'nullable|integer|min:1',
            'sort_by' => 'nullable|string|in:popular,price_asc,price_desc,newest,departure_date',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $tourTab->update($validated);

        return response()->json([
            'success' => true,
            'data' => $tourTab,
            'message' => 'อัปเดต Tab สำเร็จ',
        ]);
    }

    /**
     * Delete a tour tab
     */
    public function destroy(TourTab $tourTab): JsonResponse
    {
        $tourTab->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบ Tab สำเร็จ',
        ]);
    }

    /**
     * Toggle active status
     */
    public function toggleStatus(TourTab $tourTab): JsonResponse
    {
        $tourTab->update(['is_active' => !$tourTab->is_active]);

        return response()->json([
            'success' => true,
            'data' => $tourTab,
            'message' => $tourTab->is_active ? 'เปิดใช้งานแล้ว' : 'ปิดใช้งานแล้ว',
        ]);
    }

    /**
     * Reorder tabs
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:tour_tabs,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['items'] as $item) {
            TourTab::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'จัดเรียงลำดับสำเร็จ',
        ]);
    }

    /**
     * Preview tours for a tab (admin - test conditions)
     */
    public function preview(TourTab $tourTab, Request $request): JsonResponse
    {
        $limit = $request->integer('limit', $tourTab->display_limit);
        
        try {
            $tours = $tourTab->getTours($limit);
            
            // Format tours for preview - use tours table fields directly
            $formattedTours = $tours->map(function ($tour) {
                return [
                    'id' => $tour->id,
                    'title' => $tour->title,
                    'tour_code' => $tour->tour_code,
                    'country' => $tour->country?->name_th ?? $tour->country?->name_en,
                    'days' => $tour->duration_days ?? $tour->days,
                    'nights' => $tour->duration_nights ?? $tour->nights,
                    'price' => $tour->min_price,
                    'departure_date' => $tour->next_departure_date,
                    'image_url' => $tour->cover_image_url,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'tours' => $formattedTours,
                    'total' => $tours->count(),
                    'conditions' => $tourTab->conditions,
                    'sort_by' => $tourTab->sort_by,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview tours with conditions (without saving - for testing in modal)
     */
    public function previewConditions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conditions' => 'nullable|array',
            'sort_by' => 'nullable|string|in:popular,price_asc,price_desc,newest,departure_date',
            'display_limit' => 'nullable|integer|min:1',
        ]);

        $conditions = $validated['conditions'] ?? [];
        $sortBy = $validated['sort_by'] ?? 'popular';
        $displayLimit = $validated['display_limit'] ?? 12;

        try {
            // Create a temporary TourTab instance (not saved)
            $tempTab = new TourTab([
                'conditions' => $conditions,
                'sort_by' => $sortBy,
                'display_limit' => $displayLimit,
            ]);

            $tours = $tempTab->getTours($displayLimit);

            // Format tours for preview - use tours table fields directly
            $formattedTours = $tours->map(function ($tour) {
                return [
                    'id' => $tour->id,
                    'title' => $tour->title,
                    'tour_code' => $tour->tour_code,
                    'country' => $tour->country?->name_th ?? $tour->country?->name_en,
                    'days' => $tour->duration_days ?? $tour->days,
                    'nights' => $tour->duration_nights ?? $tour->nights,
                    'price' => $tour->min_price,
                    'departure_date' => $tour->next_departure_date,
                    'image_url' => $tour->cover_image_url,
                    'view_count' => $tour->view_count ?? 0,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'tours' => $formattedTours,
                    'total' => $tours->count(),
                    'conditions' => $conditions,
                    'sort_by' => $sortBy,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get condition options (for dropdown selects in admin)
     */
    public function getConditionOptions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'condition_types' => TourTab::CONDITION_TYPES,
                'sort_options' => TourTab::SORT_OPTIONS,
                'display_modes' => TourTab::DISPLAY_MODES,
                'countries' => Country::orderBy('name_th')->get(['id', 'name_th', 'name_en', 'iso2']),
                'regions' => Tour::REGIONS,
                'wholesalers' => Wholesaler::where('is_active', true)->get(['id', 'name', 'code']),
                'tour_types' => Tour::TOUR_TYPES,
            ],
        ]);
    }

    // ==========================================
    // Public API (for tour-web frontend)
    // ==========================================

    /**
     * Get active tabs with tours for public display
     */
    /**
     * Format a tour for public tab display
     */
    private function formatTourForTab(Tour $tour): array
    {
        // Get airline from first outbound transport
        $airlineTransport = $tour->transports
            ->where('transport_type', 'outbound')
            ->first();
        $airline = $airlineTransport
            ? ($airlineTransport->transport?->name ?? $airlineTransport->transport_name)
            : null;

        // Get departure date range from open future periods
        $openPeriods = $tour->periods
            ->where('status', 'open')
            ->where('start_date', '>=', now()->toDateString());
        $minDeparture = $openPeriods->min('start_date');
        $maxDeparture = $openPeriods->max('start_date');
        
        // Calculate available seats from open future periods
        $availableSeats = $openPeriods->sum('available');

        // Periods preview: up to 7 upcoming periods (sorted ascending)
        $tourDays = max(1, $tour->duration_days ?? $tour->days ?? 1);
        $sortedPeriods = $openPeriods->sortBy('start_date');
        $periodsPreview = $sortedPeriods->take(5)->map(function ($p) use ($tourDays) {
            $startDate = $p->start_date; // Carbon instance
            $endDate   = $p->end_date;   // Carbon instance or null
            // If end_date missing or same as start_date, calculate from tour duration
            if (!$endDate || $endDate->toDateString() === $startDate->toDateString()) {
                $endDate = $startDate->copy()->addDays($tourDays - 1);
            }
            return [
                'start' => $startDate->toDateString(),
                'end'   => $endDate->toDateString(),
            ];
        })->values()->toArray();
        $totalPeriods = $sortedPeriods->count();

        return [
            'id' => $tour->id,
            'slug' => $tour->slug,
            'title' => $tour->title,
            'tour_code' => $tour->tour_code,
            'country' => [
                'id' => $tour->primary_country_id ?? $tour->country_id,
                'name' => $tour->country?->name_th ?? $tour->country?->name_en,
                'iso2' => $tour->country?->iso2,
            ],
            'days' => $tour->duration_days ?? $tour->days,
            'nights' => $tour->duration_nights ?? $tour->nights,
            'price' => $tour->min_price,
            'original_price' => $tour->price_adult,
            'discount_adult' => $tour->discount_adult,
            'discount_percent' => $tour->max_discount_percent,
            'departure_date' => $minDeparture,
            'max_departure_date' => $maxDeparture,
            'airline' => $airline,
            'image_url' => $tour->cover_image_url,
            'badge' => $tour->badge,
            'rating' => $tour->rating,
            'review_count' => $tour->review_count,
            'available_seats' => $availableSeats,
            'view_count' => $tour->view_count ?? 0,
            'hotel_star' => $tour->hotel_star,
            'periods_preview' => $periodsPreview,
            'total_periods' => $totalPeriods,
            'active_promotions' => $this->getActivePromotions($tour),
        ];
    }

    /**
     * Collect active promotions from period offers for badge display on tour card
     */
    private function getActivePromotions(Tour $tour): array
    {
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
            ->values()
            ->toArray();

        return $activePromos;
    }

    public function publicList(Request $request): JsonResponse
    {
        $tabs = TourTab::active()->ordered()
            ->where(function ($q) {
                $q->whereNull('badge_expires_at')
                  ->orWhere('badge_expires_at', '>', now());
            })
            ->get();

        $result = $tabs->map(function ($tab) {
            $tours = $tab->getTours();

            // Eager-load relations for airline & departure dates
            $tours->load(['transports.transport', 'periods.offer.promotion', 'country']);

            $formattedTours = $tours->map(fn ($tour) => $this->formatTourForTab($tour));

            return [
                'id' => $tab->id,
                'name' => $tab->name,
                'slug' => $tab->slug,
                'description' => $tab->description,
                'icon' => $tab->icon,
                'badge_text' => $tab->badge_text,
                'badge_color' => $tab->badge_color,
                'display_modes' => $tab->display_modes ?? ['tab'],
                'badge_icon' => $tab->badge_icon,
                'tours' => $formattedTours,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Get badge-type tabs with tour IDs for global badge display
     * ใช้สำหรับทุกหน้าเว็บเพื่อแสดง badge บนการ์ดทัวร์
     */
    public function publicBadges(Request $request): JsonResponse
    {
        $tabs = TourTab::active()
            ->where(function ($q) {
                $q->whereJsonContains('display_modes', 'badge')
                  ->orWhereJsonContains('display_modes', 'period');
            })
            ->where(function ($q) {
                $q->whereNull('badge_expires_at')
                  ->orWhere('badge_expires_at', '>', now());
            })
            ->ordered()
            ->get();

        $result = $tabs->map(function ($tab) {
            $tours = $tab->getTours($tab->display_limit);
            $tourIds = $tours->pluck('id')->toArray();

            // Extract discount_min_amount from conditions for period-level badge matching
            $discountMinAmount = null;
            $conditions = is_array($tab->conditions) ? $tab->conditions : json_decode($tab->conditions, true) ?? [];
            foreach ($conditions as $cond) {
                if (($cond['type'] ?? '') === 'discount_min_amount') {
                    $discountMinAmount = (float) ($cond['value'] ?? 0);
                }
            }

            return [
                'id' => $tab->id,
                'name' => $tab->name,
                'badge_text' => $tab->badge_text,
                'badge_color' => $tab->badge_color,
                'badge_icon' => $tab->badge_icon,
                'tour_ids' => $tourIds,
                'discount_min_amount' => $discountMinAmount,
                'display_modes' => $tab->display_modes ?? [],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Get tours for a specific tab (public)
     */
    public function publicShow(string $slug, Request $request): JsonResponse
    {
        $tab = TourTab::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $limit = $request->integer('limit', $tab->display_limit);
        
        $tours = $tab->getTours($limit);

        // Eager-load relations for airline & departure dates
        $tours->load(['transports.transport', 'periods.offer.promotion', 'country']);

        $formattedTours = $tours->map(fn ($tour) => $this->formatTourForTab($tour));

        return response()->json([
            'success' => true,
            'data' => [
                'tab' => [
                    'id' => $tab->id,
                    'name' => $tab->name,
                    'slug' => $tab->slug,
                    'description' => $tab->description,
                    'icon' => $tab->icon,
                ],
                'tours' => $formattedTours,
            ],
        ]);
    }

    /**
     * Get all promotion-type tour tabs with their tours (public)
     * สำหรับหน้า "ทัวร์โปรโมชั่น" ของ tour-web
     */
    public function publicPromotions(Request $request): JsonResponse
    {
        $tabs = TourTab::active()->ordered()
            ->whereJsonContains('display_modes', 'promotion')
            ->where(function ($q) {
                $q->whereNull('badge_expires_at')
                  ->orWhere('badge_expires_at', '>', now());
            })
            ->get();

        $result = $tabs->map(function ($tab) {
            $tours = $tab->getTours();

            // Eager-load relations for airline & departure dates
            $tours->load(['transports.transport', 'periods.offer.promotion', 'country']);

            $formattedTours = $tours->map(fn ($tour) => $this->formatTourForTab($tour));

            return [
                'id' => $tab->id,
                'name' => $tab->name,
                'slug' => $tab->slug,
                'description' => $tab->description,
                'icon' => $tab->icon,
                'badge_text' => $tab->badge_text,
                'badge_color' => $tab->badge_color,
                'badge_icon' => $tab->badge_icon,
                'display_modes' => $tab->display_modes ?? [],
                'tours' => $formattedTours,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
