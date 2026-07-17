<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPageSetting;
use App\Models\BlogPost;
use App\Services\CloudflareImagesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BlogController extends Controller
{
    protected CloudflareImagesService $cloudflare;

    public function __construct(CloudflareImagesService $cloudflare)
    {
        $this->cloudflare = $cloudflare;
    }

    // ============================
    // Public
    // ============================

    public function publicSettings(): JsonResponse
    {
        $settings = BlogPageSetting::getSettings();
        return response()->json(['success' => true, 'data' => $settings]);
    }

    public function publicCategories(): JsonResponse
    {
        $categories = BlogCategory::active()
            ->withCount(['posts' => fn($q) => $q->published()])
            ->orderBy('sort_order')
            ->get();

        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function publicFilters(): JsonResponse
    {
        // Collect country_ids and city_ids from all published posts
        $posts = BlogPost::published()
            ->whereNotNull('country_ids')
            ->select('country_ids', 'city_ids')
            ->get();

        $countryCounts = [];
        $cityCounts = [];

        foreach ($posts as $post) {
            foreach ((array) $post->country_ids as $cid) {
                $cid = (int) $cid;
                $countryCounts[$cid] = ($countryCounts[$cid] ?? 0) + 1;
            }
            foreach ((array) $post->city_ids as $cid) {
                $cid = (int) $cid;
                $cityCounts[$cid] = ($cityCounts[$cid] ?? 0) + 1;
            }
        }

        $countries = [];
        if (!empty($countryCounts)) {
            $countryIds = array_keys($countryCounts);
            $countryRows = DB::table('countries')
                ->whereIn('id', $countryIds)
                ->select('id', 'name_th', 'name_en', 'iso2', 'region', 'flag_emoji')
                ->get();
            foreach ($countryRows as $row) {
                $countries[] = [
                    'id' => $row->id,
                    'name_th' => $row->name_th,
                    'name_en' => $row->name_en,
                    'iso2' => $row->iso2,
                    'region' => $row->region,
                    'flag_emoji' => $row->flag_emoji ?? null,
                    'posts_count' => $countryCounts[$row->id] ?? 0,
                ];
            }
            usort($countries, fn($a, $b) => $b['posts_count'] - $a['posts_count']);
        }

        $cities = [];
        if (!empty($cityCounts)) {
            $cityIds = array_keys($cityCounts);
            $cityRows = DB::table('cities')
                ->whereIn('id', $cityIds)
                ->select('id', 'name_th', 'name_en', 'country_id')
                ->get();
            foreach ($cityRows as $row) {
                $cities[] = [
                    'id' => $row->id,
                    'name_th' => $row->name_th,
                    'name_en' => $row->name_en,
                    'country_id' => $row->country_id,
                    'posts_count' => $cityCounts[$row->id] ?? 0,
                ];
            }
            usort($cities, fn($a, $b) => $b['posts_count'] - $a['posts_count']);
        }

        return response()->json([
            'success' => true,
            'countries' => $countries,
            'cities' => $cities,
        ]);
    }

    public function publicPosts(Request $request): JsonResponse
    {
        $query = BlogPost::with('category:id,name,slug')
            ->published()
            ->orderByDesc('published_at');

        if ($request->filled('category')) {
            $query->whereHas('category', fn($q) => $q->where('slug', $request->category));
        }

        if ($request->filled('tag')) {
            $tag = $request->tag;
            $query->whereJsonContains('tags', $tag);
        }

        if ($request->filled('featured')) {
            $query->featured();
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        if ($request->filled('country_id')) {
            $query->whereJsonContains('country_ids', (int) $request->country_id);
        }

        if ($request->filled('city_id')) {
            $query->whereJsonContains('city_ids', (int) $request->city_id);
        }

        $posts = $query->paginate($request->integer('per_page', 12));

        return response()->json(['success' => true, ...$posts->toArray()]);
    }

    public function publicShow(string $slug): JsonResponse
    {
        $post = BlogPost::with('category:id,name,slug')
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        // Increment view count
        $post->increment('view_count');

        $settings = BlogPageSetting::getSettings();

        // Related posts from same category (always returned for backward compat)
        $relatedLimit = max(1, (int) ($settings->sidebar_related_posts_limit ?? 5));
        $related = BlogPost::with('category:id,name,slug')
            ->published()
            ->where('id', '!=', $post->id)
            ->when($post->category_id, fn($q) => $q->where('category_id', $post->category_id))
            ->orderByDesc('published_at')
            ->limit($relatedLimit)
            ->get();

        // Sidebar widgets data (only when enabled to avoid wasted queries)
        $sidebar = [];

        if ($settings->show_sidebar && $settings->sidebar_show_recent_posts) {
            $sidebar['recent_posts'] = BlogPost::with('category:id,name,slug')
                ->published()
                ->where('id', '!=', $post->id)
                ->orderByDesc('published_at')
                ->limit(max(1, (int) ($settings->sidebar_recent_posts_limit ?? 5)))
                ->get();
        }

        if ($settings->show_sidebar && $settings->sidebar_show_recommended_tours) {
            $tourLimit = max(1, (int) ($settings->sidebar_recommended_tours_limit ?? 3));
            $countryIds = (array) ($post->country_ids ?? []);

            $tourQuery = \App\Models\Tour::query()
                ->where('status', 'active')
                ->with(['primaryCountry:id,slug,name_th', 'countries:id,slug,name_th'])
                ->select('id', 'tour_code', 'title', 'slug', 'cover_image_url', 'custom_cover_image_url', 'cover_image_source', 'view_count', 'primary_country_id');

            // Prefer tours from the same countries as the post
            if (!empty($countryIds)) {
                $tourQuery->whereHas('countries', function ($q) use ($countryIds) {
                    $q->whereIn('countries.id', $countryIds);
                });
            }

            $tours = $tourQuery
                ->orderByDesc('view_count')
                ->limit($tourLimit)
                ->get();

            // Fallback: if no country-matched tours found, take top-viewed active tours
            if ($tours->isEmpty() && !empty($countryIds)) {
                $tours = \App\Models\Tour::query()
                    ->where('status', 'active')
                    ->with(['primaryCountry:id,slug,name_th', 'countries:id,slug,name_th'])
                    ->select('id', 'tour_code', 'title', 'slug', 'cover_image_url', 'custom_cover_image_url', 'cover_image_source', 'view_count', 'primary_country_id')
                    ->orderByDesc('view_count')
                    ->limit($tourLimit)
                    ->get();
            }

            $sidebar['recommended_tours'] = $tours->map(function ($t) {
                $country = $t->primaryCountry ?: $t->countries->first();
                return [
                    'id' => $t->id,
                    'tour_code' => $t->tour_code,
                    'title' => $t->title,
                    'slug' => $t->slug,
                    'cover_image_url' => $t->effective_cover_image_url,
                    'country_slug' => $country->slug ?? null,
                    'country_name' => $country->name_th ?? null,
                ];
            })->values();
        }

        return response()->json([
            'success' => true,
            'data' => $post,
            'related' => $related,
            'sidebar' => $sidebar,
            'settings' => $settings,
        ]);
    }

    // ============================
    // Admin: Categories
    // ============================

    public function listCategories(): JsonResponse
    {
        $categories = BlogCategory::withCount('posts')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:blog_categories,slug',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
            // Ensure unique
            $base = $validated['slug'];
            $i = 1;
            while (BlogCategory::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $base . '-' . $i++;
            }
        }

        $category = BlogCategory::create($validated);

        return response()->json(['success' => true, 'data' => $category, 'message' => 'เพิ่มหมวดหมู่สำเร็จ'], 201);
    }

    public function updateCategory(Request $request, BlogCategory $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:blog_categories,slug,' . $category->id,
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $category->update($validated);

        return response()->json(['success' => true, 'data' => $category->fresh(), 'message' => 'อัปเดตหมวดหมู่สำเร็จ']);
    }

    public function destroyCategory(BlogCategory $category): JsonResponse
    {
        $category->delete();
        return response()->json(['success' => true, 'message' => 'ลบหมวดหมู่สำเร็จ']);
    }

    public function reorderCategories(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:blog_categories,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['items'] as $item) {
            BlogCategory::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        $categories = BlogCategory::orderBy('sort_order')->orderByDesc('created_at')->get();
        return response()->json(['success' => true, 'data' => $categories, 'message' => 'เรียงลำดับสำเร็จ']);
    }

    // ============================
    // Admin: Posts
    // ============================

    public function listPosts(Request $request): JsonResponse
    {
        $query = BlogPost::with(['category:id,name,slug'])
            ->orderByDesc('created_at');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        $posts = $query->paginate(15);

        return response()->json(['success' => true, ...$posts->toArray()]);
    }

    public function showPost(BlogPost $post): JsonResponse
    {
        $post->load(['category:id,name,slug']);
        return response()->json(['success' => true, 'data' => $post]);
    }

    public function storePost(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:blog_categories,id',
            'country_ids' => 'nullable|array',
            'country_ids.*' => 'integer|exists:countries,id',
            'city_ids' => 'nullable|array',
            'city_ids.*' => 'integer|exists:cities,id',
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:blog_posts,slug',
            'excerpt' => 'nullable|string',
            'content' => 'nullable|string',
            'author_name' => 'nullable|string|max:100',
            'status' => 'in:draft,published,archived',
            'is_featured' => 'boolean',
            'published_at' => 'nullable|date',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string',
            'seo_keywords' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'reading_time_min' => 'nullable|integer|min:1',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
            $base = $validated['slug'];
            $i = 1;
            while (BlogPost::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $base . '-' . $i++;
            }
        }

        // Auto-set published_at if publishing
        if (($validated['status'] ?? 'draft') === 'published' && empty($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        // Auto-calculate reading time
        if (!empty($validated['content']) && empty($validated['reading_time_min'])) {
            $wordCount = str_word_count(strip_tags($validated['content']));
            $validated['reading_time_min'] = max(1, (int) ceil($wordCount / 200));
        }

        $post = BlogPost::create($validated);
        $post->load(['category:id,name,slug']);

        return response()->json(['success' => true, 'data' => $post, 'message' => 'สร้างบทความสำเร็จ'], 201);
    }

    public function updatePost(Request $request, BlogPost $post): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:blog_categories,id',
            'country_ids' => 'nullable|array',
            'country_ids.*' => 'integer|exists:countries,id',
            'city_ids' => 'nullable|array',
            'city_ids.*' => 'integer|exists:cities,id',
            'title' => 'sometimes|required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:blog_posts,slug,' . $post->id,
            'excerpt' => 'nullable|string',
            'content' => 'nullable|string',
            'author_name' => 'nullable|string|max:100',
            'status' => 'in:draft,published,archived',
            'is_featured' => 'boolean',
            'published_at' => 'nullable|date',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string',
            'seo_keywords' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'reading_time_min' => 'nullable|integer|min:1',
        ]);

        // Auto-set published_at if publishing for the first time
        if (($validated['status'] ?? $post->status) === 'published' && !$post->published_at && empty($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        // Auto-calculate reading time if content changed
        if (!empty($validated['content']) && empty($validated['reading_time_min'])) {
            $wordCount = str_word_count(strip_tags($validated['content']));
            $validated['reading_time_min'] = max(1, (int) ceil($wordCount / 200));
        }

        $post->update($validated);

        return response()->json(['success' => true, 'data' => $post->fresh()->load(['category:id,name,slug']), 'message' => 'อัปเดตบทความสำเร็จ']);
    }

    public function destroyPost(BlogPost $post): JsonResponse
    {
        // Safety (2026-07-17): if the Cloudflare delete fails, abort so we
        // don't orphan the file. The post row will be deleted only after the
        // image is actually gone (or was never on Cloudflare).
        if ($post->cover_image_cf_id && $this->cloudflare->isConfigured()) {
            $deleted = $this->cloudflare->delete($post->cover_image_cf_id);
            if (!$deleted) {
                Log::warning('destroyPost: Cloudflare delete failed, aborting', [
                    'post_id' => $post->id,
                    'cf_id' => $post->cover_image_cf_id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'ลบรูปปกจาก Cloudflare ไม่สำเร็จ — โปรดลองใหม่อีกครั้ง',
                ], 500);
            }
        }

        $post->delete();
        return response()->json(['success' => true, 'message' => 'ลบบทความสำเร็จ']);
    }

    public function uploadContentImage(Request $request, BlogPost $post): JsonResponse
    {
        $request->validate(['image' => 'required|image|max:10240']);

        $result = $this->cloudflare->uploadFromFile(
            $request->file('image'),
            'blog-content-' . $post->id . '-' . time()
        );

        $url = $this->cloudflare->getDisplayUrl($result['id'], 'public');

        return response()->json(['success' => true, 'url' => $url, 'cf_id' => $result['id'], 'message' => 'อัปโหลดรูปสำเร็จ']);
    }

    public function uploadCoverImage(Request $request, BlogPost $post): JsonResponse
    {
        $request->validate(['image' => 'required|image|max:10240']);

        if ($post->cover_image_cf_id) {
            try { $this->cloudflare->delete($post->cover_image_cf_id); } catch (\Exception $e) {}
        }

        $result = $this->cloudflare->uploadFromFile(
            $request->file('image'),
            'blog-cover-' . $post->id . '-' . time()
        );

        $post->update([
            'cover_image_url' => $this->cloudflare->getDisplayUrl($result['id'], 'public'),
            'cover_image_cf_id' => $result['id'],
        ]);

        return response()->json(['success' => true, 'data' => $post->fresh()->load(['category:id,name,slug']), 'message' => 'อัปโหลดรูปปกสำเร็จ']);
    }

    public function deleteCoverImage(BlogPost $post): JsonResponse
    {
        if ($post->cover_image_cf_id) {
            try { $this->cloudflare->delete($post->cover_image_cf_id); } catch (\Exception $e) {}
        }

        $post->update([
            'cover_image_url' => null,
            'cover_image_cf_id' => null,
        ]);

        return response()->json(['success' => true, 'data' => $post->fresh(), 'message' => 'ลบรูปปกสำเร็จ']);
    }

    // ============================
    // Admin: Page Settings
    // ============================

    public function getPageSettings(): JsonResponse
    {
        $settings = BlogPageSetting::getSettings();
        return response()->json(['success' => true, 'data' => $settings]);
    }

    public function updatePageSettings(Request $request): JsonResponse
    {
        $settings = BlogPageSetting::getSettings();

        $validated = $request->validate([
            'hero_title' => 'sometimes|string|max:255',
            'hero_subtitle' => 'nullable|string|max:1000',
            'hero_image_position' => 'sometimes|string|in:center,top,bottom',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'seo_keywords' => 'nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
            // Sidebar settings
            'show_sidebar' => 'sometimes|boolean',
            'sidebar_show_author' => 'sometimes|boolean',
            'sidebar_show_related_posts' => 'sometimes|boolean',
            'sidebar_show_recent_posts' => 'sometimes|boolean',
            'sidebar_show_recommended_tours' => 'sometimes|boolean',
            'sidebar_show_back_button' => 'sometimes|boolean',
            'sidebar_related_posts_limit' => 'sometimes|integer|min:1|max:20',
            'sidebar_recent_posts_limit' => 'sometimes|integer|min:1|max:20',
            'sidebar_recommended_tours_limit' => 'sometimes|integer|min:1|max:10',
            'sidebar_recommended_tours_title' => 'nullable|string|max:100',
            'sidebar_related_posts_title' => 'nullable|string|max:100',
            'sidebar_recent_posts_title' => 'nullable|string|max:100',
        ]);

        $settings->update($validated);
        return response()->json(['success' => true, 'data' => $settings->fresh(), 'message' => 'อัปเดตการตั้งค่าสำเร็จ']);
    }

    public function uploadHeroImage(Request $request): JsonResponse
    {
        $request->validate(['image' => 'required|image|max:10240']);

        $settings = BlogPageSetting::getSettings();

        if ($settings->hero_image_cf_id) {
            try { $this->cloudflare->delete($settings->hero_image_cf_id); } catch (\Exception $e) {}
        }

        $result = $this->cloudflare->uploadFromFile(
            $request->file('image'),
            'blog-hero-' . time()
        );

        $settings->update([
            'hero_image_url' => $this->cloudflare->getDisplayUrl($result['id'], 'public'),
            'hero_image_cf_id' => $result['id'],
        ]);

        return response()->json(['success' => true, 'data' => $settings->fresh(), 'message' => 'อัปโหลดรูป Hero สำเร็จ']);
    }

    public function deleteHeroImage(): JsonResponse
    {
        $settings = BlogPageSetting::getSettings();

        if ($settings->hero_image_cf_id) {
            try { $this->cloudflare->delete($settings->hero_image_cf_id); } catch (\Exception $e) {}
        }

        $settings->update([
            'hero_image_url' => null,
            'hero_image_cf_id' => null,
        ]);

        return response()->json(['success' => true, 'data' => $settings->fresh(), 'message' => 'ลบรูป Hero สำเร็จ']);
    }
}
