<?php

namespace App\Http\Controllers;

use App\Models\PopularCountrySetting;
use App\Models\PopularCountryItem;
use App\Models\Country;
use App\Models\Wholesaler;
use App\Services\CloudflareImagesService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PopularCountryController extends Controller
{
    protected CloudflareImagesService $cloudflare;

    public function __construct(CloudflareImagesService $cloudflare)
    {
        $this->cloudflare = $cloudflare;
    }

    /**
     * Display a listing of popular country settings.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PopularCountrySetting::query()->with('items.country');

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'sort_order');
        $sortDir = $request->get('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = min($request->get('per_page', 20), 100);
        $settings = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $settings->items(),
            'meta' => [
                'current_page' => $settings->currentPage(),
                'last_page' => $settings->lastPage(),
                'per_page' => $settings->perPage(),
                'total' => $settings->total(),
            ],
        ]);
    }

    /**
     * Get filter options for creating/editing settings
     */
    public function filterOptions(): JsonResponse
    {
        $options = PopularCountrySetting::getFilterOptions();

        // Add wholesalers
        $options['wholesalers'] = Wholesaler::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->toArray();

        // Add countries
        $options['countries'] = Country::where('is_active', true)
            ->orderBy('name_th')
            ->get(['id', 'iso2', 'name_en', 'name_th', 'flag_emoji', 'region'])
            ->toArray();

        // Add months
        $options['months'] = [
            1 => 'มกราคม',
            2 => 'กุมภาพันธ์',
            3 => 'มีนาคม',
            4 => 'เมษายน',
            5 => 'พฤษภาคม',
            6 => 'มิถุนายน',
            7 => 'กรกฎาคม',
            8 => 'สิงหาคม',
            9 => 'กันยายน',
            10 => 'ตุลาคม',
            11 => 'พฤศจิกายน',
            12 => 'ธันวาคม',
        ];

        return response()->json([
            'success' => true,
            'data' => $options,
        ]);
    }

    /**
     * Get public active popular countries (for tour-web)
     * Default endpoint: /api/popular-countries/public
     */
    public function publicList(Request $request): JsonResponse
    {
        $slug = $request->get('slug', 'homepage');

        $setting = PopularCountrySetting::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$setting) {
            // Fallback: Return top 6 countries by tour count
            return $this->getDefaultPopularCountries();
        }

        try {
            $countries = $setting->getPopularCountries();

            return response()->json([
                'success' => true,
                'data' => [
                    'setting' => [
                        'name' => $setting->name,
                        'slug' => $setting->slug,
                    ],
                    'countries' => $countries,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get popular countries', [
                'setting' => $setting->slug,
                'error' => $e->getMessage(),
            ]);

            return $this->getDefaultPopularCountries();
        }
    }

    /**
     * Get default popular countries (fallback)
     */
    protected function getDefaultPopularCountries(): JsonResponse
    {
        $countries = Country::where('is_active', true)
            ->withCount(['tours' => function ($query) {
                // Status 'active' = เปิดใช้งาน (tour UI has 3 statuses: draft, active, closed)
                // Exclude Sold Out tours (available_seats = 0)
                $query->where('status', 'active')
                      ->where('available_seats', '>', 0)
                      ->whereHas('periods', function ($q) {
                          $q->where('status', 'open')
                            ->where('start_date', '>=', now()->toDateString());
                      });
            }])
            ->having('tours_count', '>', 0)
            ->orderByDesc('tours_count')
            ->limit(6)
            ->get()
            ->map(function ($country) {
                return [
                    'id' => $country->id,
                    'iso2' => $country->iso2,
                    'iso3' => $country->iso3,
                    'name_en' => $country->name_en,
                    'name_th' => $country->name_th,
                    'slug' => $country->slug,
                    'region' => $country->region,
                    'flag_emoji' => $country->flag_emoji,
                    'tour_count' => $country->tours_count,
                    'display_name' => $country->name_th ?: $country->name_en,
                    'image_url' => null,
                    'alt_text' => null,
                    'title' => null,
                    'subtitle' => null,
                    'link_url' => null,
                ];
            })
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'setting' => [
                    'name' => 'Default',
                    'slug' => 'default',
                ],
                'countries' => $countries,
            ],
        ]);
    }

    /**
     * Store a new popular country setting.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:popular_country_settings,slug',
            'description' => 'nullable|string',
            'selection_mode' => ['required', Rule::in(['auto', 'manual'])],
            'filters' => 'nullable|array',
            'filters.wholesaler_ids' => 'nullable|array',
            'filters.wholesaler_ids.*' => 'integer|exists:wholesalers,id',
            'filters.themes' => 'nullable|array',
            'filters.regions' => 'nullable|array',
            'filters.min_price' => 'nullable|numeric|min:0',
            'filters.max_price' => 'nullable|numeric|min:0',
            'filters.hotel_star_min' => 'nullable|integer|min:1|max:5',
            'filters.hotel_star_max' => 'nullable|integer|min:1|max:5',
            'filters.duration_min' => 'nullable|integer|min:1',
            'filters.duration_max' => 'nullable|integer|min:1',
            'tour_conditions' => 'nullable|array',
            'tour_conditions.has_upcoming_periods' => 'nullable|boolean',
            'tour_conditions.travel_months' => 'nullable|array',
            'tour_conditions.travel_months.*' => 'integer|min:1|max:12',
            'tour_conditions.travel_date_from' => 'nullable|date',
            'tour_conditions.travel_date_to' => 'nullable|date',
            'tour_conditions.min_available_seats' => 'nullable|integer|min:1',
            'display_count' => 'nullable|integer|min:1|max:20',
            'min_tour_count' => 'nullable|integer|min:0',
            'sort_by' => ['nullable', Rule::in(['tour_count', 'name', 'manual'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'cache_minutes' => 'nullable|integer|min:1|max:1440',
            // Country items for manual mode
            'items' => 'nullable|array',
            'items.*.country_id' => 'required|integer|exists:countries,id',
            'items.*.display_name' => 'nullable|string|max:255',
            'items.*.alt_text' => 'nullable|string|max:255',
            'items.*.title' => 'nullable|string|max:255',
            'items.*.subtitle' => 'nullable|string|max:500',
            'items.*.link_url' => 'nullable|string|max:500',
            'items.*.sort_order' => 'nullable|integer',
            'items.*.is_active' => 'nullable|boolean',
        ]);

        // Generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
            
            // Ensure unique slug
            $originalSlug = $validated['slug'];
            $counter = 1;
            while (PopularCountrySetting::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $originalSlug . '-' . $counter++;
            }
        }

        // Set defaults
        $validated['display_count'] = $validated['display_count'] ?? 6;
        $validated['min_tour_count'] = $validated['min_tour_count'] ?? 1;
        $validated['sort_by'] = $validated['sort_by'] ?? 'tour_count';
        $validated['sort_direction'] = $validated['sort_direction'] ?? 'desc';
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['cache_minutes'] = $validated['cache_minutes'] ?? 60;

        // Set default tour conditions
        if (empty($validated['tour_conditions'])) {
            $validated['tour_conditions'] = [
                'has_upcoming_periods' => true,
            ];
        }

        DB::beginTransaction();
        try {
            // Create setting
            $items = $validated['items'] ?? [];
            unset($validated['items']);
            
            $setting = PopularCountrySetting::create($validated);

            // Create items if provided (both auto and manual modes can have custom display data)
            if (!empty($items)) {
                foreach ($items as $index => $itemData) {
                    $setting->items()->create([
                        'country_id' => $itemData['country_id'],
                        'display_name' => $itemData['display_name'] ?? null,
                        'alt_text' => $itemData['alt_text'] ?? null,
                        'title' => $itemData['title'] ?? null,
                        'subtitle' => $itemData['subtitle'] ?? null,
                        'link_url' => $itemData['link_url'] ?? null,
                        'sort_order' => $itemData['sort_order'] ?? $index,
                        'is_active' => $itemData['is_active'] ?? true,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'สร้างการตั้งค่าประเทศยอดนิยมสำเร็จ',
                'data' => $setting->load('items.country'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create popular country setting', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถสร้างการตั้งค่าได้: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified setting.
     */
    public function show(int $id): JsonResponse
    {
        $setting = PopularCountrySetting::with('items.country')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $setting,
        ]);
    }

    /**
     * Update the specified setting.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $setting = PopularCountrySetting::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('popular_country_settings')->ignore($id)],
            'description' => 'nullable|string',
            'selection_mode' => ['sometimes', Rule::in(['auto', 'manual'])],
            'filters' => 'nullable|array',
            'filters.wholesaler_ids' => 'nullable|array',
            'filters.wholesaler_ids.*' => 'integer|exists:wholesalers,id',
            'filters.themes' => 'nullable|array',
            'filters.regions' => 'nullable|array',
            'filters.min_price' => 'nullable|numeric|min:0',
            'filters.max_price' => 'nullable|numeric|min:0',
            'filters.hotel_star_min' => 'nullable|integer|min:1|max:5',
            'filters.hotel_star_max' => 'nullable|integer|min:1|max:5',
            'filters.duration_min' => 'nullable|integer|min:1',
            'filters.duration_max' => 'nullable|integer|min:1',
            'tour_conditions' => 'nullable|array',
            'tour_conditions.has_upcoming_periods' => 'nullable|boolean',
            'tour_conditions.travel_months' => 'nullable|array',
            'tour_conditions.travel_months.*' => 'integer|min:1|max:12',
            'tour_conditions.travel_date_from' => 'nullable|date',
            'tour_conditions.travel_date_to' => 'nullable|date',
            'tour_conditions.min_available_seats' => 'nullable|integer|min:1',
            'display_count' => 'nullable|integer|min:1|max:20',
            'min_tour_count' => 'nullable|integer|min:0',
            'sort_by' => ['nullable', Rule::in(['tour_count', 'name', 'manual'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'cache_minutes' => 'nullable|integer|min:1|max:1440',
            // Country items for manual mode
            'items' => 'nullable|array',
            'items.*.id' => 'nullable|integer',
            'items.*.country_id' => 'required|integer|exists:countries,id',
            'items.*.display_name' => 'nullable|string|max:255',
            'items.*.alt_text' => 'nullable|string|max:255',
            'items.*.title' => 'nullable|string|max:255',
            'items.*.subtitle' => 'nullable|string|max:500',
            'items.*.link_url' => 'nullable|string|max:500',
            'items.*.sort_order' => 'nullable|integer',
            'items.*.is_active' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $items = $validated['items'] ?? null;
            unset($validated['items']);

            // Handle slug - if empty/null, don't update it (keep existing)
            if (array_key_exists('slug', $validated) && empty($validated['slug'])) {
                unset($validated['slug']);
            }

            $setting->update($validated);

            // Update items if provided
            // Items are saved by country_id - never deleted (to preserve images for countries that may come back)
            if ($items !== null) {
                foreach ($items as $index => $itemData) {
                    // Find existing item by country_id (not by id)
                    $existingItem = PopularCountryItem::where('setting_id', $setting->id)
                        ->where('country_id', $itemData['country_id'])
                        ->first();
                    
                    if ($existingItem) {
                        // Update existing item - use array_key_exists to allow setting null values
                        $updateData = [
                            'sort_order' => $itemData['sort_order'] ?? $index,
                            'is_active' => $itemData['is_active'] ?? true,
                        ];
                        
                        // Only update if key exists in request (allows setting to empty/null)
                        if (array_key_exists('display_name', $itemData)) {
                            $updateData['display_name'] = $itemData['display_name'];
                        }
                        if (array_key_exists('alt_text', $itemData)) {
                            $updateData['alt_text'] = $itemData['alt_text'];
                        }
                        if (array_key_exists('title', $itemData)) {
                            $updateData['title'] = $itemData['title'];
                        }
                        if (array_key_exists('subtitle', $itemData)) {
                            $updateData['subtitle'] = $itemData['subtitle'];
                        }
                        if (array_key_exists('link_url', $itemData)) {
                            $updateData['link_url'] = $itemData['link_url'];
                        }
                        
                        $existingItem->update($updateData);
                    } else {
                        // Create new item
                        $setting->items()->create([
                            'country_id' => $itemData['country_id'],
                            'display_name' => $itemData['display_name'] ?? null,
                            'alt_text' => $itemData['alt_text'] ?? null,
                            'title' => $itemData['title'] ?? null,
                            'subtitle' => $itemData['subtitle'] ?? null,
                            'link_url' => $itemData['link_url'] ?? null,
                            'sort_order' => $itemData['sort_order'] ?? $index,
                            'is_active' => $itemData['is_active'] ?? true,
                        ]);
                    }
                }
                
                // Note: We don't delete items that aren't in the current list
                // This preserves images for countries that may come back when tour counts change
            }

            // Clear cache after update
            $setting->clearCache();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'อัปเดตการตั้งค่าสำเร็จ',
                'data' => $setting->fresh()->load('items.country'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update popular country setting', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถอัปเดตการตั้งค่าได้: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified setting.
     */
    public function destroy(int $id): JsonResponse
    {
        $setting = PopularCountrySetting::findOrFail($id);
        
        // Clear cache before delete
        $setting->clearCache();
        
        // Delete associated items (cascade will handle this)
        $setting->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบการตั้งค่าสำเร็จ',
        ]);
    }

    /**
     * Toggle active status
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $setting = PopularCountrySetting::findOrFail($id);
        $setting->is_active = !$setting->is_active;
        $setting->save();

        // Clear cache
        $setting->clearCache();

        return response()->json([
            'success' => true,
            'message' => $setting->is_active ? 'เปิดใช้งานสำเร็จ' : 'ปิดใช้งานสำเร็จ',
            'data' => $setting,
        ]);
    }

    /**
     * Preview the popular countries based on current settings
     */
    public function preview(Request $request, int $id): JsonResponse
    {
        $setting = PopularCountrySetting::with('items.country')->findOrFail($id);

        try {
            $countries = $setting->preview();

            return response()->json([
                'success' => true,
                'data' => [
                    'setting' => [
                        'name' => $setting->name,
                        'slug' => $setting->slug,
                        'selection_mode' => $setting->selection_mode,
                        'display_count' => $setting->display_count,
                    ],
                    'countries' => $countries,
                    'total_found' => count($countries),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to preview popular countries', [
                'setting_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถดูตัวอย่างได้: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview settings before saving (without ID)
     */
    public function previewSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'setting_id' => 'nullable|integer|exists:popular_country_settings,id',
            'selection_mode' => ['required', Rule::in(['auto', 'manual'])],
            'country_ids' => 'nullable|array',
            'country_ids.*' => 'integer|exists:countries,id',
            'filters' => 'nullable|array',
            'tour_conditions' => 'nullable|array',
            'display_count' => 'nullable|integer|min:1|max:20',
            'min_tour_count' => 'nullable|integer|min:0',
            'sort_by' => ['nullable', Rule::in(['tour_count', 'name', 'manual'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        // If editing an existing setting, load it to access saved items
        $existingSetting = !empty($validated['setting_id']) 
            ? PopularCountrySetting::with('items')->find($validated['setting_id']) 
            : null;

        // Create temporary setting for preview
        $setting = new PopularCountrySetting([
            'name' => 'Preview',
            'slug' => 'preview',
            'selection_mode' => $validated['selection_mode'],
            'filters' => $validated['filters'] ?? null,
            'tour_conditions' => $validated['tour_conditions'] ?? ['has_upcoming_periods' => true],
            'display_count' => $validated['display_count'] ?? 6,
            'min_tour_count' => $validated['min_tour_count'] ?? 1,
            'sort_by' => $validated['sort_by'] ?? 'tour_count',
            'sort_direction' => $validated['sort_direction'] ?? 'desc',
        ]);
        
        // Copy items from existing setting if available (for image lookup)
        if ($existingSetting && $existingSetting->items->isNotEmpty()) {
            $setting->setRelation('items', $existingSetting->items);
        }

        try {
            // For manual mode with country_ids, use special preview method
            if ($validated['selection_mode'] === 'manual' && !empty($validated['country_ids'])) {
                $countries = $setting->previewWithCountryIds($validated['country_ids']);
            } else {
                $countries = $setting->preview();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'countries' => $countries,
                    'total_found' => count($countries),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to preview settings', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถดูตัวอย่างได้: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear cache for a setting
     */
    public function clearCache(int $id): JsonResponse
    {
        $setting = PopularCountrySetting::findOrFail($id);
        $setting->clearCache();
        $setting->last_cached_at = null;
        $setting->save();

        return response()->json([
            'success' => true,
            'message' => 'ล้างแคชสำเร็จ',
        ]);
    }

    /**
     * Reorder settings
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:popular_country_settings,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['items'] as $item) {
            PopularCountrySetting::where('id', $item['id'])
                ->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'จัดเรียงสำเร็จ',
        ]);
    }

    /**
     * Upload image for a country item
     */
    public function uploadItemImage(Request $request, int $settingId, int $countryId): JsonResponse
    {
        $setting = PopularCountrySetting::findOrFail($settingId);
        
        // Find or create item by country_id
        $item = $setting->items()->where('country_id', $countryId)->first();
        if (!$item) {
            $item = $setting->items()->create([
                'country_id' => $countryId,
                'sort_order' => $setting->items()->count(),
            ]);
        }

        $request->validate([
            'image' => 'required|file|mimes:jpeg,jpg,png,gif,webp|max:5120', // 5MB max
        ]);

        try {
            $file = $request->file('image');
            
            // Delete old image if exists
            if ($item->cloudflare_id) {
                try {
                    $this->cloudflare->delete($item->cloudflare_id);
                } catch (\Exception $e) {
                    Log::warning('Failed to delete old cloudflare image', [
                        'cloudflare_id' => $item->cloudflare_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Upload to Cloudflare with unique ID
            $uniqueId = "popular-countries/{$settingId}/{$countryId}-" . time();
            $result = $this->cloudflare->uploadFromFile($file, $uniqueId);
            
            if (!$result || !isset($result['id'])) {
                throw new \Exception('Cloudflare upload failed');
            }

            // Generate URL
            $imageUrl = $this->cloudflare->getDisplayUrl($result['id']);

            // Update item
            $item->update([
                'cloudflare_id' => $result['id'],
                'image_url' => $imageUrl,
            ]);

            // Clear cache
            $setting->clearCache();

            return response()->json([
                'success' => true,
                'message' => 'อัปโหลดรูปภาพสำเร็จ',
                'image_url' => $imageUrl,
                'cloudflare_id' => $result['id'],
                'item' => $item->fresh()->load('country'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to upload item image', [
                'setting_id' => $settingId,
                'country_id' => $countryId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถอัปโหลดรูปภาพได้: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete image for a country item
     */
    public function deleteItemImage(int $settingId, int $countryId): JsonResponse
    {
        $setting = PopularCountrySetting::findOrFail($settingId);
        $item = $setting->items()->where('country_id', $countryId)->first();
        
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่พบรายการประเทศ',
            ], 404);
        }

        try {
            // Delete from Cloudflare
            if ($item->cloudflare_id) {
                try {
                    $this->cloudflare->delete($item->cloudflare_id);
                } catch (\Exception $e) {
                    Log::warning('Failed to delete cloudflare image', [
                        'cloudflare_id' => $item->cloudflare_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Update item
            $item->update([
                'cloudflare_id' => null,
                'image_url' => null,
            ]);

            // Clear cache
            $setting->clearCache();

            return response()->json([
                'success' => true,
                'message' => 'ลบรูปภาพสำเร็จ',
                'item' => $item->fresh()->load('country'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete item image', [
                'setting_id' => $settingId,
                'country_id' => $countryId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถลบรูปภาพได้: ' . $e->getMessage(),
            ], 500);
        }
    }
}
