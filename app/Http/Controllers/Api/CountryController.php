<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class CountryController extends Controller
{
    /**
     * Display a listing of countries.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Country::query();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('iso2', 'like', "%{$search}%")
                  ->orWhere('iso3', 'like', "%{$search}%")
                  ->orWhere('name_en', 'like', "%{$search}%")
                  ->orWhere('name_th', 'like', "%{$search}%");
            });
        }

        // Filter by region
        if ($request->filled('region')) {
            $query->where('region', $request->region);
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Order: active first, then by name
        $query->orderByRaw("CASE WHEN is_active = 1 THEN 0 ELSE 1 END")
              ->orderBy('name_en', 'asc');

        // Paginate
        $perPage = $request->input('per_page', 50);
        $countries = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $countries->items(),
            'meta' => [
                'current_page' => $countries->currentPage(),
                'last_page' => $countries->lastPage(),
                'per_page' => $countries->perPage(),
                'total' => $countries->total(),
            ],
        ]);
    }

    /**
     * Store a newly created country.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'iso2' => ['required', 'string', 'size:2', 'unique:countries,iso2'],
            'iso3' => ['required', 'string', 'size:3', 'unique:countries,iso3'],
            'name_en' => ['required', 'string', 'max:100'],
            'name_th' => ['nullable', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:countries,slug'],
            'region' => ['nullable', 'string', Rule::in(array_keys(Country::REGIONS))],
            'flag_emoji' => ['nullable', 'string', 'max:10'],
            'is_active' => ['boolean'],
        ]);

        // Auto-generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name_en']);
        }

        // Convert iso codes to uppercase
        $validated['iso2'] = strtoupper($validated['iso2']);
        $validated['iso3'] = strtoupper($validated['iso3']);

        $country = Country::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Country created successfully',
            'data' => $country,
        ], 201);
    }

    /**
     * Display the specified country.
     */
    public function show(Country $country): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $country,
        ]);
    }

    /**
     * Update the specified country.
     */
    public function update(Request $request, Country $country): JsonResponse
    {
        $validated = $request->validate([
            'iso2' => ['sometimes', 'string', 'size:2', Rule::unique('countries', 'iso2')->ignore($country->id)],
            'iso3' => ['sometimes', 'string', 'size:3', Rule::unique('countries', 'iso3')->ignore($country->id)],
            'name_en' => ['sometimes', 'string', 'max:100'],
            'name_th' => ['nullable', 'string', 'max:100'],
            'slug' => ['sometimes', 'string', 'max:100', Rule::unique('countries', 'slug')->ignore($country->id)],
            'region' => ['nullable', 'string', Rule::in(array_keys(Country::REGIONS))],
            'flag_emoji' => ['nullable', 'string', 'max:10'],
            'is_active' => ['boolean'],
        ]);

        // Convert iso codes to uppercase if provided
        if (isset($validated['iso2'])) {
            $validated['iso2'] = strtoupper($validated['iso2']);
        }
        if (isset($validated['iso3'])) {
            $validated['iso3'] = strtoupper($validated['iso3']);
        }

        $country->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Country updated successfully',
            'data' => $country,
        ]);
    }

    /**
     * Remove the specified country.
     */
    public function destroy(Country $country): JsonResponse
    {
        $country->delete();

        return response()->json([
            'success' => true,
            'message' => 'Country deleted successfully',
        ]);
    }

    /**
     * Toggle country active status.
     */
    public function toggleStatus(Country $country): JsonResponse
    {
        $country->update([
            'is_active' => !$country->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Country status updated',
            'data' => $country,
        ]);
    }

    /**
     * Get available regions.
     */
    public function regions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Country::REGIONS,
        ]);
    }
}
