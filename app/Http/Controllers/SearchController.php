<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use App\Models\Country;
use App\Models\City;
use App\Models\FestivalHoliday;
use App\Models\SearchKeyword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SearchController extends Controller
{
    /**
     * GET /api/search/autocomplete?q=xxx
     * Fast autocomplete suggestions — grouped by type
     */
    public function autocomplete(Request $request): JsonResponse
    {
        $q = trim($request->input('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $results = [];

        // 1. Search Countries (name_th, name_en)
        $countries = Country::where('is_active', true)
            ->where(function ($query) use ($q) {
                $query->where('name_th', 'like', "%{$q}%")
                      ->orWhere('name_en', 'like', "%{$q}%");
            })
            ->select('id', 'name_th', 'name_en', 'slug', 'iso2', 'flag_emoji')
            ->limit(5)
            ->get();

        foreach ($countries as $country) {
            $tourCount = $country->tours()->where('status', 'active')->count();
            if ($tourCount > 0) {
                $results[] = [
                    'type' => 'country',
                    'id' => $country->id,
                    'title' => $country->name_th,
                    'subtitle' => $country->name_en,
                    'url' => '/tours/country/' . $country->slug,
                    'image' => $country->iso2 ? 'https://flagcdn.com/48x36/' . strtolower($country->iso2) . '.png' : null,
                    'icon' => $country->flag_emoji,
                    'tour_count' => $tourCount,
                ];
            }
        }

        // 2. Search Cities (name_th, name_en)
        $cities = City::where('is_active', true)
            ->where(function ($query) use ($q) {
                $query->where('name_th', 'like', "%{$q}%")
                      ->orWhere('name_en', 'like', "%{$q}%");
            })
            ->with('country:id,name_th,iso2')
            ->select('id', 'name_th', 'name_en', 'slug', 'country_id')
            ->limit(5)
            ->get();

        foreach ($cities as $city) {
            $tourCount = $city->tours()->where('status', 'active')->count();
            if ($tourCount > 0) {
                $results[] = [
                    'type' => 'city',
                    'id' => $city->id,
                    'title' => $city->name_th,
                    'subtitle' => $city->name_en . ($city->country ? ' · ' . $city->country->name_th : ''),
                    'url' => '/tours/city/' . $city->slug,
                    'image' => $city->country && $city->country->iso2 ? 'https://flagcdn.com/48x36/' . strtolower($city->country->iso2) . '.png' : null,
                    'tour_count' => $tourCount,
                ];
            }
        }

        // 3. Search Festivals
        $festivals = FestivalHoliday::active()
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('description', 'like', "%{$q}%");
            })
            ->select('id', 'name', 'slug', 'badge_icon', 'start_date', 'end_date')
            ->limit(3)
            ->get();

        foreach ($festivals as $festival) {
            $tourCount = $festival->getMatchingTourIds()->count();
            if ($tourCount > 0) {
                $results[] = [
                    'type' => 'festival',
                    'id' => $festival->id,
                    'title' => $festival->name,
                    'subtitle' => $festival->date_range_text,
                    'url' => '/tours/festival/' . $festival->slug,
                    'icon' => $festival->badge_icon,
                    'tour_count' => $tourCount,
                ];
            }
        }

        // 4. Search Tours (title, tour_code, keywords, hashtags)
        $tours = Tour::where('status', 'active')
            ->where(function ($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                      ->orWhere('tour_code', 'like', "%{$q}%")
                      ->orWhereRaw("JSON_SEARCH(keywords, 'one', ?) IS NOT NULL", ["%{$q}%"])
                      ->orWhereRaw("JSON_SEARCH(hashtags, 'one', ?) IS NOT NULL", ["%{$q}%"]);
            })
            ->with('primaryCountry:id,name_th,iso2')
            ->select('id', 'title', 'slug', 'tour_code', 'cover_image_url', 'min_price', 'duration_days', 'duration_nights', 'primary_country_id')
            ->orderByRaw("
                CASE
                    WHEN tour_code LIKE ? THEN 1
                    WHEN title LIKE ? THEN 2
                    WHEN title LIKE ? THEN 3
                    ELSE 4
                END
            ", ["%{$q}%", "{$q}%", "%{$q}%"])
            ->limit(8)
            ->get();

        foreach ($tours as $tour) {
            $results[] = [
                'type' => 'tour',
                'id' => $tour->id,
                'title' => $tour->title,
                'subtitle' => $tour->tour_code . ' · ' . $tour->duration_days . ' วัน ' . $tour->duration_nights . ' คืน',
                'url' => '/tours/' . $tour->slug,
                'image' => $tour->cover_image_url,
                'country' => $tour->primaryCountry ? $tour->primaryCountry->name_th : null,
                'country_flag' => $tour->primaryCountry && $tour->primaryCountry->iso2 ? 'https://flagcdn.com/48x36/' . strtolower($tour->primaryCountry->iso2) . '.png' : null,
                'price' => $tour->min_price ? number_format($tour->min_price, 0) : null,
            ];
        }

        return response()->json(['success' => true, 'data' => $results]);
    }

    /**
     * GET /api/search?q=xxx — Full search results page
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim($request->input('q', ''));
        $type = $request->input('type'); // tour, country, city, festival
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);

        if (mb_strlen($q) < 2) {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => ['total' => 0, 'current_page' => 1, 'last_page' => 1],
            ]);
        }

        // If type is specified, search only that type
        if ($type === 'tour' || !$type) {
            $toursQuery = Tour::where('status', 'active')
                ->where(function ($query) use ($q) {
                    $query->where('title', 'like', "%{$q}%")
                          ->orWhere('tour_code', 'like', "%{$q}%")
                          ->orWhere('description', 'like', "%{$q}%")
                          ->orWhereRaw("JSON_SEARCH(keywords, 'one', ?) IS NOT NULL", ["%{$q}%"])
                          ->orWhereRaw("JSON_SEARCH(hashtags, 'one', ?) IS NOT NULL", ["%{$q}%"])
                          ->orWhereHas('primaryCountry', function ($cq) use ($q) {
                              $cq->where('name_th', 'like', "%{$q}%")
                                  ->orWhere('name_en', 'like', "%{$q}%");
                          })
                          ->orWhereHas('cities', function ($cq) use ($q) {
                              $cq->where('name_th', 'like', "%{$q}%")
                                  ->orWhere('name_en', 'like', "%{$q}%");
                          });
                })
                ->with([
                    'primaryCountry:id,name_th,name_en,iso2',
                    'cities:id,name_th',
                    'periods' => function ($q) {
                        $q->where('status', 'open')
                          ->where('is_visible', true)
                          ->where('start_date', '>=', now()->toDateString())
                          ->orderBy('start_date')
                          ->limit(3);
                    },
                    'periods.offer',
                    'periods.offer.promotion',
                ])
                ->orderByRaw("
                    CASE
                        WHEN title LIKE ? THEN 1
                        WHEN title LIKE ? THEN 2
                        ELSE 3
                    END
                ", ["{$q}%", "%{$q}%"])
                ->orderBy('popularity_score', 'desc');

            $tours = $toursQuery->paginate($perPage);

            $formattedTours = collect($tours->items())->map(function ($tour) {
                return [
                    'id' => $tour->id,
                    'title' => $tour->title,
                    'slug' => $tour->slug,
                    'tour_code' => $tour->tour_code,
                    'cover_image_url' => $tour->cover_image_url,
                    'duration_days' => $tour->duration_days,
                    'duration_nights' => $tour->duration_nights,
                    'min_price' => $tour->min_price,
                    'display_price' => $tour->display_price,
                    'badge' => $tour->badge,
                    'hotel_star' => $tour->hotel_star,
                    'country' => $tour->primaryCountry ? [
                        'name_th' => $tour->primaryCountry->name_th,
                        'iso2' => strtolower($tour->primaryCountry->iso2 ?? ''),
                    ] : null,
                    'cities' => $tour->cities->pluck('name_th'),
                    'next_periods' => $tour->periods->map(fn($p) => [
                        'start_date' => $p->start_date?->format('Y-m-d'),
                        'end_date' => $p->end_date?->format('Y-m-d'),
                        'available' => $p->available,
                        'price' => $p->offer ? (float) ($p->offer->price_adult - $p->offer->discount_adult) : null,
                    ]),
                    'active_promotions' => $tour->periods
                        ->filter(fn($p) => $p->offer && ($p->offer->promo_name || $p->offer->promotion))
                        ->map(function ($p) {
                            $offer = $p->offer;
                            $name = $offer->promo_name ?? $offer->promotion?->name;
                            if (!$name) return null;
                            $today = now()->toDateString();
                            $start = $offer->promo_start_date?->format('Y-m-d');
                            $end = $offer->promo_end_date?->format('Y-m-d');
                            if ($start && $today < $start) return null;
                            if ($end && $today > $end) return null;
                            return ['name' => $name, 'start_date' => $start, 'end_date' => $end];
                        })
                        ->filter()
                        ->unique('name')
                        ->values(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedTours,
                'meta' => [
                    'total' => $tours->total(),
                    'current_page' => $tours->currentPage(),
                    'last_page' => $tours->lastPage(),
                    'per_page' => $tours->perPage(),
                ],
            ]);
        }

        return response()->json(['success' => true, 'data' => [], 'meta' => ['total' => 0]]);
    }

    /**
     * GET /api/search/suggestions?q=xxx — Keyword suggestions (like Google)
     * Returns popular/trending search keywords that match the input
     */
    public function suggestions(Request $request): JsonResponse
    {
        $q = trim($request->input('q', ''));

        // If no query, return top popular keywords
        if (mb_strlen($q) < 1) {
            $popular = SearchKeyword::where('search_count', '>=', 2)
                ->where('result_count', '>', 0)
                ->orderByDesc('search_count')
                ->limit(8)
                ->pluck('keyword')
                ->toArray();

            // Also add popular country names as suggestions
            $countryNames = Country::where('is_active', true)
                ->withCount(['tours' => fn($q) => $q->where('status', 'active')])
                ->having('tours_count', '>', 0)
                ->orderByDesc('tours_count')
                ->limit(6)
                ->get()
                ->map(fn($c) => $c->name_th)
                ->toArray();

            $suggestions = collect(array_merge($popular, $countryNames))
                ->unique()
                ->take(10)
                ->values()
                ->toArray();

            return response()->json(['success' => true, 'data' => $suggestions]);
        }

        // Search matching keywords
        $keywords = SearchKeyword::where('keyword', 'like', "%{$q}%")
            ->where('result_count', '>', 0)
            ->orderByRaw("CASE WHEN keyword LIKE ? THEN 0 ELSE 1 END", ["{$q}%"])
            ->orderByDesc('search_count')
            ->limit(8)
            ->pluck('keyword')
            ->toArray();

        // Also add matching country/city/festival names as keyword suggestions
        $countryNames = Country::where('is_active', true)
            ->where(function ($query) use ($q) {
                $query->where('name_th', 'like', "%{$q}%")
                      ->orWhere('name_en', 'like', "%{$q}%");
            })
            ->withCount(['tours' => fn($qr) => $qr->where('status', 'active')])
            ->having('tours_count', '>', 0)
            ->orderByDesc('tours_count')
            ->limit(3)
            ->get()
            ->map(fn($c) => $c->name_th)
            ->toArray();

        $festivalNames = FestivalHoliday::active()
            ->where('name', 'like', "%{$q}%")
            ->limit(3)
            ->pluck('name')
            ->toArray();

        // Merge and deduplicate
        $suggestions = collect(array_merge($keywords, $countryNames, $festivalNames))
            ->unique()
            ->take(10)
            ->values()
            ->toArray();

        return response()->json(['success' => true, 'data' => $suggestions]);
    }

    /**
     * POST /api/search/track — Track a search keyword
     */
    public function trackKeyword(Request $request): JsonResponse
    {
        $keyword = trim($request->input('keyword', ''));
        $resultCount = (int) $request->input('result_count', 0);

        if (mb_strlen($keyword) >= 2) {
            SearchKeyword::recordSearch($keyword, $resultCount);
        }

        return response()->json(['success' => true]);
    }

    /**
     * GET /api/search/popular — Popular search terms + trending tours
     */
    public function popular(): JsonResponse
    {
        $data = Cache::remember('search_popular', 600, function () {
            // Popular countries (by tour count)
            $popularCountries = Country::where('is_active', true)
                ->withCount(['tours' => fn($q) => $q->where('status', 'active')])
                ->having('tours_count', '>', 0)
                ->orderByDesc('tours_count')
                ->limit(6)
                ->get()
                ->map(fn($c) => [
                    'type' => 'country',
                    'title' => 'ทัวร์' . $c->name_th,
                    'url' => '/tours/country/' . $c->slug,
                    'icon' => $c->flag_emoji,
                    'image' => $c->iso2 ? 'https://flagcdn.com/48x36/' . strtolower($c->iso2) . '.png' : null,
                    'count' => $c->tours_count,
                ]);

            // Active festivals
            $festivals = FestivalHoliday::active()
                ->orderBy('start_date')
                ->limit(4)
                ->get()
                ->map(function ($f) {
                    $count = $f->getMatchingTourIds()->count();
                    return $count > 0 ? [
                        'type' => 'festival',
                        'title' => $f->name,
                        'url' => '/tours/festival/' . $f->slug,
                        'icon' => $f->badge_icon,
                        'count' => $count,
                    ] : null;
                })
                ->filter()
                ->values();

            // Trending tours (by view_count or popularity_score)
            $trendingTours = Tour::where('status', 'active')
                ->whereNotNull('min_price')
                ->with('primaryCountry:id,name_th,iso2')
                ->orderByDesc('popularity_score')
                ->limit(4)
                ->get()
                ->map(fn($t) => [
                    'type' => 'tour',
                    'title' => $t->title,
                    'url' => '/tours/' . $t->slug,
                    'image' => $t->cover_image_url,
                    'price' => $t->min_price ? number_format($t->min_price, 0) : null,
                    'country' => $t->primaryCountry?->name_th,
                ]);

            return [
                'popular_destinations' => $popularCountries,
                'festivals' => $festivals,
                'trending_tours' => $trendingTours,
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }
}
