<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UnifiedSearchService;
use App\Services\TourTransformService;
use App\Models\WholesalerApiConfig;
use App\Services\WholesalerAdapters\AdapterFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Unified Tour Search Controller
 * 
 * Provides realtime search across wholesaler APIs
 * using unified/transformed field names
 */
class TourSearchController extends Controller
{
    /**
     * Search tours across all active wholesalers (realtime)
     * 
     * GET /api/tours/search?keyword=ญี่ปุ่น&departure_from=2026-04-01&max_price=50000
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keyword' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'departure_from' => 'nullable|date',
            'departure_to' => 'nullable|date',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'min_seats' => 'nullable|integer|min:1',
            'duration_days' => 'nullable|integer|min:1',
            'wholesaler_ids' => 'nullable|array',
            'wholesaler_ids.*' => 'integer',
            '_sort' => 'nullable|string|in:price,-price,departure_date,-departure_date,title,-title',
            '_limit' => 'nullable|integer|min:1|max:500',
            '_offset' => 'nullable|integer|min:0',
        ]);

        try {
            $searchService = new UnifiedSearchService();
            $results = $searchService->searchTours(
                $validated,
                $validated['wholesaler_ids'] ?? null
            );

            // Apply pagination
            $limit = $validated['_limit'] ?? 50;
            $offset = $validated['_offset'] ?? 0;
            $tours = array_slice($results['tours'], $offset, $limit);

            return response()->json([
                'success' => true,
                'data' => $tours,
                'pagination' => [
                    'total' => $results['total'],
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $results['total'],
                ],
                'meta' => $results['meta'],
                'errors' => $results['errors'],
            ]);

        } catch (\Exception $e) {
            Log::error('TourSearch: Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Search failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search tours from a specific wholesaler (realtime)
     * 
     * GET /api/integrations/{id}/tours/search
     */
    public function searchWholesaler(Request $request, int $id): JsonResponse
    {
        $config = WholesalerApiConfig::where('id', $id)->with('wholesaler')->firstOrFail();

        $validated = $request->validate([
            'keyword' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'departure_from' => 'nullable|date',
            'departure_to' => 'nullable|date',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'min_seats' => 'nullable|integer|min:1',
            '_sort' => 'nullable|string|in:price,-price,departure_date,-departure_date,title,-title',
            '_limit' => 'nullable|integer|min:1|max:500',
            '_offset' => 'nullable|integer|min:0',
            'with_periods' => 'nullable|boolean',
        ]);

        try {
            $searchService = new UnifiedSearchService();
            $results = $searchService->searchWholesaler($config, $validated);

            // Apply pagination
            $limit = $validated['_limit'] ?? 50;
            $offset = $validated['_offset'] ?? 0;
            $tours = array_slice($results['tours'], $offset, $limit);

            return response()->json([
                'success' => true,
                'data' => $tours,
                'pagination' => [
                    'total' => $results['total'],
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $results['total'],
                ],
                'meta' => [
                    'wholesaler_id' => $config->wholesaler_id,
                    'wholesaler_name' => $config->wholesaler?->name,
                    'searched_at' => now()->toIso8601String(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('TourSearch: Wholesaler search error', [
                'wholesaler_id' => $config->wholesaler_id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Search failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get tour detail with realtime periods from API
     * 
     * GET /api/integrations/{id}/tours/{tourId}
     */
    public function getTourDetail(Request $request, int $id, string $tourId): JsonResponse
    {
        $config = WholesalerApiConfig::findOrFail($id);

        try {
            $transformService = new TourTransformService($config->wholesaler_id);
            $adapter = AdapterFactory::create($config->wholesaler_id);

            // Fetch tour detail
            $tourDetail = null;
            if (method_exists($adapter, 'fetchTourDetail')) {
                $tourDetail = $adapter->fetchTourDetail($tourId);
            }

            if (!$tourDetail) {
                // Fallback: search in all tours
                $result = $adapter->fetchTours(null);
                if ($result->success) {
                    foreach ($result->tours as $tour) {
                        $id = $tour['id'] ?? $tour['code'] ?? null;
                        if ($id == $tourId) {
                            $tourDetail = $tour;
                            break;
                        }
                    }
                }
            }

            if (!$tourDetail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tour not found',
                ], 404);
            }

            // Transform tour
            $transformed = $transformService->transformToUnified($tourDetail, 'tour');
            $transformed['_wholesaler_id'] = $config->wholesaler_id;

            // Fetch periods if two-phase
            $credentials = $config->auth_credentials ?? [];
            $periodsEndpoint = $credentials['endpoints']['periods'] ?? null;

            if ($periodsEndpoint && $adapter instanceof \App\Services\WholesalerAdapters\Adapters\GenericRestAdapter) {
                $endpoint = $this->buildEndpoint($periodsEndpoint, $tourDetail);
                if ($endpoint) {
                    $periodsResult = $adapter->fetchPeriods($endpoint);
                    if ($periodsResult->success) {
                        $transformed['periods'] = array_map(
                            fn($p) => $transformService->transformToUnified($p, 'period'),
                            $periodsResult->periods
                        );
                    }
                }
            }

            // Fetch itineraries if endpoint configured
            $itinerariesEndpoint = $credentials['endpoints']['itineraries'] ?? null;

            if ($itinerariesEndpoint && $adapter instanceof \App\Services\WholesalerAdapters\Adapters\GenericRestAdapter) {
                $endpoint = $this->buildEndpoint($itinerariesEndpoint, $tourDetail);
                if ($endpoint) {
                    $itinerariesResult = $adapter->fetchItineraries($endpoint);
                    if ($itinerariesResult->success) {
                        $transformed['itineraries'] = array_map(
                            fn($i) => $transformService->transformToUnified($i, 'itinerary'),
                            $itinerariesResult->itineraries
                        );
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => $transformed,
            ]);

        } catch (\Exception $e) {
            Log::error('TourSearch: Get detail error', [
                'tour_id' => $tourId,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available filters/fields for search
     * 
     * GET /api/tours/search/filters
     */
    public function getFilters(): JsonResponse
    {
        // Standard unified filters
        $filters = [
            [
                'name' => 'keyword',
                'label' => 'คำค้นหา',
                'type' => 'text',
                'description' => 'ค้นหาจากชื่อทัวร์',
            ],
            [
                'name' => 'country',
                'label' => 'ประเทศ',
                'type' => 'text',
                'description' => 'กรองตามประเทศ',
            ],
            [
                'name' => 'departure_from',
                'label' => 'วันเดินทาง (เริ่ม)',
                'type' => 'date',
                'description' => 'วันเดินทางตั้งแต่',
            ],
            [
                'name' => 'departure_to',
                'label' => 'วันเดินทาง (สิ้นสุด)',
                'type' => 'date',
                'description' => 'วันเดินทางถึง',
            ],
            [
                'name' => 'min_price',
                'label' => 'ราคาต่ำสุด',
                'type' => 'number',
                'description' => 'ราคาต่ำสุด (บาท)',
            ],
            [
                'name' => 'max_price',
                'label' => 'ราคาสูงสุด',
                'type' => 'number',
                'description' => 'ราคาสูงสุด (บาท)',
            ],
            [
                'name' => 'min_seats',
                'label' => 'ที่นั่งว่าง',
                'type' => 'number',
                'description' => 'จำนวนที่นั่งว่างขั้นต่ำ',
            ],
            [
                'name' => 'duration_days',
                'label' => 'จำนวนวัน',
                'type' => 'number',
                'description' => 'ระยะเวลาทริป (วัน)',
            ],
        ];

        // Get active wholesalers
        $wholesalers = WholesalerApiConfig::where('is_active', true)
            ->with('wholesaler')
            ->get()
            ->map(fn($c) => [
                'id' => $c->wholesaler_id,
                'name' => $c->wholesaler?->name,
            ]);

        // Predefined country list with codes
        $countries = [
            'CHINA', 'JAPAN', 'KOREA', 'TAIWAN', 'VIETNAM', 'THAILAND',
            'SINGAPORE', 'MALAYSIA', 'INDONESIA', 'INDIA', 'NEPAL', 'BHUTAN',
            'MALDIVES', 'SRI LANKA', 'TURKEY', 'GEORGIA', 'AZERBAIJAN', 
            'UZBEKISTAN', 'KAZAKHSTAN', 'RUSSIA', 'MONGOLIA', 'EUROPE',
            'FRANCE', 'ITALY', 'SWITZERLAND', 'GERMANY', 'ENGLAND', 'SPAIN',
            'PORTUGAL', 'GREECE', 'CROATIA', 'ICELAND', 'NORWAY', 'FINLAND',
            'SWEDEN', 'DENMARK', 'AUSTRALIA', 'NEW ZEALAND', 'USA', 'CANADA',
            'EGYPT', 'MOROCCO', 'SOUTH AFRICA', 'DUBAI', 'JORDAN', 'ISRAEL',
        ];

        return response()->json([
            'success' => true,
            'filters' => $filters,
            'wholesalers' => $wholesalers,
            'countries' => $countries,
            'sort_options' => [
                ['value' => 'price', 'label' => 'ราคาต่ำ → สูง'],
                ['value' => '-price', 'label' => 'ราคาสูง → ต่ำ'],
                ['value' => 'departure_date', 'label' => 'วันเดินทางใกล้สุด'],
                ['value' => '-departure_date', 'label' => 'วันเดินทางไกลสุด'],
                ['value' => 'title', 'label' => 'ชื่อ A-Z'],
            ],
        ]);
    }

    /**
     * Build endpoint URL with placeholders replaced
     */
    protected function buildEndpoint(string $endpointTemplate, array $tourData): ?string
    {
        $endpoint = $endpointTemplate;

        if (preg_match_all('/\{([^}]+)\}/', $endpointTemplate, $matches)) {
            foreach ($matches[1] as $fieldName) {
                $value = $tourData[$fieldName] ?? $tourData['id'] ?? $tourData['code'] ?? null;
                if ($value !== null) {
                    $endpoint = str_replace('{' . $fieldName . '}', $value, $endpoint);
                }
            }
        }

        // Check if all placeholders were replaced
        if (preg_match('/\{[^}]+\}/', $endpoint)) {
            return null;
        }

        return $endpoint;
    }

    /**
     * Lookup tour codes by external_id
     * 
     * POST /api/tours/lookup-codes
     * Body: { "external_ids": [{ "wholesaler_id": 1, "external_id": "12345" }, ...] }
     * 
     * Returns mapping of external_id to tour_code (null if not synced)
     */
    public function lookupTourCodes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'external_ids' => 'required|array|max:200',
            'external_ids.*.wholesaler_id' => 'required|numeric',
            'external_ids.*.external_id' => 'required|string',
        ]);

        try {
            $lookupMap = [];
            
            // Group by wholesaler_id for efficient querying
            $grouped = collect($validated['external_ids'])->groupBy('wholesaler_id');
            
            foreach ($grouped as $wholesalerId => $items) {
                $externalIds = $items->pluck('external_id')->toArray();
                
                // Query tours with these external_ids
                $tours = \App\Models\Tour::where('wholesaler_id', $wholesalerId)
                    ->whereIn('external_id', $externalIds)
                    ->select('external_id', 'tour_code', 'id', 'title', 'sync_status')
                    ->get()
                    ->keyBy('external_id');
                
                foreach ($externalIds as $extId) {
                    $key = "{$wholesalerId}_{$extId}";
                    if (isset($tours[$extId])) {
                        $tour = $tours[$extId];
                        $lookupMap[$key] = [
                            'synced' => true,
                            'tour_id' => $tour->id,
                            'tour_code' => $tour->tour_code,
                            'sync_status' => $tour->sync_status,
                        ];
                    } else {
                        $lookupMap[$key] = [
                            'synced' => false,
                            'tour_id' => null,
                            'tour_code' => null,
                            'sync_status' => null,
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => $lookupMap,
            ]);

        } catch (\Exception $e) {
            Log::error('TourSearch: lookupTourCodes error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Lookup failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
