<?php

namespace App\Http\Controllers;

use App\Models\InternationalTourSetting;
use App\Models\Country;
use App\Models\Tour;
use App\Models\Transport;
use App\Models\Wholesaler;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InternationalTourSettingController extends Controller
{
    /**
     * List all settings
     */
    public function index()
    {
        $settings = InternationalTourSetting::orderBy('sort_order')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Create a new setting
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:international_tour_settings,slug',
            'description' => 'nullable|string',
            'conditions' => 'nullable|array',
            'display_limit' => 'integer|min:1|max:200',
            'per_page' => 'integer|min:5|max:50',
            'sort_by' => 'string|in:popular,price_asc,price_desc,newest,departure_date',
            'show_periods' => 'boolean',
            'max_periods_display' => 'integer|min:1|max:20',
            'show_transport' => 'boolean',
            'show_hotel_star' => 'boolean',
            'show_meal_count' => 'boolean',
            'show_commission' => 'boolean',
            'filter_country' => 'boolean',
            'filter_city' => 'boolean',
            'filter_search' => 'boolean',
            'filter_airline' => 'boolean',
            'filter_departure_month' => 'boolean',
            'filter_price_range' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $setting = InternationalTourSetting::create($validated);

        return response()->json([
            'success' => true,
            'data' => $setting,
            'message' => 'สร้างการตั้งค่าสำเร็จ',
        ], 201);
    }

    /**
     * Show a single setting
     */
    public function show(InternationalTourSetting $internationalTourSetting)
    {
        return response()->json([
            'success' => true,
            'data' => $internationalTourSetting,
        ]);
    }

    /**
     * Update a setting
     */
    public function update(Request $request, InternationalTourSetting $internationalTourSetting)
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'slug' => 'string|max:255|unique:international_tour_settings,slug,' . $internationalTourSetting->id,
            'description' => 'nullable|string',
            'conditions' => 'nullable|array',
            'display_limit' => 'integer|min:1|max:200',
            'per_page' => 'integer|min:5|max:50',
            'sort_by' => 'string|in:popular,price_asc,price_desc,newest,departure_date',
            'show_periods' => 'boolean',
            'max_periods_display' => 'integer|min:1|max:20',
            'show_transport' => 'boolean',
            'show_hotel_star' => 'boolean',
            'show_meal_count' => 'boolean',
            'show_commission' => 'boolean',
            'filter_country' => 'boolean',
            'filter_city' => 'boolean',
            'filter_search' => 'boolean',
            'filter_airline' => 'boolean',
            'filter_departure_month' => 'boolean',
            'filter_price_range' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $internationalTourSetting->update($validated);

        return response()->json([
            'success' => true,
            'data' => $internationalTourSetting->fresh(),
            'message' => 'อัปเดตการตั้งค่าสำเร็จ',
        ]);
    }

    /**
     * Delete a setting
     */
    public function destroy(InternationalTourSetting $internationalTourSetting)
    {
        $internationalTourSetting->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบการตั้งค่าสำเร็จ',
        ]);
    }

    /**
     * Toggle active status
     */
    public function toggleStatus(InternationalTourSetting $internationalTourSetting)
    {
        $internationalTourSetting->update([
            'is_active' => !$internationalTourSetting->is_active,
        ]);

        return response()->json([
            'success' => true,
            'data' => $internationalTourSetting->fresh(),
            'message' => $internationalTourSetting->is_active ? 'เปิดใช้งานแล้ว' : 'ปิดใช้งานแล้ว',
        ]);
    }

    /**
     * Get condition options for the editor
     */
    public function getConditionOptions()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'condition_types' => InternationalTourSetting::CONDITION_TYPES,
                'sort_options' => InternationalTourSetting::SORT_OPTIONS,
                'countries' => Country::where('is_active', true)
                    ->where('id', '!=', 8) // Exclude Thailand
                    ->orderBy('name_th')
                    ->get(['id', 'name_th', 'name_en', 'iso2', 'region']),
                'regions' => Tour::REGIONS,
                'wholesalers' => Wholesaler::where('is_active', true)->get(['id', 'name', 'code']),
                'tour_types' => Tour::TOUR_TYPES,
                'airlines' => Transport::active()->airlines()->orderBy('name')->get(['id', 'code', 'name', 'image']),
            ],
        ]);
    }

    /**
     * Preview tours with given conditions (before saving)
     */
    public function previewConditions(Request $request)
    {
        $setting = new InternationalTourSetting([
            'conditions' => $request->input('conditions', []),
            'sort_by' => $request->input('sort_by', 'popular'),
            'display_limit' => $request->input('display_limit', 20),
            'per_page' => 10,
            'max_periods_display' => 6,
        ]);

        $query = $setting->getBaseQuery();
        $count = $query->count();

        $tours = $query
            ->with('primaryCountry:id,name_th,name_en,iso2')
            ->limit(10)
            ->get()
            ->map(function ($tour) {
                return [
                    'id' => $tour->id,
                    'title' => $tour->title,
                    'tour_code' => $tour->tour_code,
                    'country' => $tour->primaryCountry ? [
                        'id' => $tour->primaryCountry->id,
                        'name' => $tour->primaryCountry->name_th,
                        'iso2' => $tour->primaryCountry->iso2,
                    ] : null,
                    'days' => $tour->duration_days,
                    'nights' => $tour->duration_nights,
                    'price' => $tour->min_price,
                    'departure_date' => $tour->next_departure_date,
                    'image_url' => $tour->cover_image_url,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'total_count' => $count,
                'preview_tours' => $tours,
            ],
        ]);
    }

    /**
     * Get the active setting for public display
     */
    public function getPublicSetting()
    {
        $setting = InternationalTourSetting::active()->orderBy('sort_order')->first();

        if (!$setting) {
            // Return default setting
            return response()->json([
                'success' => true,
                'data' => [
                    'display_limit' => 50,
                    'per_page' => 10,
                    'sort_by' => 'popular',
                    'show_periods' => true,
                    'max_periods_display' => 6,
                    'show_transport' => true,
                    'show_hotel_star' => true,
                    'show_meal_count' => true,
                    'show_commission' => false,
                    'filter_country' => true,
                    'filter_city' => true,
                    'filter_search' => true,
                    'filter_airline' => true,
                    'filter_departure_month' => true,
                    'filter_price_range' => true,
                    'sort_options' => InternationalTourSetting::SORT_OPTIONS,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'display_limit' => $setting->display_limit,
                'per_page' => $setting->per_page,
                'sort_by' => $setting->sort_by,
                'show_periods' => $setting->show_periods,
                'max_periods_display' => $setting->max_periods_display,
                'show_transport' => $setting->show_transport,
                'show_hotel_star' => $setting->show_hotel_star,
                'show_meal_count' => $setting->show_meal_count,
                'show_commission' => $setting->show_commission,
                'filter_country' => $setting->filter_country,
                'filter_city' => $setting->filter_city,
                'filter_search' => $setting->filter_search,
                'filter_airline' => $setting->filter_airline,
                'filter_departure_month' => $setting->filter_departure_month,
                'filter_price_range' => $setting->filter_price_range,
                'sort_options' => InternationalTourSetting::SORT_OPTIONS,
            ],
        ]);
    }
}
