<?php

namespace App\Services;

use App\Models\WholesalerApiConfig;
use App\Services\WholesalerAdapters\AdapterFactory;
use App\Services\WholesalerAdapters\Adapters\GenericRestAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Unified Search Service
 * 
 * Provides realtime search across multiple wholesaler APIs
 * using transformed (unified) field names
 */
class UnifiedSearchService
{
    protected array $wholesalerConfigs = [];
    protected array $transformServices = [];
    
    // Cache TTL in seconds (5 minutes default)
    protected int $cacheTtl = 300;

    /**
     * Search tours across all active wholesalers
     * 
     * @param array $searchParams Unified search parameters
     * @param array|null $wholesalerIds Specific wholesalers to search (null = all active)
     * @return array Search results with unified format
     */
    public function searchTours(array $searchParams, ?array $wholesalerIds = null): array
    {
        // Get active wholesaler configs
        $query = WholesalerApiConfig::where('is_active', true);
        if ($wholesalerIds) {
            $query->whereIn('wholesaler_id', $wholesalerIds);
        }
        $configs = $query->with('wholesaler')->get();

        $results = [];
        $errors = [];

        foreach ($configs as $config) {
            try {
                $wholesalerResult = $this->searchWholesaler($config, $searchParams);
                $results = array_merge($results, $wholesalerResult['tours']);
            } catch (\Exception $e) {
                Log::error("UnifiedSearch: Error searching wholesaler {$config->wholesaler_id}", [
                    'error' => $e->getMessage(),
                ]);
                $errors[] = [
                    'wholesaler_id' => $config->wholesaler_id,
                    'wholesaler_name' => $config->wholesaler?->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Apply client-side filtering for params not supported by API
        $filteredResults = $this->applyClientFilters($results, $searchParams);

        // Sort results
        $sortedResults = $this->sortResults($filteredResults, $searchParams['_sort'] ?? null);

        return [
            'success' => true,
            'tours' => $sortedResults,
            'total' => count($sortedResults),
            'errors' => $errors,
            'meta' => [
                'search_params' => $searchParams,
                'wholesalers_searched' => $configs->count(),
                'searched_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Search a specific wholesaler
     */
    public function searchWholesaler(WholesalerApiConfig $config, array $searchParams): array
    {
        $wholesalerId = $config->wholesaler_id;

        // Get transform service
        $transformService = new TourTransformService($wholesalerId);
        
        // Get periods array key from mapping (e.g., "Periods" or "periods")
        $periodsArrayKey = $this->getPeriodsArrayKey($wholesalerId);

        // Get adapter
        $adapter = AdapterFactory::create($wholesalerId);

        // Reverse transform search params to API format
        $apiParams = $transformService->reverseTransformSearchParams($searchParams, 'tour');

        // Remove internal params
        unset($apiParams['_sort'], $apiParams['_limit'], $apiParams['_offset']);

        // Fetch tours from API
        $result = $adapter->fetchTours(null); // TODO: Pass search params to API if supported

        if (!$result->success) {
            throw new \Exception($result->errorMessage ?? 'Failed to fetch tours');
        }

        // Transform each tour to unified format
        $transformedTours = [];
        foreach ($result->tours as $tour) {
            $transformed = $transformService->transformToUnified($tour, 'tour');
            $transformed['_wholesaler_id'] = $wholesalerId;
            $transformed['_wholesaler_name'] = $config->wholesaler?->name;

            // Check if sync mode is NOT two-phase - periods are inline in tour data
            if ($config->sync_mode !== 'two_phase') {
                // Get periods array using mapped key (not hardcoded)
                $rawPeriods = $tour[$periodsArrayKey] ?? [];
                if (!empty($rawPeriods)) {
                    $transformed['periods'] = array_map(
                        fn($p) => $transformService->transformToUnified($p, 'departure'),
                        $rawPeriods
                    );
                }
            }
            // Two-phase sync - fetch periods from separate endpoint
            elseif ($adapter instanceof GenericRestAdapter) {
                $transformed = $this->fetchAndTransformPeriods($adapter, $transformService, $config, $tour, $transformed);
            }

            $transformedTours[] = $transformed;
        }

        // Apply client-side filters
        $filteredTours = $this->applyClientFilters($transformedTours, $searchParams);

        // Filter periods within each tour based on search params
        $filteredTours = $this->filterPeriodsWithinTours($filteredTours, $searchParams);

        // Sort results
        $sortedTours = $this->sortResults($filteredTours, $searchParams['_sort'] ?? null);

        return [
            'tours' => $sortedTours,
            'total' => count($sortedTours),
        ];
    }

    /**
     * Fetch and transform periods for two-phase sync
     */
    protected function fetchAndTransformPeriods(
        GenericRestAdapter $adapter,
        TourTransformService $transformService,
        WholesalerApiConfig $config,
        array $rawTour,
        array $transformedTour
    ): array {
        $credentials = $config->auth_credentials ?? [];
        $periodsEndpoint = $credentials['endpoints']['periods'] ?? null;

        if (!$periodsEndpoint) {
            return $transformedTour;
        }

        // Build endpoint with placeholders replaced
        $endpoint = $periodsEndpoint;
        if (preg_match_all('/\{([^}]+)\}/', $periodsEndpoint, $matches)) {
            foreach ($matches[1] as $fieldName) {
                $value = $rawTour[$fieldName] ?? $rawTour['id'] ?? $rawTour['code'] ?? null;
                if ($value !== null) {
                    $endpoint = str_replace('{' . $fieldName . '}', $value, $endpoint);
                }
            }
        }

        // Fetch periods
        if (!preg_match('/\{[^}]+\}/', $endpoint)) {
            $periodsResult = $adapter->fetchPeriods($endpoint);
            if ($periodsResult->success && !empty($periodsResult->periods)) {
                // Transform periods
                $transformedPeriods = [];
                foreach ($periodsResult->periods as $period) {
                    $transformedPeriods[] = $transformService->transformToUnified($period, 'period');
                }
                $transformedTour['periods'] = $transformedPeriods;
            }
        }

        return $transformedTour;
    }

    /**
     * Apply client-side filters for params not supported by API
     * Checks both unified fields and _raw fields
     */
    protected function applyClientFilters(array $tours, array $searchParams): array
    {
        return array_filter($tours, function ($tour) use ($searchParams) {
            $raw = $tour['_raw'] ?? [];

            // Filter by country - check both code (iso2) and name
            if (!empty($searchParams['country'])) {
                $searchCountry = strtoupper($searchParams['country']);
                
                // Expand search terms using country aliases
                $searchTerms = $this->expandCountrySearch($searchCountry);
                
                // Collect all country identifiers for matching
                $countryValues = [];
                
                // From unified field (could be code or name)
                if (!empty($tour['primary_country_id'])) {
                    $countryValues[] = strtoupper($tour['primary_country_id']);
                }
                
                // From raw countries array - get both code AND name
                $rawCountries = $raw['countries'] ?? $raw['Countries'] ?? [];
                if (!empty($rawCountries) && is_array($rawCountries)) {
                    foreach ($rawCountries as $country) {
                        if (!empty($country['code'])) {
                            $countryValues[] = strtoupper($country['code']);
                        }
                        if (!empty($country['name'])) {
                            $countryValues[] = strtoupper($country['name']);
                        }
                    }
                }
                
                // Fallback to legacy raw fields
                if (empty($countryValues)) {
                    $legacy = $raw['CountryName'] ?? $raw['countryName'] ?? $raw['country'] ?? null;
                    if ($legacy) {
                        $countryValues[] = strtoupper($legacy);
                    }
                }
                
                // Check if any country value matches any search term
                if (!empty($countryValues)) {
                    $matched = false;
                    foreach ($searchTerms as $term) {
                        foreach ($countryValues as $countryVal) {
                            if (stripos($countryVal, $term) !== false || stripos($term, $countryVal) !== false) {
                                $matched = true;
                                break 2;
                            }
                        }
                    }
                    if (!$matched) {
                        return false;
                    }
                }
            }

            // Filter by keyword (search in title, code, highlight, locations) - check unified and raw
            if (!empty($searchParams['keyword'])) {
                $keyword = strtolower($searchParams['keyword']);
                
                // Build searchable text from multiple fields
                $title = $tour['title'] ?? '';
                if (!$title) {
                    $title = $raw['ProductName'] ?? $raw['name'] ?? $raw['TourName'] ?? '';
                }
                
                $code = $tour['wholesaler_tour_code'] ?? $raw['ProductCode'] ?? $raw['code'] ?? '';
                
                // Additional searchable fields: highlight, locations, country
                $highlight = $raw['Highlight'] ?? $raw['highlight'] ?? $raw['description'] ?? '';
                
                // Locations can be array or string
                $locations = $raw['Locations'] ?? $raw['locations'] ?? [];
                $locationText = is_array($locations) ? implode(' ', $locations) : (string)$locations;
                
                // City names
                $cityText = $raw['CityName'] ?? $raw['cityName'] ?? '';
                
                // Country
                $countryText = $raw['CountryName'] ?? $raw['countryName'] ?? $tour['primary_country_id'] ?? '';
                
                // Combine all searchable text
                $searchText = strtolower($title . ' ' . $code . ' ' . $highlight . ' ' . $locationText . ' ' . $cityText . ' ' . $countryText);
                
                if (strpos($searchText, $keyword) === false) {
                    return false;
                }
            }

            // Get periods - check both unified and raw
            $periods = $tour['periods'] ?? [];
            if (empty($periods) && !empty($raw['Periods'])) {
                // Use raw periods
                $periods = array_map(fn($p) => ['_raw' => $p], $raw['Periods']);
            }

            // Filter by date range
            if (!empty($searchParams['departure_from'])) {
                $hasValidPeriod = false;
                foreach ($periods as $period) {
                    $pRaw = $period['_raw'] ?? $period;
                    $depDate = $period['departure_date'] 
                        ?? $pRaw['PeriodStartDate'] 
                        ?? $pRaw['departureDate'] 
                        ?? $pRaw['start_date'] 
                        ?? null;
                    if ($depDate && $depDate >= $searchParams['departure_from']) {
                        $hasValidPeriod = true;
                        break;
                    }
                }
                if (!$hasValidPeriod && !empty($periods)) {
                    return false;
                }
            }

            if (!empty($searchParams['departure_to'])) {
                $hasValidPeriod = false;
                foreach ($periods as $period) {
                    $pRaw = $period['_raw'] ?? $period;
                    $depDate = $period['departure_date'] 
                        ?? $pRaw['PeriodStartDate'] 
                        ?? $pRaw['departureDate'] 
                        ?? $pRaw['start_date'] 
                        ?? null;
                    if ($depDate && $depDate <= $searchParams['departure_to']) {
                        $hasValidPeriod = true;
                        break;
                    }
                }
                if (!$hasValidPeriod && !empty($periods)) {
                    return false;
                }
            }

            // Filter by price range
            if (!empty($searchParams['min_price'])) {
                $hasValidPeriod = false;
                foreach ($periods as $period) {
                    $pRaw = $period['_raw'] ?? $period;
                    $price = $period['price_adult'] 
                        ?? $period['price'] 
                        ?? $pRaw['Price'] 
                        ?? $pRaw['adultPrice'] 
                        ?? $pRaw['salePrice'] 
                        ?? 0;
                    if ($price >= $searchParams['min_price']) {
                        $hasValidPeriod = true;
                        break;
                    }
                }
                if (!$hasValidPeriod && !empty($periods)) {
                    return false;
                }
            }

            if (!empty($searchParams['max_price'])) {
                $hasValidPeriod = false;
                foreach ($periods as $period) {
                    $pRaw = $period['_raw'] ?? $period;
                    $price = $period['price_adult'] 
                        ?? $period['price'] 
                        ?? $pRaw['Price'] 
                        ?? $pRaw['adultPrice'] 
                        ?? $pRaw['salePrice'] 
                        ?? PHP_INT_MAX;
                    if ($price <= $searchParams['max_price']) {
                        $hasValidPeriod = true;
                        break;
                    }
                }
                if (!$hasValidPeriod && !empty($periods)) {
                    return false;
                }
            }

            // Filter by available seats
            if (!empty($searchParams['min_seats'])) {
                $hasValidPeriod = false;
                foreach ($periods as $period) {
                    $pRaw = $period['_raw'] ?? $period;
                    $seats = $period['available_seats'] 
                        ?? $period['available'] 
                        ?? $pRaw['Seat'] 
                        ?? $pRaw['available'] 
                        ?? 0;
                    if ($seats >= $searchParams['min_seats']) {
                        $hasValidPeriod = true;
                        break;
                    }
                }
                if (!$hasValidPeriod && !empty($periods)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Expand country search term to include aliases
     * Loads from countries table in database
     */
    protected function expandCountrySearch(string $searchCountry): array
    {
        $searchCountry = strtoupper(trim($searchCountry));
        $terms = [$searchCountry];
        
        // Search in countries table for matching country
        $country = \App\Models\Country::where(function ($q) use ($searchCountry) {
            $q->whereRaw('UPPER(iso2) = ?', [$searchCountry])
              ->orWhereRaw('UPPER(iso3) = ?', [$searchCountry])
              ->orWhereRaw('UPPER(name_en) = ?', [$searchCountry])
              ->orWhereRaw('UPPER(name_th) = ?', [$searchCountry])
              ->orWhereRaw('UPPER(name_en) LIKE ?', ['%' . $searchCountry . '%'])
              ->orWhereRaw('UPPER(name_th) LIKE ?', ['%' . $searchCountry . '%']);
        })->first();
        
        if ($country) {
            // Add all identifiers for this country
            if ($country->iso2) $terms[] = strtoupper($country->iso2);
            if ($country->iso3) $terms[] = strtoupper($country->iso3);
            if ($country->name_en) $terms[] = strtoupper($country->name_en);
            if ($country->name_th) $terms[] = strtoupper($country->name_th);
        }
        
        return array_unique($terms);
    }

    /**
     * Sort results
     */
    protected function sortResults(array $tours, ?string $sortBy): array
    {
        if (!$sortBy) {
            return array_values($tours);
        }

        $direction = 'asc';
        if (str_starts_with($sortBy, '-')) {
            $direction = 'desc';
            $sortBy = substr($sortBy, 1);
        }

        usort($tours, function ($a, $b) use ($sortBy, $direction) {
            $valueA = $a[$sortBy] ?? null;
            $valueB = $b[$sortBy] ?? null;

            // Handle nested period values (e.g., sort by lowest price)
            if ($sortBy === 'price' || $sortBy === 'price_adult') {
                $valueA = $this->getLowestPeriodPrice($a);
                $valueB = $this->getLowestPeriodPrice($b);
            }

            if ($sortBy === 'departure_date') {
                $valueA = $this->getEarliestDepartureDate($a);
                $valueB = $this->getEarliestDepartureDate($b);
            }

            if ($valueA === $valueB) return 0;
            if ($valueA === null) return 1;
            if ($valueB === null) return -1;

            $result = $valueA <=> $valueB;
            return $direction === 'desc' ? -$result : $result;
        });

        return array_values($tours);
    }

    protected function getLowestPeriodPrice(array $tour): ?float
    {
        $periods = $tour['periods'] ?? [];
        if (empty($periods)) return null;

        $prices = array_map(fn($p) => $p['price_adult'] ?? $p['price'] ?? PHP_INT_MAX, $periods);
        return min($prices);
    }

    protected function getEarliestDepartureDate(array $tour): ?string
    {
        $periods = $tour['periods'] ?? [];
        if (empty($periods)) return null;

        $dates = array_filter(array_map(fn($p) => $p['departure_date'] ?? null, $periods));
        return !empty($dates) ? min($dates) : null;
    }

    /**
     * Filter periods within each tour based on search params
     * This removes periods that don't match the date/price/seat criteria
     */
    protected function filterPeriodsWithinTours(array $tours, array $searchParams): array
    {
        $departureFrom = $searchParams['departure_from'] ?? null;
        $departureTo = $searchParams['departure_to'] ?? null;
        $minPrice = $searchParams['min_price'] ?? null;
        $maxPrice = $searchParams['max_price'] ?? null;
        $minSeats = $searchParams['min_seats'] ?? null;

        // If no filter params, return as-is
        if (!$departureFrom && !$departureTo && !$minPrice && !$maxPrice && !$minSeats) {
            return $tours;
        }

        return array_map(function ($tour) use ($departureFrom, $departureTo, $minPrice, $maxPrice, $minSeats) {
            $periods = $tour['periods'] ?? [];
            
            if (empty($periods)) {
                return $tour;
            }

            $filteredPeriods = array_filter($periods, function ($period) use ($departureFrom, $departureTo, $minPrice, $maxPrice, $minSeats) {
                $pRaw = $period['_raw'] ?? $period;

                // Get departure date
                $depDate = $period['departure_date'] 
                    ?? $pRaw['PeriodStartDate'] 
                    ?? $pRaw['departureDate'] 
                    ?? $pRaw['start_date'] 
                    ?? null;

                // Filter by departure_from
                if ($departureFrom && $depDate && $depDate < $departureFrom) {
                    return false;
                }

                // Filter by departure_to
                if ($departureTo && $depDate && $depDate > $departureTo) {
                    return false;
                }

                // Get price
                $price = $period['price_adult'] 
                    ?? $period['price'] 
                    ?? $pRaw['Price'] 
                    ?? $pRaw['adultPrice'] 
                    ?? $pRaw['salePrice'] 
                    ?? 0;

                // Filter by min_price
                if ($minPrice && $price < $minPrice) {
                    return false;
                }

                // Filter by max_price
                if ($maxPrice && $price > $maxPrice) {
                    return false;
                }

                // Get available seats
                $seats = $period['available_seats'] 
                    ?? $period['available'] 
                    ?? $pRaw['Seat'] 
                    ?? $pRaw['available'] 
                    ?? $pRaw['AvailableSeats'] 
                    ?? 0;

                // Filter by min_seats
                if ($minSeats && $seats < $minSeats) {
                    return false;
                }

                return true;
            });

            $tour['periods'] = array_values($filteredPeriods);
            return $tour;
        }, $tours);
    }

    /**
     * Get periods array key from mapping
     * 
     * Looks at departure section mapping to find the array key (e.g., "Periods", "periods", "departures")
     */
    protected function getPeriodsArrayKey(int $wholesalerId): string
    {
        $mapping = \App\Models\WholesalerFieldMapping::where('wholesaler_id', $wholesalerId)
            ->where('section_name', 'departure')
            ->whereNotNull('their_field_path')
            ->first(['their_field_path']);

        if ($mapping && preg_match('/^(\w+)\[\]/', $mapping->their_field_path, $matches)) {
            return $matches[1]; // e.g., "Periods", "periods", "departures"
        }

        // Fallback to common names
        return 'periods';
    }
}
