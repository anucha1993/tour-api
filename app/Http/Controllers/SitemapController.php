<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Country;
use App\Models\Tour;
use Illuminate\Http\JsonResponse;

/**
 * Lightweight sitemap data source for the public website (tour-web).
 *
 * Returns only the fields needed to build <url> entries (slug + last-modified)
 * so the Next.js sitemap.ts can generate sitemap.xml without over-fetching
 * heavy tour/blog payloads.
 */
class SitemapController extends Controller
{
    public function index(): JsonResponse
    {
        // Active tours that have at least one open, future departure.
        $today = now()->toDateString();
        $tours = Tour::query()
            ->where('status', 'active')
            ->whereNotNull('slug')
            ->whereHas('periods', fn ($q) => $q->displayable()->where('start_date', '>=', $today))
            ->with(['primaryCountry:id,slug', 'cities:id,slug'])
            ->select(
                'id',
                'slug',
                'primary_country_id',
                'updated_at',
                // Needed so the `effective_cover_image_url` accessor can resolve
                // between the auto cover and the custom cover set by admin.
                'cover_image_url',
                'custom_cover_image_url',
                'cover_image_source',
            )
            ->orderByDesc('updated_at')
            ->limit(5000)
            ->get()
            ->map(fn ($t) => [
                'slug' => $t->slug,
                'country_slug' => $t->primaryCountry?->slug,
                'city_slug' => $t->cities->first()?->slug,
                'updated_at' => $t->updated_at,
                // Image sitemap entry — helps Google Images index tour covers.
                // Emit only when we actually have a URL.
                'cover_image_url' => $t->effective_cover_image_url,
            ]);

        // Published blog posts.
        $blogs = BlogPost::query()
            ->where('status', 'published')
            ->where('published_at', '<=', now())
            ->whereNotNull('slug')
            ->select('slug', 'updated_at', 'cover_image_url')
            ->orderByDesc('updated_at')
            ->limit(5000)
            ->get();

        // Active countries (used for /tours/country/{slug}).
        $countries = Country::query()
            ->where('is_active', true)
            ->whereNotNull('slug')
            ->select('slug', 'updated_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'tours' => $tours,
                'blogs' => $blogs,
                'countries' => $countries,
            ],
        ]);
    }
}
