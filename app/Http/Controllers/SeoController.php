<?php

namespace App\Http\Controllers;

use App\Models\SeoSetting;
use App\Models\SiteContact;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\CloudflareImagesService;

class SeoController extends Controller
{
    protected $cloudflare;

    public function __construct(CloudflareImagesService $cloudflare)
    {
        $this->cloudflare = $cloudflare;
    }

    // ==================== SEO Settings ====================

    /**
     * List all SEO settings
     */
    public function index(): JsonResponse
    {
        $seo = SeoSetting::orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data' => $seo,
        ]);
    }

    /**
     * Get SEO for a specific page (public)
     */
    public function publicShow(string $slug): JsonResponse
    {
        $seo = SeoSetting::bySlug($slug)->first();
        $global = SeoSetting::bySlug('global')->first();

        // Merge: page-specific overrides global
        $data = [
            'meta_title' => $seo?->meta_title ?: $global?->meta_title,
            'meta_description' => $seo?->meta_description ?: $global?->meta_description,
            'meta_keywords' => $seo?->meta_keywords ?: $global?->meta_keywords,
            'og_title' => $seo?->og_title ?: $seo?->meta_title ?: $global?->og_title,
            'og_description' => $seo?->og_description ?: $seo?->meta_description ?: $global?->og_description,
            'og_image' => $seo?->og_image ?: $global?->og_image,
            'canonical_url' => $seo?->canonical_url,
            'robots_index' => $seo?->robots_index ?? $global?->robots_index ?? true,
            'robots_follow' => $seo?->robots_follow ?? $global?->robots_follow ?? true,
            'structured_data' => $seo?->structured_data ?: $global?->structured_data,
            'custom_head_tags' => $seo?->custom_head_tags,
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get or create SEO for a page
     */
    public function show(string $slug): JsonResponse
    {
        $seo = SeoSetting::bySlug($slug)->first();

        if (!$seo) {
            $pageName = SeoSetting::PAGES[$slug] ?? $slug;
            $seo = SeoSetting::create([
                'page_slug' => $slug,
                'page_name' => $pageName,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $seo,
        ]);
    }

    /**
     * Update SEO for a page
     */
    public function update(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'page_name' => 'nullable|string|max:255',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:1000',
            'meta_keywords' => 'nullable|string|max:1000',
            'og_title' => 'nullable|string|max:255',
            'og_description' => 'nullable|string|max:1000',
            'og_image' => 'nullable|string|max:500',
            'canonical_url' => 'nullable|string|max:500',
            'robots_index' => 'nullable|boolean',
            'robots_follow' => 'nullable|boolean',
            'structured_data' => 'nullable|string',
            'custom_head_tags' => 'nullable|string',
        ]);

        $seo = SeoSetting::firstOrCreate(
            ['page_slug' => $slug],
            ['page_name' => SeoSetting::PAGES[$slug] ?? $slug]
        );

        $seo->update($validated);

        return response()->json([
            'success' => true,
            'data' => $seo,
            'message' => 'อัปเดต SEO สำเร็จ',
        ]);
    }

    /**
     * Upload OG image
     */
    public function uploadOgImage(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|max:2048',
        ]);

        $seo = SeoSetting::firstOrCreate(
            ['page_slug' => $slug],
            ['page_name' => SeoSetting::PAGES[$slug] ?? $slug]
        );

        // Delete old image if exists
        if ($seo->og_image_cloudflare_id) {
            $this->cloudflare->deleteImage($seo->og_image_cloudflare_id);
        }

        $file = $request->file('image');
        $result = $this->cloudflare->uploadImage($file, "seo-og-{$slug}");

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'อัพโหลดรูปล้มเหลว: ' . ($result['error'] ?? 'Unknown error'),
            ], 500);
        }

        $seo->update([
            'og_image_cloudflare_id' => $result['id'],
            'og_image' => $result['url'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $seo,
            'message' => 'อัพโหลดรูป OG สำเร็จ',
        ]);
    }

    /**
     * Get available pages
     */
    public function pages(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => SeoSetting::PAGES,
        ]);
    }

    // ==================== Site Contacts ====================

    /**
     * List all contacts (admin)
     */
    public function contactIndex(Request $request): JsonResponse
    {
        $query = SiteContact::ordered();

        if ($request->has('group')) {
            $query->byGroup($request->group);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    /**
     * Public contacts
     */
    public function contactPublic(): JsonResponse
    {
        $contacts = SiteContact::active()->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => [
                'contact' => $contacts->where('group', 'contact')->values(),
                'social' => $contacts->where('group', 'social')->values(),
                'business_hours' => $contacts->where('group', 'business_hours')->values(),
            ],
        ]);
    }

    /**
     * Store a contact
     */
    public function contactStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'required|string|max:100|unique:site_contacts,key',
            'label' => 'required|string|max:255',
            'value' => 'required|string',
            'icon' => 'nullable|string|max:100',
            'url' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'group' => 'required|string|in:contact,social,business_hours',
        ]);

        if (!isset($validated['sort_order'])) {
            $validated['sort_order'] = SiteContact::where('group', $validated['group'])->max('sort_order') + 1;
        }

        $contact = SiteContact::create($validated);

        return response()->json([
            'success' => true,
            'data' => $contact,
            'message' => 'สร้างข้อมูลติดต่อสำเร็จ',
        ], 201);
    }

    /**
     * Update a contact
     */
    public function contactUpdate(Request $request, SiteContact $siteContact): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'nullable|string|max:100|unique:site_contacts,key,' . $siteContact->id,
            'label' => 'nullable|string|max:255',
            'value' => 'nullable|string',
            'icon' => 'nullable|string|max:100',
            'url' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'group' => 'nullable|string|in:contact,social,business_hours',
        ]);

        $siteContact->update($validated);

        return response()->json([
            'success' => true,
            'data' => $siteContact,
            'message' => 'อัปเดตข้อมูลติดต่อสำเร็จ',
        ]);
    }

    /**
     * Delete a contact
     */
    public function contactDestroy(SiteContact $siteContact): JsonResponse
    {
        $siteContact->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบข้อมูลติดต่อสำเร็จ',
        ]);
    }

    /**
     * Toggle contact status
     */
    public function contactToggle(SiteContact $siteContact): JsonResponse
    {
        $siteContact->update(['is_active' => !$siteContact->is_active]);

        return response()->json([
            'success' => true,
            'data' => $siteContact,
        ]);
    }
}
