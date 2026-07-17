<?php

namespace App\Http\Controllers;

use App\Models\InternationalTourSetting;
use App\Models\InternationalTourCountryCover;
use App\Models\Country;
use App\Models\Tour;
use App\Models\Transport;
use App\Models\Wholesaler;
use App\Services\CloudflareImagesService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InternationalTourSettingController extends Controller
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
        $settings = InternationalTourSetting::with(['countryCovers.country:id,name_en,name_th,iso2,flag_emoji'])
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
            'filter_festival' => 'boolean',
            'filter_promotion' => 'boolean',
            'filter_theme' => 'boolean',
            'filter_special_highlight' => 'boolean',
            'filter_advanced' => 'boolean',
            'hero_text' => 'nullable|string|max:255',
            'pagination_mode' => 'string|in:page,load_more',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
            'show_sidebar' => 'boolean',
            'sidebar_show_blog_posts' => 'boolean',
            'sidebar_show_popular_tours' => 'boolean',
            'sidebar_show_contact' => 'boolean',
            'sidebar_blog_posts_limit' => 'integer|min:1|max:20',
            'sidebar_popular_tours_limit' => 'integer|min:1|max:20',
            'sidebar_blog_posts_title' => 'nullable|string|max:100',
            'sidebar_popular_tours_title' => 'nullable|string|max:100',
            'sidebar_contact_title' => 'nullable|string|max:100',
            'sidebar_contact_phone' => 'nullable|string|max:50',
            'sidebar_contact_line' => 'nullable|string|max:100',
            'sidebar_contact_text' => 'nullable|string|max:255',
            'sidebar_show_portfolios' => 'boolean',
            'sidebar_portfolios_limit' => 'integer|min:1|max:20',
            'sidebar_portfolios_title' => 'nullable|string|max:100',
            'sidebar_popular_tours_mode' => 'nullable|string|in:popular,latest,manual',
            'sidebar_popular_tours_codes' => 'nullable|string|max:1000',
            'detail_country_sidebar_enabled' => 'boolean',
            'detail_country_sidebar_title' => 'nullable|string|max:100',
            'detail_country_sidebar_limit' => 'integer|min:1|max:20',
            'detail_country_sidebar_sort' => 'nullable|string|in:same_city,popular,price_asc,latest',
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
        $internationalTourSetting->load(['countryCovers.country:id,name_en,name_th,iso2,flag_emoji']);
        
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
            'filter_festival' => 'boolean',
            'filter_promotion' => 'boolean',
            'filter_theme' => 'boolean',
            'filter_special_highlight' => 'boolean',
            'filter_advanced' => 'boolean',
            'hero_text' => 'nullable|string|max:255',
            'pagination_mode' => 'string|in:page,load_more',
            'cover_image_position' => 'string|in:top,center,bottom,left top,left center,left bottom,right top,right center,right bottom',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
            'show_sidebar' => 'boolean',
            'sidebar_show_blog_posts' => 'boolean',
            'sidebar_show_popular_tours' => 'boolean',
            'sidebar_show_contact' => 'boolean',
            'sidebar_blog_posts_limit' => 'integer|min:1|max:20',
            'sidebar_popular_tours_limit' => 'integer|min:1|max:20',
            'sidebar_blog_posts_title' => 'nullable|string|max:100',
            'sidebar_popular_tours_title' => 'nullable|string|max:100',
            'sidebar_contact_title' => 'nullable|string|max:100',
            'sidebar_contact_phone' => 'nullable|string|max:50',
            'sidebar_contact_line' => 'nullable|string|max:100',
            'sidebar_contact_text' => 'nullable|string|max:255',
            'sidebar_show_portfolios' => 'boolean',
            'sidebar_portfolios_limit' => 'integer|min:1|max:20',
            'sidebar_portfolios_title' => 'nullable|string|max:100',
            'sidebar_popular_tours_mode' => 'nullable|string|in:popular,latest,manual',
            'sidebar_popular_tours_codes' => 'nullable|string|max:1000',
            'detail_country_sidebar_enabled' => 'boolean',
            'detail_country_sidebar_title' => 'nullable|string|max:100',
            'detail_country_sidebar_limit' => 'integer|min:1|max:20',
            'detail_country_sidebar_sort' => 'nullable|string|in:same_city,popular,price_asc,latest',
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
        // Safety (2026-07-17): abort if Cloudflare delete fails so we don't orphan the file.
        if ($internationalTourSetting->cover_image_cf_id && $this->cloudflare->isConfigured()) {
            $deleted = $this->cloudflare->delete($internationalTourSetting->cover_image_cf_id);
            if (!$deleted) {
                \Illuminate\Support\Facades\Log::warning('InternationalTourSettingController::destroy: Cloudflare delete failed, aborting', [
                    'setting_id' => $internationalTourSetting->id,
                    'cf_id' => $internationalTourSetting->cover_image_cf_id,
                ]);
                return response()->json(['success' => false, 'message' => 'ลบไฟล์รูปจาก Cloudflare ไม่สำเร็จ — โปรดลองใหม่อีกครั้ง'], 500);
            }
        }

        $internationalTourSetting->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบการตั้งค่าสำเร็จ',
        ]);
    }

    /**
     * Upload cover image
     */
    public function uploadCoverImage(Request $request, InternationalTourSetting $internationalTourSetting)
    {
        $request->validate([
            'cover_image' => 'required|file|mimes:jpeg,jpg,png,gif,webp|max:10240',
        ]);

        $file = $request->file('cover_image');

        // ลบภาพเดิมถ้ามี
        if ($internationalTourSetting->cover_image_cf_id) {
            $this->cloudflare->delete($internationalTourSetting->cover_image_cf_id);
        }

        $customId = 'intl-tour-cover-' . $internationalTourSetting->id . '-' . time();
        $metadata = [
            'type' => 'international_tour_cover',
            'setting_id' => $internationalTourSetting->id,
        ];

        $result = $this->cloudflare->uploadFromFile($file, $customId, $metadata);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'อัปโหลดภาพไม่สำเร็จ',
            ], 500);
        }

        $url = $this->cloudflare->getDisplayUrl($result['id'], 'public');

        $internationalTourSetting->update([
            'cover_image_url' => $url,
            'cover_image_cf_id' => $result['id'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $internationalTourSetting->fresh(),
            'message' => 'อัปโหลดภาพ Cover สำเร็จ',
        ]);
    }

    /**
     * Delete cover image
     */
    public function deleteCoverImage(InternationalTourSetting $internationalTourSetting)
    {
        // Safety (2026-07-17): abort if Cloudflare delete fails so we don't orphan the file.
        if ($internationalTourSetting->cover_image_cf_id && $this->cloudflare->isConfigured()) {
            $deleted = $this->cloudflare->delete($internationalTourSetting->cover_image_cf_id);
            if (!$deleted) {
                \Illuminate\Support\Facades\Log::warning('InternationalTourSettingController::deleteCoverImage: Cloudflare delete failed, aborting', [
                    'setting_id' => $internationalTourSetting->id,
                    'cf_id' => $internationalTourSetting->cover_image_cf_id,
                ]);
                return response()->json(['success' => false, 'message' => 'ลบไฟล์รูปจาก Cloudflare ไม่สำเร็จ — โปรดลองใหม่อีกครั้ง'], 500);
            }
        }

        $internationalTourSetting->update([
            'cover_image_url' => null,
            'cover_image_cf_id' => null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $internationalTourSetting->fresh(),
            'message' => 'ลบภาพ Cover สำเร็จ',
        ]);
    }

    /**
     * Upload country cover image
     */
    public function uploadCountryCover(Request $request, InternationalTourSetting $internationalTourSetting, $countryId)
    {
        $request->validate([
            'image' => 'required|file|mimes:jpeg,jpg,png,gif,webp|max:10240',
            'image_position' => 'nullable|string',
            'alt_text' => 'nullable|string|max:255',
        ]);

        $country = Country::findOrFail($countryId);
        $file = $request->file('image');

        // Find existing cover or create new one
        $cover = InternationalTourCountryCover::firstOrNew([
            'setting_id' => $internationalTourSetting->id,
            'country_id' => $country->id,
        ]);

        // Delete old image if exists
        if ($cover->cloudflare_id) {
            $this->cloudflare->delete($cover->cloudflare_id);
        }

        // Upload new image
        $customId = 'intl-tour-country-cover-' . $internationalTourSetting->id . '-' . $country->id . '-' . time();
        $metadata = [
            'type' => 'international_tour_country_cover',
            'setting_id' => $internationalTourSetting->id,
            'country_id' => $country->id,
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
            'message' => 'อัปโหลดภาพ Cover ประเทศสำเร็จ',
        ]);
    }

    /**
     * Delete country cover image
     */
    public function deleteCountryCover(InternationalTourSetting $internationalTourSetting, $countryId)
    {
        $cover = InternationalTourCountryCover::where('setting_id', $internationalTourSetting->id)
            ->where('country_id', $countryId)
            ->first();

        if (!$cover) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบภาพ Cover ประเทศ',
            ], 404);
        }

        if ($cover->cloudflare_id && $this->cloudflare->isConfigured()) {
            $deleted = $this->cloudflare->delete($cover->cloudflare_id);
            if (!$deleted) {
                \Illuminate\Support\Facades\Log::warning('InternationalTourSettingController::deleteCountryCover: Cloudflare delete failed, aborting', [
                    'cover_id' => $cover->id,
                    'cf_id' => $cover->cloudflare_id,
                ]);
                return response()->json(['success' => false, 'message' => 'ลบไฟล์รูปจาก Cloudflare ไม่สำเร็จ — โปรดลองใหม่อีกครั้ง'], 500);
            }
        }

        $cover->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบภาพ Cover ประเทศสำเร็จ',
        ]);
    }

    /**
     * Update country cover position
     */
    public function updateCountryCoverPosition(Request $request, InternationalTourSetting $internationalTourSetting, $countryId)
    {
        $request->validate([
            'image_position' => 'required|string|in:top,center,bottom,left top,left center,left bottom,right top,right center,right bottom',
        ]);

        $cover = InternationalTourCountryCover::where('setting_id', $internationalTourSetting->id)
            ->where('country_id', $countryId)
            ->first();

        if (!$cover) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบภาพ Cover ประเทศ',
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
     * Update country cover hero text
     */
    public function updateCountryCoverHeroText(Request $request, InternationalTourSetting $internationalTourSetting, $countryId)
    {
        $request->validate([
            'hero_text' => 'nullable|string|max:255',
        ]);

        $cover = InternationalTourCountryCover::where('setting_id', $internationalTourSetting->id)
            ->where('country_id', $countryId)
            ->first();

        if (!$cover) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบภาพ Cover ประเทศ',
            ], 404);
        }

        $cover->update([
            'hero_text' => $request->hero_text,
        ]);

        return response()->json([
            'success' => true,
            'data' => $cover,
            'message' => 'อัปเดตข้อความ Hero สำเร็จ',
        ]);
    }

    /**
     * Update country cover pinned tour codes
     */
    public function updateCountryCoverPinnedTours(Request $request, InternationalTourSetting $internationalTourSetting, $countryId)
    {
        $request->validate([
            'pinned_tour_codes' => 'nullable|string|max:1000',
        ]);

        $cover = InternationalTourCountryCover::where('setting_id', $internationalTourSetting->id)
            ->where('country_id', $countryId)
            ->first();

        if (!$cover) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบภาพ Cover ประเทศ',
            ], 404);
        }

        $cover->update([
            'pinned_tour_codes' => $request->pinned_tour_codes,
        ]);

        return response()->json([
            'success' => true,
            'data' => $cover,
            'message' => 'อัปเดตทัวร์ปักหมุดสำเร็จ',
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
                    'filter_festival' => true,
                    'filter_promotion' => true,
                    'filter_theme' => true,
                    'filter_special_highlight' => true,
                    'filter_advanced' => true,
                    'sort_options' => InternationalTourSetting::SORT_OPTIONS,
                    'cover_image_url' => null,
                    'cover_image_position' => 'center',
                    'hero_text' => null,
                    'detail_country_sidebar_enabled' => true,
                    'detail_country_sidebar_title' => null,
                    'detail_country_sidebar_limit' => 8,
                    'detail_country_sidebar_sort' => 'same_city',
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
                'filter_festival' => $setting->filter_festival ?? true,
                'filter_promotion' => $setting->filter_promotion ?? true,
                'filter_theme' => $setting->filter_theme ?? true,
                'filter_special_highlight' => $setting->filter_special_highlight ?? true,
                'filter_advanced' => $setting->filter_advanced ?? true,
                'sort_options' => InternationalTourSetting::SORT_OPTIONS,
                'cover_image_url' => $setting->cover_image_url,
                'cover_image_position' => $setting->cover_image_position ?? 'center',
                'hero_text' => $setting->hero_text,
                'detail_country_sidebar_enabled' => $setting->detail_country_sidebar_enabled ?? true,
                'detail_country_sidebar_title' => $setting->detail_country_sidebar_title,
                'detail_country_sidebar_limit' => $setting->detail_country_sidebar_limit ?? 8,
                'detail_country_sidebar_sort' => $setting->detail_country_sidebar_sort ?? 'same_city',
            ],
        ]);
    }
}
