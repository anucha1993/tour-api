<?php

namespace App\Http\Controllers;

use App\Models\GroupTourPageSetting;
use App\Models\GroupTourPortfolio;
use App\Models\GroupTourInquiry;
use App\Models\TourReview;
use App\Services\CloudflareImagesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
            'testimonial_title' => 'nullable|string|max:255',
            'testimonial_subtitle' => 'nullable|string|max:500',
            'testimonial_limit' => 'nullable|integer|min:1|max:30',
            'testimonial_pinned_ids' => 'nullable|array',
            'testimonial_pinned_ids.*' => 'integer',
            'testimonial_show_section' => 'boolean',
            'testimonial_tour_types' => 'nullable|array',
            'testimonial_tour_types.*' => 'string|in:individual,private,corporate',
            'testimonial_sort_by' => 'nullable|string|in:newest,oldest,rating_high,rating_low,featured',
            'testimonial_min_rating' => 'nullable|integer|min:1|max:5',
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
            'group_type' => 'nullable|string|max:100',
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
            'group_type' => 'nullable|string|max:100',
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

    public function uploadPortfolioLogo(Request $request, GroupTourPortfolio $portfolio): JsonResponse
    {
        $request->validate(['logo' => 'required|image|max:5120']);

        if ($portfolio->logo_cf_id) {
            try { $this->cloudflare->delete($portfolio->logo_cf_id); } catch (\Exception $e) {}
        }

        $result = $this->cloudflare->uploadFromFile(
            $request->file('logo'),
            'group-tour-portfolio-logo-' . $portfolio->id . '-' . time()
        );

        $portfolio->update([
            'logo_url' => $this->cloudflare->getDisplayUrl($result['id'], 'public'),
            'logo_cf_id' => $result['id'],
        ]);

        return response()->json(['success' => true, 'data' => $portfolio->fresh(), 'message' => 'อัปโหลดโลโก้สำเร็จ']);
    }

    public function deletePortfolioLogo(GroupTourPortfolio $portfolio): JsonResponse
    {
        if ($portfolio->logo_cf_id) {
            try { $this->cloudflare->delete($portfolio->logo_cf_id); } catch (\Exception $e) {}
        }

        $portfolio->update(['logo_url' => null, 'logo_cf_id' => null]);

        return response()->json(['success' => true, 'data' => $portfolio->fresh(), 'message' => 'ลบโลโก้สำเร็จ']);
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
                'logo_url' => $p->logo_url,
                'group_type' => $p->group_type,
            ]);

        $testimonials = collect();
        $testimonialSettings = [
            'title' => $settings->testimonial_title ?? 'เสียงจากลูกค้า',
            'subtitle' => $settings->testimonial_subtitle,
            'show_section' => $settings->testimonial_show_section ?? true,
        ];

        if ($testimonialSettings['show_section']) {
            $tourTypes = $settings->testimonial_tour_types ?? ['private', 'corporate'];
            $limit = $settings->testimonial_limit ?? 6;
            $minRating = $settings->testimonial_min_rating ?? 1;
            $sortBy = $settings->testimonial_sort_by ?? 'newest';
            $pinnedIds = $settings->testimonial_pinned_ids ?? [];

            $query = TourReview::where('status', 'approved')
                ->whereIn('tour_type', $tourTypes)
                ->where('rating', '>=', $minRating)
                ->with('tour:id,title,slug');

            // Pinned reviews always come first
            if (!empty($pinnedIds)) {
                $query->orderByRaw('FIELD(id, ' . implode(',', array_map('intval', $pinnedIds)) . ') DESC');
            }

            // Then sort by chosen criteria
            switch ($sortBy) {
                case 'oldest':
                    $query->orderBy('created_at', 'asc');
                    break;
                case 'rating_high':
                    $query->orderByDesc('rating')->orderByDesc('created_at');
                    break;
                case 'rating_low':
                    $query->orderBy('rating')->orderByDesc('created_at');
                    break;
                case 'featured':
                    $query->orderByDesc('is_featured')->orderByDesc('created_at');
                    break;
                case 'newest':
                default:
                    $query->orderByDesc('created_at');
                    break;
            }

            $testimonials = $query->limit($limit)
                ->get()
                ->map(fn($r) => [
                    'id' => $r->id,
                    'reviewer_name' => $r->reviewer_name,
                    'reviewer_avatar_url' => $r->reviewer_avatar_url,
                    'comment' => $r->comment,
                    'rating' => $r->rating,
                    'tour_type' => $r->tour_type,
                    'tags' => $r->tags,
                    'is_featured' => $r->is_featured,
                    'tour' => $r->tour ? ['title' => $r->tour->title, 'slug' => $r->tour->slug] : null,
                    'created_at' => $r->created_at->toISOString(),
                ]);
        }

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
                'testimonial_settings' => $testimonialSettings,
            ],
        ]);
    }

    public function publicSubmitInquiry(Request $request): JsonResponse
    {
        // ─── Anti-spam: honeypot + time-trap (silent reject) ───
        // If a bot fills the hidden "website" field, silently return success
        // so the bot thinks it worked but no record is created.
        if (filled($request->input('website'))) {
            Log::info('Group inquiry blocked (honeypot)', [
                'ip' => $request->ip(),
                'ua' => substr((string) $request->userAgent(), 0, 200),
                'website' => $request->input('website'),
            ]);
            return response()->json(['success' => true, 'message' => 'ส่งข้อมูลสำเร็จ'], 201);
        }

        // Time-trap: form must have been open at least 3 seconds
        $loadedAt = (int) $request->input('_ts', 0);
        if ($loadedAt > 0) {
            $elapsedMs = (int) (microtime(true) * 1000) - $loadedAt;
            if ($elapsedMs < 3000) {
                Log::info('Group inquiry blocked (too fast)', [
                    'ip' => $request->ip(),
                    'elapsed_ms' => $elapsedMs,
                ]);
                return response()->json(['success' => true, 'message' => 'ส่งข้อมูลสำเร็จ'], 201);
            }
        }

        // ─── Strict validation ───
        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:120', 'regex:/^[^\r\n\t]+$/u'],
            'organization'      => ['nullable', 'string', 'max:200'],
            'phone'             => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{6,20}$/'],
            'email'             => ['nullable', 'string', 'email:rfc,dns', 'max:200'],
            'line_id'           => ['nullable', 'string', 'max:100'],
            'group_type'        => ['nullable', 'string', 'max:100'],
            'group_size'        => ['nullable', 'string', 'max:100'],
            'destination'       => ['nullable', 'string', 'max:200'],
            'travel_date_start' => ['nullable', 'date'],
            'travel_date_end'   => ['nullable', 'date', 'after_or_equal:travel_date_start'],
            'details'           => ['nullable', 'string', 'max:2000'],
        ], [
            'phone.regex' => 'รูปแบบเบอร์โทรไม่ถูกต้อง',
            'email.email' => 'อีเมลไม่ถูกต้องหรือโดเมนไม่มีอยู่จริง',
        ]);

        // ─── Require at least email or phone ───
        if (empty($validated['email'] ?? null) && empty($validated['phone'] ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'กรุณากรอกเบอร์โทรหรืออีเมลอย่างน้อย 1 ช่องทาง',
                'errors'  => ['phone' => ['กรุณากรอกเบอร์โทรหรืออีเมล']],
            ], 422);
        }

        // ─── Block obvious spam content ───
        // (URLs in name/organization, too many URLs in details)
        $hasUrl = fn(string $s) => preg_match('~https?://|www\.|\.com|\.ru|\.cn~i', $s) === 1;
        if ($hasUrl($validated['name']) || $hasUrl($validated['organization'] ?? '')) {
            Log::info('Group inquiry blocked (URL in name)', ['ip' => $request->ip(), 'name' => $validated['name']]);
            return response()->json(['success' => true, 'message' => 'ส่งข้อมูลสำเร็จ'], 201); // silent
        }
        if (!empty($validated['details']) && preg_match_all('~https?://~i', $validated['details']) > 2) {
            Log::info('Group inquiry blocked (too many links)', ['ip' => $request->ip()]);
            return response()->json(['success' => true, 'message' => 'ส่งข้อมูลสำเร็จ'], 201); // silent
        }

        // ─── Block empty / suspicious User-Agent ───
        $ua = (string) $request->userAgent();
        if ($ua === '' || preg_match('~curl|wget|python|scrapy|libwww|httpclient~i', $ua)) {
            Log::info('Group inquiry blocked (bad UA)', ['ip' => $request->ip(), 'ua' => $ua]);
            return response()->json(['success' => true, 'message' => 'ส่งข้อมูลสำเร็จ'], 201); // silent
        }

        // ─── Per-IP dedupe: same IP + same phone/email within 10 minutes = skip ───
        $recentKey = 'group_inquiry_recent:' . md5(
            $request->ip() . '|' . ($validated['phone'] ?? '') . '|' . ($validated['email'] ?? '')
        );
        if (Cache::has($recentKey)) {
            return response()->json([
                'success' => true,
                'message' => 'เราได้รับข้อมูลของคุณแล้ว ทีมงานจะติดต่อกลับโดยเร็ว',
            ], 201);
        }
        Cache::put($recentKey, true, now()->addMinutes(10));

        $inquiry = GroupTourInquiry::create($validated + [
            'ip_address' => $request->ip(),
            'user_agent' => substr($ua, 0, 500),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ส่งข้อมูลสำเร็จ ทีมงานจะติดต่อกลับโดยเร็ว',
        ], 201);
    }
}
