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
            'conditions' => 'nullable|array',
            'display_limit' => 'nullable|integer|min:1|max:50',
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
            'conditions' => 'nullable|array',
            'display_limit' => 'nullable|integer|min:1|max:50',
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
            'display_limit' => 'nullable|integer|min:1|max:50',
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
        ];
    }

    public function publicList(Request $request): JsonResponse
    {
        $tabs = TourTab::active()->ordered()->get();

        $result = $tabs->map(function ($tab) {
            $tours = $tab->getTours();

            // Eager-load relations for airline & departure dates
            $tours->load(['transports.transport', 'periods', 'country']);

            $formattedTours = $tours->map(fn ($tour) => $this->formatTourForTab($tour));

            return [
                'id' => $tab->id,
                'name' => $tab->name,
                'slug' => $tab->slug,
                'description' => $tab->description,
                'icon' => $tab->icon,
                'badge_text' => $tab->badge_text,
                'badge_color' => $tab->badge_color,
                'tours' => $formattedTours,
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
        $tours->load(['transports.transport', 'periods', 'country']);

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
}
