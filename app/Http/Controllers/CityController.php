<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CityController extends Controller
{
    /**
     * Display a listing of cities.
     */
    public function index(Request $request): JsonResponse
    {
        $query = City::with('country:id,iso2,name_en,name_th');

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name_en', 'like', "%{$search}%")
                  ->orWhere('name_th', 'like', "%{$search}%");
            });
        }

        // Filter by country
        if ($request->filled('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        // Filter by popular
        if ($request->has('is_popular')) {
            $query->where('is_popular', filter_var($request->is_popular, FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // Sort: active first, popular first, then by name
        $query->orderByDesc('is_active')
              ->orderByDesc('is_popular')
              ->orderBy('name_en');

        $perPage = $request->get('per_page', 50);
        $cities = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $cities->items(),
            'meta' => [
                'current_page' => $cities->currentPage(),
                'last_page' => $cities->lastPage(),
                'per_page' => $cities->perPage(),
                'total' => $cities->total(),
            ],
        ]);
    }

    /**
     * Store a newly created city.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name_en' => 'required|string|max:150',
            'name_th' => 'nullable|string|max:150',
            'slug' => 'nullable|string|max:150|unique:cities,slug',
            'country_id' => 'required|exists:countries,id',
            'description' => 'nullable|string',
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // Generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name_en']);
            
            // Ensure unique slug
            $originalSlug = $validated['slug'];
            $count = 1;
            while (City::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $originalSlug . '-' . $count++;
            }
        }

        $city = City::create($validated);
        $city->load('country:id,iso2,name_en,name_th');

        return response()->json([
            'success' => true,
            'data' => $city,
            'message' => 'City created successfully',
        ], 201);
    }

    /**
     * Display the specified city.
     */
    public function show(City $city): JsonResponse
    {
        $city->load('country:id,iso2,name_en,name_th');

        return response()->json([
            'success' => true,
            'data' => $city,
        ]);
    }

    /**
     * Update the specified city.
     */
    public function update(Request $request, City $city): JsonResponse
    {
        $validated = $request->validate([
            'name_en' => 'required|string|max:150',
            'name_th' => 'nullable|string|max:150',
            'slug' => ['nullable', 'string', 'max:150', Rule::unique('cities', 'slug')->ignore($city->id)],
            'country_id' => 'required|exists:countries,id',
            'description' => 'nullable|string',
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // Generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name_en']);
            
            // Ensure unique slug
            $originalSlug = $validated['slug'];
            $count = 1;
            while (City::where('slug', $validated['slug'])->where('id', '!=', $city->id)->exists()) {
                $validated['slug'] = $originalSlug . '-' . $count++;
            }
        }

        $city->update($validated);
        $city->load('country:id,iso2,name_en,name_th');

        return response()->json([
            'success' => true,
            'data' => $city,
            'message' => 'City updated successfully',
        ]);
    }

    /**
     * Remove the specified city.
     */
    public function destroy(City $city): JsonResponse
    {
        $city->delete();

        return response()->json([
            'success' => true,
            'message' => 'City deleted successfully',
        ]);
    }

    /**
     * Toggle city status.
     */
    public function toggleStatus(City $city): JsonResponse
    {
        $city->update(['is_active' => !$city->is_active]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $city->id,
                'is_active' => $city->is_active,
            ],
            'message' => 'Status updated successfully',
        ]);
    }

    /**
     * Toggle city popular status.
     */
    public function togglePopular(City $city): JsonResponse
    {
        $city->update(['is_popular' => !$city->is_popular]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $city->id,
                'is_popular' => $city->is_popular,
            ],
            'message' => 'Popular status updated successfully',
        ]);
    }

    /**
     * Get countries list for dropdown.
     */
    public function countries(): JsonResponse
    {
        $countries = Country::active()
            ->orderBy('name_en')
            ->get(['id', 'iso2', 'name_en', 'name_th']);

        return response()->json([
            'success' => true,
            'data' => $countries,
        ]);
    }

    /**
     * Get countries with city count (grouped view).
     */
    public function countriesWithCities(Request $request): JsonResponse
    {
        $query = Country::query()
            ->withCount(['cities' => function ($q) use ($request) {
                // Optionally filter only active cities
                if ($request->has('active_only') && filter_var($request->active_only, FILTER_VALIDATE_BOOLEAN)) {
                    $q->where('is_active', true);
                }
            }])
            ->having('cities_count', '>', 0);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name_en', 'like', "%{$search}%")
                  ->orWhere('name_th', 'like', "%{$search}%")
                  ->orWhere('iso2', 'like', "%{$search}%");
            });
        }

        // Filter by region
        if ($request->filled('region')) {
            $query->where('region', $request->region);
        }

        // Sort by city count desc, then name
        $query->orderByDesc('cities_count')
              ->orderBy('name_en');

        $countries = $query->get(['id', 'iso2', 'iso3', 'name_en', 'name_th', 'region', 'is_active']);

        // Also get popular cities count per country
        $popularCounts = City::where('is_popular', true)
            ->selectRaw('country_id, COUNT(*) as popular_count')
            ->groupBy('country_id')
            ->pluck('popular_count', 'country_id');

        $data = $countries->map(function ($country) use ($popularCounts) {
            return [
                'id' => $country->id,
                'iso2' => $country->iso2,
                'iso3' => $country->iso3,
                'name_en' => $country->name_en,
                'name_th' => $country->name_th,
                'region' => $country->region,
                'is_active' => $country->is_active,
                'cities_count' => $country->cities_count,
                'popular_count' => $popularCounts[$country->id] ?? 0,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'total_countries' => $data->count(),
                'total_cities' => $data->sum('cities_count'),
            ],
        ]);
    }
}
