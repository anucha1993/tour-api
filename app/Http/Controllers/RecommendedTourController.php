<?php

namespace App\Http\Controllers;

use App\Models\RecommendedTourSection;
use App\Models\RecommendedTourSetting;
use App\Models\Tour;
use App\Models\Country;
use App\Models\Wholesaler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendedTourController extends Controller
{
    // ==========================================
    // Admin CRUD for sections
    // ==========================================

    /**
     * List all sections
     */
    public function index(Request $request): JsonResponse
    {
        $query = RecommendedTourSection::ordered();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    /**
     * Create a section
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'conditions' => 'nullable|array',
            'conditions.*.type' => 'required|string',
            'conditions.*.value' => 'required',
            'display_limit' => 'nullable|integer|min:1|max:50',
            'sort_by' => 'nullable|string|in:popular,price_asc,price_desc,newest,departure_date',
            'sort_order' => 'nullable|integer|min:0',
            'weight' => 'nullable|integer|min:1|max:100',
            'schedule_start' => 'nullable|date',
            'schedule_end' => 'nullable|date|after:schedule_start',
            'is_active' => 'nullable|boolean',
        ]);

        // Auto sort_order
        if (!isset($validated['sort_order'])) {
            $validated['sort_order'] = (RecommendedTourSection::max('sort_order') ?? 0) + 1;
        }

        $section = RecommendedTourSection::create($validated);

        return response()->json([
            'success' => true,
            'data' => $section,
            'message' => 'สร้าง Section สำเร็จ',
        ], 201);
    }

    /**
     * Get a single section
     */
    public function show(RecommendedTourSection $recommendedTourSection): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $recommendedTourSection,
        ]);
    }

    /**
     * Update a section
     */
    public function update(Request $request, RecommendedTourSection $recommendedTourSection): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'conditions' => 'nullable|array',
            'conditions.*.type' => 'required|string',
            'conditions.*.value' => 'required',
            'display_limit' => 'nullable|integer|min:1|max:50',
            'sort_by' => 'nullable|string|in:popular,price_asc,price_desc,newest,departure_date',
            'sort_order' => 'nullable|integer|min:0',
            'weight' => 'nullable|integer|min:1|max:100',
            'schedule_start' => 'nullable|date',
            'schedule_end' => 'nullable|date|after:schedule_start',
            'is_active' => 'nullable|boolean',
        ]);

        $recommendedTourSection->update($validated);

        return response()->json([
            'success' => true,
            'data' => $recommendedTourSection->fresh(),
            'message' => 'อัปเดต Section สำเร็จ',
        ]);
    }

    /**
     * Delete a section
     */
    public function destroy(RecommendedTourSection $recommendedTourSection): JsonResponse
    {
        $recommendedTourSection->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบ Section สำเร็จ',
        ]);
    }

    /**
     * Toggle active status
     */
    public function toggleStatus(RecommendedTourSection $recommendedTourSection): JsonResponse
    {
        $recommendedTourSection->update([
            'is_active' => !$recommendedTourSection->is_active,
        ]);

        return response()->json([
            'success' => true,
            'data' => $recommendedTourSection->fresh(),
            'message' => $recommendedTourSection->is_active ? 'เปิดใช้งานแล้ว' : 'ปิดใช้งานแล้ว',
        ]);
    }

    /**
     * Reorder sections
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:recommended_tour_sections,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->items as $item) {
            RecommendedTourSection::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'เรียงลำดับใหม่สำเร็จ',
        ]);
    }

    /**
     * Preview tours for a saved section
     */
    public function preview(RecommendedTourSection $recommendedTourSection): JsonResponse
    {
        $tours = $recommendedTourSection->getTours();
        $tours->load(['transports.transport', 'periods', 'country']);

        return response()->json([
            'success' => true,
            'data' => [
                'section' => $recommendedTourSection,
                'tours' => $tours->map(fn ($t) => $this->formatTourForDisplay($t)),
                'total' => $tours->count(),
            ],
        ]);
    }

    /**
     * Preview conditions without saving
     */
    public function previewConditions(Request $request): JsonResponse
    {
        $request->validate([
            'conditions' => 'nullable|array',
            'display_limit' => 'nullable|integer|min:1|max:50',
            'sort_by' => 'nullable|string',
        ]);

        $section = new RecommendedTourSection([
            'conditions' => $request->conditions ?? [],
            'display_limit' => $request->display_limit ?? 8,
            'sort_by' => $request->sort_by ?? 'popular',
        ]);

        $tours = $section->getTours();
        $tours->load(['transports.transport', 'periods', 'country']);

        return response()->json([
            'success' => true,
            'data' => [
                'tours' => $tours->map(fn ($t) => $this->formatTourForDisplay($t)),
                'total' => $tours->count(),
            ],
        ]);
    }

    /**
     * Get condition options for the admin form
     */
    public function getConditionOptions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'condition_types' => RecommendedTourSection::CONDITION_TYPES,
                'sort_options' => RecommendedTourSection::SORT_OPTIONS,
                'display_modes' => RecommendedTourSetting::DISPLAY_MODES,
                'countries' => Country::orderBy('name_th')->get(['id', 'name_th', 'name_en', 'iso2']),
                'regions' => Tour::REGIONS,
                'wholesalers' => Wholesaler::where('is_active', true)->get(['id', 'name', 'code']),
                'tour_types' => Tour::TOUR_TYPES,
            ],
        ]);
    }

    // ==========================================
    // Admin Settings
    // ==========================================

    /**
     * Get global settings
     */
    public function getSettings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => RecommendedTourSetting::getSettings(),
        ]);
    }

    /**
     * Update global settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'display_mode' => 'sometimes|string|in:ordered,random,weighted_random,schedule',
            'title' => 'sometimes|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
            'cache_minutes' => 'sometimes|integer|min:0|max:1440',
        ]);

        $settings = RecommendedTourSetting::getSettings();
        $settings->update($validated);

        return response()->json([
            'success' => true,
            'data' => $settings->fresh(),
            'message' => 'อัปเดตการตั้งค่าสำเร็จ',
        ]);
    }

    // ==========================================
    // Public API (for tour-web)
    // ==========================================

    /**
     * Get recommended tours for public display
     * Returns a single section based on display_mode
     */
    public function publicShow(): JsonResponse
    {
        $settings = RecommendedTourSetting::getSettings();

        if (!$settings->is_active) {
            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        }

        $section = $this->resolveSection($settings);

        if (!$section) {
            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        }

        $tours = $section->getTours();
        $tours->load(['transports.transport', 'periods', 'country']);

        return response()->json([
            'success' => true,
            'data' => [
                'title' => $settings->title,
                'subtitle' => $settings->subtitle,
                'section_name' => $section->name,
                'tours' => $tours->map(fn ($t) => $this->formatTourForDisplay($t)),
            ],
        ]);
    }

    /**
     * Resolve which section to display based on display_mode
     */
    private function resolveSection(RecommendedTourSetting $settings): ?RecommendedTourSection
    {
        $query = RecommendedTourSection::active();

        // Apply schedule filter if mode is schedule
        if ($settings->display_mode === 'schedule') {
            $query->scheduled();
        }

        $sections = $query->ordered()->get();

        if ($sections->isEmpty()) {
            return null;
        }

        switch ($settings->display_mode) {
            case 'random':
                return $sections->random();

            case 'weighted_random':
                return $this->weightedRandom($sections);

            case 'schedule':
                // Return first valid scheduled section
                return $sections->first();

            case 'ordered':
            default:
                return $sections->first();
        }
    }

    /**
     * Weighted random selection
     */
    private function weightedRandom($sections): RecommendedTourSection
    {
        $totalWeight = $sections->sum('weight');
        $random = mt_rand(1, max($totalWeight, 1));
        $cumulative = 0;

        foreach ($sections as $section) {
            $cumulative += $section->weight;
            if ($random <= $cumulative) {
                return $section;
            }
        }

        return $sections->last();
    }

    /**
     * Format a tour for public display
     */
    private function formatTourForDisplay(Tour $tour): array
    {
        $airlineTransport = $tour->transports
            ->where('transport_type', 'outbound')
            ->first();
        $airline = $airlineTransport
            ? ($airlineTransport->transport?->name ?? $airlineTransport->transport_name)
            : null;

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
            'view_count' => $tour->view_count ?? 0,
            'hotel_star' => $tour->hotel_star,
        ];
    }
}
