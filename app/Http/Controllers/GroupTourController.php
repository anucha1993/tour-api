<?php

namespace App\Http\Controllers;

use App\Models\GroupTourPageSetting;
use App\Models\GroupTourPortfolio;
use App\Models\GroupTourTestimonial;
use App\Models\GroupTourInquiry;
use App\Services\CloudflareImagesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupTourController extends Controller
{
    protected CloudflareImagesService $cloudflare;

    public function __construct(CloudflareImagesService $cloudflare)
    {
        $this->cloudflare = $cloudflare;
    }

    // ============================
    // Admin: Page Settings
    // ============================

    public function getSettings(): JsonResponse
    {
        $settings = GroupTourPageSetting::getSettings();
        return response()->json(['success' => true, 'data' => $settings]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $settings = GroupTourPageSetting::getSettings();

        $validated = $request->validate([
            'hero_title' => 'nullable|string|max:255',
            'hero_subtitle' => 'nullable|string',
            'hero_image_position' => 'nullable|string|max:50',
            'stats' => 'nullable|array',
            'stats.*.icon' => 'required|string',
            'stats.*.value' => 'required|string',
            'stats.*.label' => 'required|string',
            'group_types' => 'nullable|array',
            'group_types.*.icon' => 'required|string',
            'group_types.*.title' => 'required|string',
            'group_types.*.description' => 'nullable|string',
            'advantages_title' => 'nullable|string|max:255',
            'advantages' => 'nullable|array',
            'advantages.*.text' => 'required|string',
            'process_steps' => 'nullable|array',
            'process_steps.*.step_number' => 'required|integer',
            'process_steps.*.title' => 'required|string',
            'process_steps.*.description' => 'nullable|string',
            'faqs' => 'nullable|array',
            'faqs.*.question' => 'required|string',
            'faqs.*.answer' => 'required|string',
            'cta_title' => 'nullable|string|max:255',
            'cta_description' => 'nullable|string',
            'cta_phone' => 'nullable|string|max:50',
            'cta_email' => 'nullable|string|max:255',
            'cta_line_id' => 'nullable|string|max:100',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string',
            'seo_keywords' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $settings->update($validated);

        return response()->json([
            'success' => true,
            'data' => $settings->fresh(),
            'message' => 'อัปเดตการตั้งค่าสำเร็จ',
        ]);
    }

    public function uploadHeroImage(Request $request): JsonResponse
    {
        $request->validate(['image' => 'required|image|max:10240']);

        $settings = GroupTourPageSetting::getSettings();

        // Delete old image
        if ($settings->hero_image_cf_id) {
            try { $this->cloudflare->delete($settings->hero_image_cf_id); } catch (\Exception $e) {}
        }

        $result = $this->cloudflare->uploadFromFile(
            $request->file('image'),
            'group-tour-hero-' . time()
        );

        $settings->update([
            'hero_image_url' => $this->cloudflare->getDisplayUrl($result['id'], 'public'),
            'hero_image_cf_id' => $result['id'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $settings->fresh(),
            'message' => 'อัปโหลดรูป Hero สำเร็จ',
        ]);
    }

    public function deleteHeroImage(): JsonResponse
    {
        $settings = GroupTourPageSetting::getSettings();

        if ($settings->hero_image_cf_id) {
            try { $this->cloudflare->delete($settings->hero_image_cf_id); } catch (\Exception $e) {}
        }

        $settings->update(['hero_image_url' => null, 'hero_image_cf_id' => null]);

        return response()->json(['success' => true, 'message' => 'ลบรูป Hero สำเร็จ']);
    }

    public function uploadAdvantagesImage(Request $request): JsonResponse
    {
        $request->validate(['image' => 'required|image|max:10240']);

        $settings = GroupTourPageSetting::getSettings();

        if ($settings->advantages_image_cf_id) {
            try { $this->cloudflare->delete($settings->advantages_image_cf_id); } catch (\Exception $e) {}
        }

        $result = $this->cloudflare->uploadFromFile(
            $request->file('image'),
            'group-tour-advantages-' . time()
        );

        $settings->update([
            'advantages_image_url' => $this->cloudflare->getDisplayUrl($result['id'], 'public'),
            'advantages_image_cf_id' => $result['id'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $settings->fresh(),
            'message' => 'อัปโหลดรูปทำไมต้องเลือกเราสำเร็จ',
        ]);
    }

    public function deleteAdvantagesImage(): JsonResponse
    {
        $settings = GroupTourPageSetting::getSettings();

        if ($settings->advantages_image_cf_id) {
            try { $this->cloudflare->delete($settings->advantages_image_cf_id); } catch (\Exception $e) {}
        }

        $settings->update(['advantages_image_url' => null, 'advantages_image_cf_id' => null]);

        return response()->json(['success' => true, 'message' => 'ลบรูปสำเร็จ']);
    }

    // ============================
    // Admin: Portfolios
    // ============================

    public function reorderPortfolios(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:group_tour_portfolios,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['items'] as $item) {
            GroupTourPortfolio::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        $items = GroupTourPortfolio::orderBy('sort_order')->orderByDesc('created_at')->get();
        return response()->json(['success' => true, 'data' => $items, 'message' => 'เรียงลำดับสำเร็จ']);
    }

    public function listPortfolios(): JsonResponse
    {
        $items = GroupTourPortfolio::orderBy('sort_order')->orderByDesc('created_at')->get();
        return response()->json(['success' => true, 'data' => $items]);
    }

    public function storePortfolio(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'caption' => 'nullable|string|max:500',
            'group_size' => 'nullable|string|max:100',
            'destination' => 'nullable|string|max:255',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $item = GroupTourPortfolio::create($validated);

        return response()->json(['success' => true, 'data' => $item, 'message' => 'เพิ่มผลงานสำเร็จ'], 201);
    }

    public function updatePortfolio(Request $request, GroupTourPortfolio $portfolio): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'caption' => 'nullable|string|max:500',
            'group_size' => 'nullable|string|max:100',
            'destination' => 'nullable|string|max:255',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $portfolio->update($validated);

        return response()->json(['success' => true, 'data' => $portfolio->fresh(), 'message' => 'อัปเดตผลงานสำเร็จ']);
    }

    public function destroyPortfolio(GroupTourPortfolio $portfolio): JsonResponse
    {
        if ($portfolio->image_cf_id) {
            try { $this->cloudflare->delete($portfolio->image_cf_id); } catch (\Exception $e) {}
        }

        $portfolio->delete();

        return response()->json(['success' => true, 'message' => 'ลบผลงานสำเร็จ']);
    }

    public function uploadPortfolioImage(Request $request, GroupTourPortfolio $portfolio): JsonResponse
    {
        $request->validate(['image' => 'required|image|max:10240']);

        if ($portfolio->image_cf_id) {
            try { $this->cloudflare->delete($portfolio->image_cf_id); } catch (\Exception $e) {}
        }

        $result = $this->cloudflare->uploadFromFile(
            $request->file('image'),
            'group-tour-portfolio-' . $portfolio->id . '-' . time()
        );

        $portfolio->update([
            'image_url' => $this->cloudflare->getDisplayUrl($result['id'], 'public'),
            'image_cf_id' => $result['id'],
        ]);

        return response()->json(['success' => true, 'data' => $portfolio->fresh(), 'message' => 'อัปโหลดรูปสำเร็จ']);
    }

    // ============================
    // Admin: Testimonials
    // ============================

    public function listTestimonials(): JsonResponse
    {
        $items = GroupTourTestimonial::orderBy('sort_order')->orderByDesc('created_at')->get();
        return response()->json(['success' => true, 'data' => $items]);
    }

    public function storeTestimonial(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'reviewer_name' => 'nullable|string|max:255',
            'reviewer_position' => 'nullable|string|max:255',
            'content' => 'required|string',
            'rating' => 'integer|min:1|max:5',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $item = GroupTourTestimonial::create($validated);

        return response()->json(['success' => true, 'data' => $item, 'message' => 'เพิ่มรีวิวสำเร็จ'], 201);
    }

    public function updateTestimonial(Request $request, GroupTourTestimonial $testimonial): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => 'sometimes|required|string|max:255',
            'reviewer_name' => 'nullable|string|max:255',
            'reviewer_position' => 'nullable|string|max:255',
            'content' => 'sometimes|required|string',
            'rating' => 'integer|min:1|max:5',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $testimonial->update($validated);

        return response()->json(['success' => true, 'data' => $testimonial->fresh(), 'message' => 'อัปเดตรีวิวสำเร็จ']);
    }

    public function destroyTestimonial(GroupTourTestimonial $testimonial): JsonResponse
    {
        if ($testimonial->logo_cf_id) {
            try { $this->cloudflare->delete($testimonial->logo_cf_id); } catch (\Exception $e) {}
        }

        $testimonial->delete();

        return response()->json(['success' => true, 'message' => 'ลบรีวิวสำเร็จ']);
    }

    public function uploadTestimonialLogo(Request $request, GroupTourTestimonial $testimonial): JsonResponse
    {
        $request->validate(['image' => 'required|image|max:5120']);

        if ($testimonial->logo_cf_id) {
            try { $this->cloudflare->delete($testimonial->logo_cf_id); } catch (\Exception $e) {}
        }

        $result = $this->cloudflare->uploadFromFile(
            $request->file('image'),
            'group-tour-testimonial-' . $testimonial->id . '-' . time()
        );

        $testimonial->update([
            'logo_url' => $this->cloudflare->getDisplayUrl($result['id'], 'public'),
            'logo_cf_id' => $result['id'],
        ]);

        return response()->json(['success' => true, 'data' => $testimonial->fresh(), 'message' => 'อัปโหลดโลโก้สำเร็จ']);
    }

    // ============================
    // Admin: Inquiries
    // ============================

    public function countNewInquiries(): JsonResponse
    {
        $count = GroupTourInquiry::where('status', 'new')->count();
        return response()->json(['success' => true, 'count' => $count]);
    }

    public function listInquiries(Request $request): JsonResponse
    {
        $query = GroupTourInquiry::orderByDesc('created_at');

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('organization', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $items = $query->paginate(20);

        return response()->json(['success' => true, ...$items->toArray()]);
    }

    public function showInquiry(GroupTourInquiry $inquiry): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $inquiry]);
    }

    public function updateInquiry(Request $request, GroupTourInquiry $inquiry): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:new,contacted,quoted,confirmed,cancelled',
            'admin_notes' => 'nullable|string',
        ]);

        $inquiry->update($validated);

        return response()->json(['success' => true, 'data' => $inquiry->fresh(), 'message' => 'อัปเดตสถานะสำเร็จ']);
    }

    public function destroyInquiry(GroupTourInquiry $inquiry): JsonResponse
    {
        $inquiry->delete();
        return response()->json(['success' => true, 'message' => 'ลบรายการสำเร็จ']);
    }

    // ============================
    // Public Endpoints
    // ============================

    public function publicPage(): JsonResponse
    {
        $settings = GroupTourPageSetting::getSettings();

        if (!$settings->is_active) {
            return response()->json(['success' => false, 'message' => 'หน้านี้ยังไม่เปิดให้บริการ'], 404);
        }

        $portfolios = GroupTourPortfolio::active()
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'caption' => $p->caption,
                'group_size' => $p->group_size,
                'destination' => $p->destination,
                'image_url' => $p->image_url,
            ]);

        $testimonials = GroupTourTestimonial::active()
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'company_name' => $t->company_name,
                'reviewer_name' => $t->reviewer_name,
                'reviewer_position' => $t->reviewer_position,
                'logo_url' => $t->logo_url,
                'content' => $t->content,
                'rating' => $t->rating,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'settings' => [
                    'hero_title' => $settings->hero_title,
                    'hero_subtitle' => $settings->hero_subtitle,
                    'hero_image_url' => $settings->hero_image_url,
                    'hero_image_position' => $settings->hero_image_position,
                    'content' => $settings->content,
                    'stats' => $settings->stats ?? [],
                    'group_types' => $settings->group_types ?? [],
                    'advantages_title' => $settings->advantages_title,
                    'advantages_image_url' => $settings->advantages_image_url,
                    'advantages' => $settings->advantages ?? [],
                    'process_steps' => $settings->process_steps ?? [],
                    'faqs' => $settings->faqs ?? [],
                    'cta_title' => $settings->cta_title,
                    'cta_description' => $settings->cta_description,
                    'cta_phone' => $settings->cta_phone,
                    'cta_email' => $settings->cta_email,
                    'cta_line_id' => $settings->cta_line_id,
                    'seo_title' => $settings->seo_title,
                    'seo_description' => $settings->seo_description,
                    'seo_keywords' => $settings->seo_keywords,
                ],
                'portfolios' => $portfolios,
                'testimonials' => $testimonials,
            ],
        ]);
    }

    public function publicSubmitInquiry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'organization' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|string|email|max:255',
            'line_id' => 'nullable|string|max:100',
            'group_type' => 'nullable|string|max:100',
            'group_size' => 'nullable|string|max:100',
            'destination' => 'nullable|string|max:255',
            'travel_date_start' => 'nullable|date',
            'travel_date_end' => 'nullable|date',
            'details' => 'nullable|string|max:2000',
        ]);

        $inquiry = GroupTourInquiry::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'ส่งข้อมูลสำเร็จ ทีมงานจะติดต่อกลับโดยเร็ว',
        ], 201);
    }
}
