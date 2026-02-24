<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\DomesticTourCityCover;
use App\Models\DomesticTourSetting;
use App\Models\Tour;
use App\Models\Transport;
use App\Models\Wholesaler;
use App\Services\CloudflareImagesService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DomesticTourSettingController extends Controller
{
    protected CloudflareImagesService $cloudflare;

    public function __construct(CloudflareImagesService $cloudflare)
    {
        $this->cloudflare = $cloudflare;
    }

    /**
     * List all settings
     */
    public function index()
    {
        $settings = DomesticTourSetting::with(['cityCovers.city:id,name_en,name_th,slug'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

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
            'slug' => 'nullable|string|max:255|unique:domestic_tour_settings,slug',
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
            'filter_search' => 'boolean',
            'filter_city' => 'boolean',
            'filter_airline' => 'boolean',
            'filter_departure_month' => 'boolean',
            'filter_price_range' => 'boolean',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $setting = DomesticTourSetting::create($validated);

        return response()->json([
            'success' => true,
            'data' => $setting,
            'message' => 'สร้างการตั้งค่าสำเร็จ',
        ], 201);
    }

    /**
     * Show a single setting
     */
    public function show(DomesticTourSetting $domesticTourSetting)
    {
        $domesticTourSetting->load(['cityCovers.city:id,name_en,name_th,slug']);

        return response()->json([
            'success' => true,
            'data' => $domesticTourSetting,
        ]);
    }

    /**
     * Update a setting
     */
    public function update(Request $request, DomesticTourSetting $domesticTourSetting)
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'slug' => 'nullable|string|max:255|unique:domestic_tour_settings,slug,' . $domesticTourSetting->id,
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
            'filter_search' => 'boolean',
            'filter_city' => 'boolean',
            'filter_airline' => 'boolean',
            'filter_departure_month' => 'boolean',
            'filter_price_range' => 'boolean',
            'cover_image_position' => 'string|in:top,center,bottom,left top,left center,left bottom,right top,right center,right bottom',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $domesticTourSetting->update($validated);

        return response()->json([
            'success' => true,
            'data' => $domesticTourSetting->fresh(),
            'message' => 'อัปเดตการตั้งค่าสำเร็จ',
        ]);
    }

    /**
     * Delete a setting
     */
    public function destroy(DomesticTourSetting $domesticTourSetting)
    {
        if ($domesticTourSetting->cover_image_cf_id) {
            $this->cloudflare->delete($domesticTourSetting->cover_image_cf_id);
        }

        $domesticTourSetting->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบการตั้งค่าสำเร็จ',
        ]);
    }

    /**
     * Upload cover image
     */
    public function uploadCoverImage(Request $request, DomesticTourSetting $domesticTourSetting)
    {
        $request->validate([
            'cover_image' => 'required|file|mimes:jpeg,jpg,png,gif,webp|max:10240',
        ]);

        $file = $request->file('cover_image');

        if ($domesticTourSetting->cover_image_cf_id) {
            $this->cloudflare->delete($domesticTourSetting->cover_image_cf_id);
        }

        $customId = 'domestic-tour-cover-' . $domesticTourSetting->id . '-' . time();
        $metadata = [
            'type' => 'domestic_tour_cover',
            'setting_id' => $domesticTourSetting->id,
        ];

        $result = $this->cloudflare->uploadFromFile($file, $customId, $metadata);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'อัปโหลดภาพไม่สำเร็จ',
            ], 500);
        }

        $url = $this->cloudflare->getDisplayUrl($result['id'], 'public');

        $domesticTourSetting->update([
            'cover_image_url' => $url,
            'cover_image_cf_id' => $result['id'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $domesticTourSetting->fresh(),
            'message' => 'อัปโหลดภาพ Cover สำเร็จ',
        ]);
    }

    /**
     * Delete cover image
     */
    public function deleteCoverImage(DomesticTourSetting $domesticTourSetting)
    {
        if ($domesticTourSetting->cover_image_cf_id) {
            $this->cloudflare->delete($domesticTourSetting->cover_image_cf_id);
        }

        $domesticTourSetting->update([
            'cover_image_url' => null,
            'cover_image_cf_id' => null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $domesticTourSetting->fresh(),
            'message' => 'ลบภาพ Cover สำเร็จ',
        ]);
    }

    /**
     * Toggle active status
     */
    public function toggleStatus(DomesticTourSetting $domesticTourSetting)
    {
        $domesticTourSetting->update([
            'is_active' => !$domesticTourSetting->is_active,
        ]);

        return response()->json([
            'success' => true,
            'data' => $domesticTourSetting->fresh(),
            'message' => $domesticTourSetting->is_active ? 'เปิดใช้งานแล้ว' : 'ปิดใช้งานแล้ว',
        ]);
    }

    /**
     * Get condition options for the editor
     */
    public function getConditionOptions()
    {
        // Get cities in Thailand (country_id = 8 for Thailand)
        $cities = City::where('country_id', DomesticTourSetting::THAILAND_ID)
            ->where('is_active', true)
            ->orderBy('name_th')
            ->get(['id', 'name_en', 'name_th', 'slug']);

        return response()->json([
            'success' => true,
            'data' => [
                'condition_types' => DomesticTourSetting::CONDITION_TYPES,
                'sort_options' => DomesticTourSetting::SORT_OPTIONS,
                'wholesalers' => Wholesaler::where('is_active', true)->get(['id', 'name', 'code']),
                'tour_types' => Tour::TOUR_TYPES,
                'airlines' => Transport::active()->orderBy('name')->get(['id', 'code', 'name', 'image']),
                'cities' => $cities,
            ],
        ]);
    }

    /**
     * Preview tours with given conditions (before saving)
     */
    public function previewConditions(Request $request)
    {
        $setting = new DomesticTourSetting([
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
     * Upload city cover image
     */
    public function uploadCityCover(Request $request, DomesticTourSetting $domesticTourSetting, $cityId)
    {
        $request->validate([
            'image' => 'required|file|mimes:jpeg,jpg,png,gif,webp|max:10240',
            'image_position' => 'nullable|string',
            'alt_text' => 'nullable|string|max:255',
        ]);

        $city = City::findOrFail($cityId);
        $file = $request->file('image');

        // Find existing cover or create new one
        $cover = DomesticTourCityCover::firstOrNew([
            'setting_id' => $domesticTourSetting->id,
            'city_id' => $city->id,
        ]);

        // Delete old image if exists
        if ($cover->cloudflare_id) {
            $this->cloudflare->delete($cover->cloudflare_id);
        }

        // Upload new image
        $customId = 'domestic-tour-city-cover-' . $domesticTourSetting->id . '-' . $city->id . '-' . time();
        $metadata = [
            'type' => 'domestic_tour_city_cover',
            'setting_id' => $domesticTourSetting->id,
            'city_id' => $city->id,
        ];

        $result = $this->cloudflare->uploadFromFile($file, $customId, $metadata);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'อัปโหลดภาพไม่สำเร็จ',
            ], 500);
        }

        $url = $this->cloudflare->getDisplayUrl($result['id'], 'public');

        $cover->fill([
            'image_url' => $url,
            'cloudflare_id' => $result['id'],
            'image_position' => $request->image_position ?? 'center',
            'alt_text' => $request->alt_text,
        ]);
        $cover->save();

        return response()->json([
            'success' => true,
            'image_url' => $url,
            'cloudflare_id' => $result['id'],
            'message' => 'อัปโหลดภาพ Cover จังหวัดสำเร็จ',
        ]);
    }

    /**
     * Delete city cover image
     */
    public function deleteCityCover(DomesticTourSetting $domesticTourSetting, $cityId)
    {
        $cover = DomesticTourCityCover::where('setting_id', $domesticTourSetting->id)
            ->where('city_id', $cityId)
            ->first();

        if (!$cover) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบภาพ Cover จังหวัด',
            ], 404);
        }

        if ($cover->cloudflare_id) {
            $this->cloudflare->delete($cover->cloudflare_id);
        }

        $cover->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบภาพ Cover จังหวัดสำเร็จ',
        ]);
    }

    /**
     * Update city cover position
     */
    public function updateCityCoverPosition(Request $request, DomesticTourSetting $domesticTourSetting, $cityId)
    {
        $request->validate([
            'image_position' => 'required|string|in:top,center,bottom,left top,left center,left bottom,right top,right center,right bottom',
        ]);

        $cover = DomesticTourCityCover::where('setting_id', $domesticTourSetting->id)
            ->where('city_id', $cityId)
            ->first();

        if (!$cover) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบภาพ Cover จังหวัด',
            ], 404);
        }

        $cover->update([
            'image_position' => $request->image_position,
        ]);

        return response()->json([
            'success' => true,
            'data' => $cover,
            'message' => 'อัปเดตตำแหน่งภาพสำเร็จ',
        ]);
    }

    /**
     * Get the active setting for public display
     */
    public function getPublicSetting()
    {
        $setting = DomesticTourSetting::active()->orderBy('sort_order')->first();

        if (!$setting) {
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
                    'filter_search' => true,
                    'filter_city' => true,
                    'filter_airline' => true,
                    'filter_departure_month' => true,
                    'filter_price_range' => true,
                    'sort_options' => DomesticTourSetting::SORT_OPTIONS,
                    'cover_image_url' => null,
                    'cover_image_position' => 'center',
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
                'filter_search' => $setting->filter_search,
                'filter_city' => $setting->filter_city,
                'filter_airline' => $setting->filter_airline,
                'filter_departure_month' => $setting->filter_departure_month,
                'filter_price_range' => $setting->filter_price_range,
                'sort_options' => DomesticTourSetting::SORT_OPTIONS,
                'cover_image_url' => $setting->cover_image_url,
                'cover_image_position' => $setting->cover_image_position ?? 'center',
            ],
        ]);
    }
}
