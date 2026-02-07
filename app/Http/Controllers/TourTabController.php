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
            $validated['slug'] = Str::slug($validated['name']);
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
            
            // Format tours for preview
            $formattedTours = $tours->map(function ($tour) {
                $minPeriod = $tour->periods()
                    ->where('departure_date', '>=', now()->toDateString())
                    ->where('status', 'active')
                    ->orderBy('price_adult')
                    ->first();

                return [
                    'id' => $tour->id,
                    'title' => $tour->title,
                    'tour_code' => $tour->tour_code,
                    'country' => $tour->country?->name_th ?? $tour->country?->name_en,
                    'days' => $tour->days,
                    'nights' => $tour->nights,
                    'price' => $minPeriod?->price_adult,
                    'departure_date' => $minPeriod?->departure_date,
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
    public function publicList(Request $request): JsonResponse
    {
        $tabs = TourTab::active()->ordered()->get();

        $result = $tabs->map(function ($tab) {
            $tours = $tab->getTours();
            
            // Format tours for display
            $formattedTours = $tours->map(function ($tour) {
                $minPeriod = $tour->periods()
                    ->where('departure_date', '>=', now()->toDateString())
                    ->where('status', 'active')
                    ->orderBy('price_adult')
                    ->first();

                $nextDeparture = $tour->periods()
                    ->where('departure_date', '>=', now()->toDateString())
                    ->where('status', 'active')
                    ->orderBy('departure_date')
                    ->first();

                return [
                    'id' => $tour->id,
                    'slug' => $tour->slug,
                    'title' => $tour->title,
                    'tour_code' => $tour->tour_code,
                    'country' => [
                        'id' => $tour->country_id,
                        'name' => $tour->country?->name_th ?? $tour->country?->name_en,
                        'iso2' => $tour->country?->iso2,
                    ],
                    'days' => $tour->days,
                    'nights' => $tour->nights,
                    'price' => $minPeriod?->price_adult,
                    'original_price' => $minPeriod?->original_price,
                    'discount_percent' => $minPeriod?->discount_percent,
                    'departure_date' => $nextDeparture?->departure_date,
                    'airline' => $tour->airline,
                    'image_url' => $tour->cover_image_url,
                    'badge' => $tour->badge,
                    'rating' => $tour->rating,
                    'review_count' => $tour->review_count,
                ];
            });

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

        // Format tours
        $formattedTours = $tours->map(function ($tour) {
            $minPeriod = $tour->periods()
                ->where('departure_date', '>=', now()->toDateString())
                ->where('status', 'active')
                ->orderBy('price_adult')
                ->first();

            $nextDeparture = $tour->periods()
                ->where('departure_date', '>=', now()->toDateString())
                ->where('status', 'active')
                ->orderBy('departure_date')
                ->first();

            return [
                'id' => $tour->id,
                'slug' => $tour->slug,
                'title' => $tour->title,
                'tour_code' => $tour->tour_code,
                'country' => [
                    'id' => $tour->country_id,
                    'name' => $tour->country?->name_th ?? $tour->country?->name_en,
                    'iso2' => $tour->country?->iso2,
                ],
                'days' => $tour->days,
                'nights' => $tour->nights,
                'price' => $minPeriod?->price_adult,
                'original_price' => $minPeriod?->original_price,
                'discount_percent' => $minPeriod?->discount_percent,
                'departure_date' => $nextDeparture?->departure_date,
                'airline' => $tour->airline,
                'image_url' => $tour->cover_image_url,
                'badge' => $tour->badge,
                'rating' => $tour->rating,
                'review_count' => $tour->review_count,
            ];
        });

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
