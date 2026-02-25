<?php

namespace App\Http\Controllers;

use App\Models\TourPackage;
use App\Models\TourPackagePageSetting;
use App\Models\Country;
use App\Services\CloudflareImagesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TourPackageController extends Controller
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
        $packages = TourPackage::with('countries:id,name_th,iso2,slug')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $packages]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tour_packages,slug',
            'description' => 'nullable|string',
            'terms' => 'nullable|string',
            'remarks' => 'nullable|string',
            'cancellation_policy' => 'nullable|string',
            'inclusions' => 'nullable|array',
            'exclusions' => 'nullable|array',
            'timeline' => 'nullable|array',
            'hashtags' => 'nullable|array',
            'expires_at' => 'nullable|date',
            'is_never_expire' => 'boolean',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string',
            'seo_keywords' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'country_ids' => 'nullable|array',
            'country_ids.*' => 'integer|exists:countries,id',
        ]);

        $countryIds = $validated['country_ids'] ?? [];
        unset($validated['country_ids']);

        // Strip HTML entities from name (e.g. &nbsp; from copy-paste)
        if (!empty($validated['name'])) {
            $validated['name'] = trim(html_entity_decode($validated['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        if (empty($validated['slug'])) {
            $validated['slug'] = TourPackage::generateSlug($validated['name']);
        }

        $package = TourPackage::create($validated);

        if (!empty($countryIds)) {
            $package->countries()->sync($countryIds);
        }

        $package->load('countries:id,name_th,iso2,slug');

        return response()->json([
            'success' => true,
            'data' => $package,
            'message' => 'สร้างแพ็คเกจทัวร์สำเร็จ',
        ], 201);
    }

    public function show(TourPackage $tourPackage): JsonResponse
    {
        $tourPackage->load('countries:id,name_th,iso2,slug');
        return response()->json(['success' => true, 'data' => $tourPackage]);
    }

    public function update(Request $request, TourPackage $tourPackage): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tour_packages,slug,' . $tourPackage->id,
            'description' => 'nullable|string',
            'terms' => 'nullable|string',
            'remarks' => 'nullable|string',
            'cancellation_policy' => 'nullable|string',
            'inclusions' => 'nullable|array',
            'exclusions' => 'nullable|array',
            'timeline' => 'nullable|array',
            'hashtags' => 'nullable|array',
            'expires_at' => 'nullable|date',
            'is_never_expire' => 'boolean',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string',
            'seo_keywords' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'country_ids' => 'nullable|array',
            'country_ids.*' => 'integer|exists:countries,id',
        ]);

        $countryIds = $validated['country_ids'] ?? null;
        unset($validated['country_ids']);

        // Strip HTML entities from name (e.g. &nbsp; from copy-paste)
        if (!empty($validated['name'])) {
            $validated['name'] = trim(html_entity_decode($validated['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $tourPackage->update($validated);

        if ($countryIds !== null) {
            $tourPackage->countries()->sync($countryIds);
        }

        $tourPackage->load('countries:id,name_th,iso2,slug');

        return response()->json([
            'success' => true,
            'data' => $tourPackage->fresh()->load('countries:id,name_th,iso2,slug'),
            'message' => 'อัปเดตแพ็คเกจทัวร์สำเร็จ',
        ]);
    }

    public function destroy(TourPackage $tourPackage): JsonResponse
    {
        // Delete image from Cloudflare
        if ($tourPackage->image_cf_id) {
            try {
                $this->cloudflare->delete($tourPackage->image_cf_id);
            } catch (\Exception $e) {
                \Log::warning('Failed to delete tour package image: ' . $e->getMessage());
            }
        }

        // Delete PDF file
        if ($tourPackage->pdf_path && \Storage::disk('public')->exists($tourPackage->pdf_path)) {
            \Storage::disk('public')->delete($tourPackage->pdf_path);
        }

        $tourPackage->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบแพ็คเกจทัวร์สำเร็จ',
        ]);
    }

    public function toggleStatus(TourPackage $tourPackage): JsonResponse
    {
        $tourPackage->update(['is_active' => !$tourPackage->is_active]);
        $tourPackage->load('countries:id,name_th,iso2,slug');

        return response()->json([
            'success' => true,
            'data' => $tourPackage,
            'message' => $tourPackage->is_active ? 'เปิดใช้งานแล้ว' : 'ปิดใช้งานแล้ว',
        ]);
    }

    // ============================
    // Image Upload
    // ============================

    public function uploadImage(Request $request, TourPackage $tourPackage): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,gif,webp|max:10240',
        ]);

        // Delete old image
        if ($tourPackage->image_cf_id) {
            try {
                $this->cloudflare->delete($tourPackage->image_cf_id);
            } catch (\Exception $e) {
                \Log::warning('Failed to delete old tour package image: ' . $e->getMessage());
            }
        }

        $file = $request->file('image');
        $customId = 'tour-package-' . $tourPackage->id . '-' . time();

        try {
            $result = $this->cloudflare->uploadFromFile($file, $customId);
            $url = $this->cloudflare->getDisplayUrl($result['id'], 'public');

            $tourPackage->update([
                'image_url' => $url,
                'image_cf_id' => $result['id'],
            ]);

            $tourPackage->load('countries:id,name_th,iso2,slug');

            return response()->json([
                'message' => 'อัปโหลดรูปภาพสำเร็จ',
                'data' => $tourPackage,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'อัปโหลดไม่สำเร็จ: ' . $e->getMessage()], 500);
        }
    }

    public function deleteImage(TourPackage $tourPackage): JsonResponse
    {
        if ($tourPackage->image_cf_id) {
            try {
                $this->cloudflare->delete($tourPackage->image_cf_id);
            } catch (\Exception $e) {
                \Log::warning('Failed to delete tour package image: ' . $e->getMessage());
            }
        }

        $tourPackage->update([
            'image_url' => null,
            'image_cf_id' => null,
        ]);

        $tourPackage->load('countries:id,name_th,iso2,slug');

        return response()->json([
            'message' => 'ลบรูปภาพสำเร็จ',
            'data' => $tourPackage,
        ]);
    }

    // ============================
    // PDF Upload
    // ============================

    public function uploadPdf(Request $request, TourPackage $tourPackage): JsonResponse
    {
        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:20480',
        ]);

        // Delete old PDF
        if ($tourPackage->pdf_path && \Storage::disk('public')->exists($tourPackage->pdf_path)) {
            \Storage::disk('public')->delete($tourPackage->pdf_path);
        }

        $file = $request->file('pdf');
        $filename = 'tour-packages/' . Str::slug($tourPackage->name) . '-' . time() . '.pdf';
        $path = $file->storeAs('pdfs', $filename, 'public');

        $tourPackage->update([
            'pdf_url' => asset('storage/pdfs/' . $filename),
            'pdf_path' => 'pdfs/' . $filename,
        ]);

        $tourPackage->load('countries:id,name_th,iso2,slug');

        return response()->json([
            'message' => 'อัปโหลด PDF สำเร็จ',
            'data' => $tourPackage,
        ]);
    }

    public function deletePdf(TourPackage $tourPackage): JsonResponse
    {
        if ($tourPackage->pdf_path && \Storage::disk('public')->exists($tourPackage->pdf_path)) {
            \Storage::disk('public')->delete($tourPackage->pdf_path);
        }

        $tourPackage->update([
            'pdf_url' => null,
            'pdf_path' => null,
        ]);

        $tourPackage->load('countries:id,name_th,iso2,slug');

        return response()->json([
            'message' => 'ลบ PDF สำเร็จ',
            'data' => $tourPackage,
        ]);
    }

    // ============================
    // Page Settings (Cover Image)
    // ============================

    public function getPageSettings(): JsonResponse
    {
        $setting = TourPackagePageSetting::getSettings();
        return response()->json(['data' => $setting]);
    }

    public function updatePageSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cover_image_position' => 'nullable|string|max:50',
        ]);

        $setting = TourPackagePageSetting::getSettings();
        $setting->update($validated);

        return response()->json(['data' => $setting]);
    }

    public function uploadPageCoverImage(Request $request): JsonResponse
    {
        $request->validate([
            'cover_image' => 'required|image|mimes:jpeg,png,gif,webp|max:10240',
        ]);

        $setting = TourPackagePageSetting::getSettings();

        if ($setting->cover_image_cf_id) {
            try {
                $this->cloudflare->delete($setting->cover_image_cf_id);
            } catch (\Exception $e) {
                \Log::warning('Failed to delete old package page cover: ' . $e->getMessage());
            }
        }

        $file = $request->file('cover_image');
        $customId = 'tour-package-page-cover-' . time();

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
        $setting = TourPackagePageSetting::getSettings();

        if ($setting->cover_image_cf_id) {
            try {
                $this->cloudflare->delete($setting->cover_image_cf_id);
            } catch (\Exception $e) {
                \Log::warning('Failed to delete package page cover: ' . $e->getMessage());
            }
        }

        $setting->update([
            'cover_image_url' => null,
            'cover_image_cf_id' => null,
        ]);

        return response()->json(['message' => 'ลบภาพปกสำเร็จ', 'data' => $setting]);
    }

    // ============================
    // Public Endpoints
    // ============================

    public function publicList(Request $request): JsonResponse
    {
        $query = TourPackage::active()
            ->notExpired()
            ->with('countries:id,name_th,iso2,slug');

        // Filter by country
        if ($request->filled('country_id')) {
            $query->whereHas('countries', function ($q) use ($request) {
                $q->where('countries.id', $request->country_id);
            });
        }

        $packages = $query->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($pkg) {
                return [
                    'id' => $pkg->id,
                    'name' => $pkg->name,
                    'slug' => $pkg->slug,
                    'description' => $pkg->description,
                    'image_url' => $pkg->image_url,
                    'hashtags' => $pkg->hashtags ?? [],
                    'countries' => $pkg->countries->map(fn($c) => [
                        'id' => $c->id,
                        'name_th' => $c->name_th,
                        'iso2' => strtolower($c->iso2 ?? ''),
                        'slug' => $c->slug,
                    ])->values(),
                    'expires_at' => $pkg->expires_at?->format('Y-m-d'),
                    'is_never_expire' => $pkg->is_never_expire,
                ];
            });

        // Get countries that have packages (for filter)
        $filterCountries = Country::whereHas('tourPackages', function ($q) {
            $q->where('is_active', true)
                ->where(function ($q2) {
                    $q2->where('is_never_expire', true)
                        ->orWhere('expires_at', '>=', now());
                });
        })
            ->orderBy('name_th')
            ->get(['id', 'name_th', 'iso2', 'slug'])
            ->map(fn($c) => [
                'id' => $c->id,
                'name_th' => $c->name_th,
                'iso2' => strtolower($c->iso2 ?? ''),
                'slug' => $c->slug,
            ]);

        return response()->json([
            'success' => true,
            'data' => $packages,
            'filters' => [
                'countries' => $filterCountries,
            ],
        ]);
    }

    public function publicShow(string $slug): JsonResponse
    {
        $package = TourPackage::active()
            ->notExpired()
            ->where('slug', $slug)
            ->with('countries:id,name_th,iso2,slug')
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $package->id,
                'name' => $package->name,
                'slug' => $package->slug,
                'description' => $package->description,
                'terms' => $package->terms,
                'remarks' => $package->remarks,
                'cancellation_policy' => $package->cancellation_policy,
                'inclusions' => $package->inclusions ?? [],
                'exclusions' => $package->exclusions ?? [],
                'timeline' => $package->timeline ?? [],
                'image_url' => $package->image_url,
                'pdf_url' => $package->pdf_url,
                'hashtags' => $package->hashtags ?? [],
                'countries' => $package->countries->map(fn($c) => [
                    'id' => $c->id,
                    'name_th' => $c->name_th,
                    'iso2' => strtolower($c->iso2 ?? ''),
                    'slug' => $c->slug,
                ])->values(),
                'expires_at' => $package->expires_at?->format('Y-m-d'),
                'is_never_expire' => $package->is_never_expire,
                'seo_title' => $package->seo_title,
                'seo_description' => $package->seo_description,
                'seo_keywords' => $package->seo_keywords,
            ],
        ]);
    }

    public function publicPageSettings(): JsonResponse
    {
        $setting = TourPackagePageSetting::getSettings();

        return response()->json([
            'cover_image_url' => $setting->cover_image_url,
            'cover_image_position' => $setting->cover_image_position ?? 'center',
        ]);
    }
}
