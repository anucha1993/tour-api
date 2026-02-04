<?php

namespace App\Services;

use App\Models\WholesalerApiConfig;
use App\Models\WholesalerFieldMapping;
use Illuminate\Support\Facades\Log;

/**
 * Service for transforming tour data between API format and unified format
 * 
 * Supports:
 * - Forward Transform: API data → Unified format (using field mappings)
 * - Reverse Transform: Search params (unified) → API query params
 */
class TourTransformService
{
    protected array $mappings = [];
    protected array $reverseMappings = [];
    protected ?WholesalerApiConfig $config = null;

    public function __construct(int $wholesalerId)
    {
        $this->config = WholesalerApiConfig::where('wholesaler_id', $wholesalerId)->first();
        $this->loadMappings($wholesalerId);
    }

    /**
     * Load field mappings for this wholesaler
     */
    protected function loadMappings(int $wholesalerId): void
    {
        $mappings = WholesalerFieldMapping::where('wholesaler_id', $wholesalerId)
            ->where('is_active', true)
            ->get();

        foreach ($mappings as $mapping) {
            // Use correct column names from database
            $section = $mapping->section_name;
            $targetField = $mapping->our_field;
            $apiFieldPath = $mapping->their_field_path;
            $transformType = $mapping->transform_type;
            $transformConfig = $mapping->transform_config;

            // Skip if no API field path
            if (empty($apiFieldPath)) {
                continue;
            }

            // For departure section, strip array prefixes like:
            // "Periods[].PeriodStartDate" -> "PeriodStartDate"
            // "periods[].tour_period[].price" -> "price"
            // This is because when transforming individual period items, they don't have the array prefix
            $cleanApiField = $apiFieldPath;
            if ($section === 'departure' || $section === 'period') {
                // Remove all array path segments (e.g., "periods[].tour_period[].")
                $cleanApiField = preg_replace('/^\w+\[\]\./', '', $apiFieldPath);
                // Keep removing until no more array prefixes
                while (preg_match('/^\w+\[\]\./', $cleanApiField)) {
                    $cleanApiField = preg_replace('/^\w+\[\]\./', '', $cleanApiField);
                }
            }

            // Forward mapping: API field → target field
            $this->mappings[$section][$targetField] = [
                'api_field' => $cleanApiField,
                'transform_type' => $transformType,
                'transform_config' => is_array($transformConfig) ? $transformConfig : [],
                'value_map' => is_array($transformConfig) ? ($transformConfig['valueMap'] ?? null) : null,
                'string_transform' => is_array($transformConfig) ? ($transformConfig['string_transform'] ?? null) : null,
            ];

            // Reverse mapping: target field → API field (for search params)
            $this->reverseMappings[$section][$targetField] = $cleanApiField;
        }
    }

    /**
     * Transform API response to unified format
     * 
     * @param array $apiData Raw data from wholesaler API
     * @param string $section Section to transform (tour, period, itinerary)
     * @param bool $applyLookup Whether to apply lookup transforms (for display)
     * @return array Transformed data with unified field names
     */
    public function transformToUnified(array $apiData, string $section = 'tour', bool $applyLookup = true): array
    {
        $result = [];
        $sectionMappings = $this->mappings[$section] ?? [];

        foreach ($sectionMappings as $targetField => $mapping) {
            $apiField = $mapping['api_field'];
            $value = $this->getNestedValue($apiData, $apiField);

            // Apply value map if exists
            if ($value !== null && !empty($mapping['value_map'])) {
                $value = $this->applyValueMap($value, $mapping['value_map']);
            }

            // Apply string transform if exists
            if ($value !== null && !empty($mapping['string_transform'])) {
                $value = $this->applyStringTransform($value, $mapping['string_transform']);
            }
            
            // Apply lookup transform if enabled (converts code to display name)
            if ($value !== null && $applyLookup && $mapping['transform_type'] === 'lookup') {
                $displayName = $this->getDisplayName($value, $targetField, $mapping['transform_config']);
                if ($displayName !== null && $displayName !== $value) {
                    // Store both: original value and display name
                    $result[$targetField] = $value; // Keep original (e.g., "CN")
                    $result[$targetField . '_name'] = $displayName; // Add display name (e.g., "จีน")
                    continue;
                }
            }

            if ($value !== null) {
                $result[$targetField] = $value;
            }
        }

        // Keep original data for reference
        $result['_raw'] = $apiData;

        return $result;
    }

    /**
     * Transform multiple items
     */
    public function transformManyToUnified(array $items, string $section = 'tour'): array
    {
        return array_map(fn($item) => $this->transformToUnified($item, $section), $items);
    }

    /**
     * Reverse transform: Convert unified search params to API query params
     * 
     * @param array $searchParams Unified search parameters
     * @param string $section Section (tour, period)
     * @return array API query parameters
     */
    public function reverseTransformSearchParams(array $searchParams, string $section = 'tour'): array
    {
        $apiParams = [];
        $sectionReverse = $this->reverseMappings[$section] ?? [];

        foreach ($searchParams as $unifiedField => $value) {
            // Check if we have a mapping for this field
            if (isset($sectionReverse[$unifiedField])) {
                $apiField = $sectionReverse[$unifiedField];
                $apiParams[$apiField] = $value;
            } else {
                // No mapping, pass through as-is (might be a common field)
                $apiParams[$unifiedField] = $value;
            }
        }

        return $apiParams;
    }

    /**
     * Get the API field name for a unified field
     */
    public function getApiFieldName(string $unifiedField, string $section = 'tour'): ?string
    {
        return $this->reverseMappings[$section][$unifiedField] ?? null;
    }

    /**
     * Get all searchable fields with their API field names
     */
    public function getSearchableFields(string $section = 'tour'): array
    {
        $fields = [];
        $sectionMappings = $this->mappings[$section] ?? [];

        foreach ($sectionMappings as $targetField => $mapping) {
            $fields[$targetField] = [
                'unified_name' => $targetField,
                'api_name' => $mapping['api_field'],
                'has_value_map' => !empty($mapping['value_map']),
            ];
        }

        return $fields;
    }

    /**
     * Get nested value from array using dot notation
     * Supports array notation like "countries[].code" to get first item's code
     * Supports fallback paths with | separator like "countries[].code|countries[].name"
     */
    protected function getNestedValue(array $data, string $path): mixed
    {
        // Handle fallback paths with | separator
        if (str_contains($path, '|')) {
            $paths = explode('|', $path);
            foreach ($paths as $singlePath) {
                $value = $this->getNestedValue($data, trim($singlePath));
                // Return first non-empty value
                if ($value !== null && $value !== '') {
                    return $value;
                }
            }
            return null;
        }
        
        // Handle array notation like "countries[].code"
        if (preg_match('/^(\w+)\[\]\.(.+)$/', $path, $matches)) {
            $arrayKey = $matches[1]; // e.g., "countries"
            $fieldPath = $matches[2]; // e.g., "code"
            
            if (!isset($data[$arrayKey]) || !is_array($data[$arrayKey]) || empty($data[$arrayKey])) {
                return null;
            }
            
            // Get first item from array
            $firstItem = $data[$arrayKey][0] ?? null;
            if (!$firstItem) {
                return null;
            }
            
            // Get nested field from first item
            return $this->getNestedValue($firstItem, $fieldPath);
        }
        
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Apply value mapping (e.g., status codes → labels)
     */
    protected function applyValueMap(mixed $value, array $valueMap): mixed
    {
        foreach ($valueMap as $item) {
            if (($item['from'] ?? null) === $value) {
                return $item['to'] ?? $value;
            }
        }
        return $value;
    }

    /**
     * Apply string transform (split, join, template)
     */
    protected function applyStringTransform(mixed $value, array $transform): mixed
    {
        $type = $transform['type'] ?? 'none';

        switch ($type) {
            case 'split':
                $delimiter = $transform['delimiter'] ?? ',';
                return is_string($value) ? explode($delimiter, $value) : $value;

            case 'join':
                $delimiter = $transform['delimiter'] ?? ', ';
                return is_array($value) ? implode($delimiter, $value) : $value;

            case 'template':
                // Template with placeholders like "Day {day}: {title}"
                $template = $transform['template'] ?? '{value}';
                if (is_array($value)) {
                    return str_replace(
                        array_map(fn($k) => "{{$k}}", array_keys($value)),
                        array_values($value),
                        $template
                    );
                }
                return str_replace('{value}', $value, $template);

            default:
                return $value;
        }
    }

    /**
     * Get display name for lookup fields (for realtime search display)
     * Converts code/id to human-readable name from database
     */
    protected function getDisplayName(mixed $value, string $targetField, array $config): ?string
    {
        if (empty($value) || !is_string($value)) {
            return null;
        }
        
        // Determine which table to lookup based on target field name
        if (str_contains($targetField, 'country')) {
            return $this->lookupCountryName($value, $config['lookup_by'] ?? null);
        }
        
        if (str_contains($targetField, 'transport') || str_contains($targetField, 'airline')) {
            return $this->lookupTransportName($value);
        }
        
        if (str_contains($targetField, 'city')) {
            return $this->lookupCityName($value, $config['lookup_by'] ?? null);
        }
        
        return null;
    }

    /**
     * Lookup country name from countries table
     */
    protected function lookupCountryName(string $value, ?string $lookupBy = null): ?string
    {
        $value = trim($value);
        
        // Try to find by iso2, iso3, name_en, or name_th
        $country = \App\Models\Country::where(function ($q) use ($value) {
            $q->where('iso2', $value)
              ->orWhere('iso3', $value)
              ->orWhereRaw('UPPER(name_en) = ?', [strtoupper($value)])
              ->orWhereRaw('UPPER(name_th) = ?', [strtoupper($value)]);
        })->first();
        
        if ($country) {
            // Return Thai name preferably, fallback to English
            return $country->name_th ?: $country->name_en;
        }
        
        // If not found in DB, return original value
        return $value;
    }
    
    /**
     * Lookup transport/airline name from transports table
     */
    protected function lookupTransportName(string $value): ?string
    {
        // Check if transport table exists and lookup
        if (class_exists('\App\Models\Transport')) {
            // Try exact match first
            $transport = \App\Models\Transport::where('code', $value)
                ->orWhere('name', $value)
                ->first();
            
            if (!$transport) {
                // Try extracting code from parentheses like "CHINA SOUTHERN AIRLINE (CZ)"
                if (preg_match('/\(([A-Z0-9]{2,3})\)/', $value, $matches)) {
                    $code = $matches[1];
                    $transport = \App\Models\Transport::where('code', $code)
                        ->orWhere('code1', $code)
                        ->first();
                }
            }
            
            if (!$transport) {
                // Try LIKE match on name
                $transport = \App\Models\Transport::where('name', 'LIKE', "%{$value}%")
                    ->first();
            }
            
            if ($transport) {
                return $transport->name;
            }
        }
        
        // Return original value if not found
        return $value;
    }
    
    /**
     * Lookup city name from cities table
     */
    protected function lookupCityName(string $value, ?string $lookupBy = null): ?string
    {
        $city = \App\Models\City::where(function ($q) use ($value) {
            $q->where('code', $value)
              ->orWhereRaw('UPPER(name_en) = ?', [strtoupper($value)])
              ->orWhereRaw('UPPER(name_th) = ?', [strtoupper($value)]);
        })->first();
        
        if ($city) {
            return $city->name_th ?: $city->name_en;
        }
        
        return $value;
    }

    /**
     * Apply lookup transform - find ID from related table with fuzzy matching
     * Also returns display name for realtime search
     */
    protected function applyLookupTransform(mixed $value, string $targetField, array $config): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $lookupTable = $config['lookup_table'] ?? null;
        $lookupBy = $config['lookup_by'] ?? 'id';

        // Auto-infer lookup_table from target field if not specified
        if (!$lookupTable) {
            if (str_ends_with($targetField, '_id')) {
                $baseName = substr($targetField, 0, -3);
                if (str_contains($baseName, '_')) {
                    $parts = explode('_', $baseName);
                    $baseName = end($parts);
                }
                $lookupTable = str($baseName)->plural()->toString();
            }
        }

        if (!$lookupTable) {
            return $value;
        }

        // Build model class
        $modelClass = 'App\\Models\\' . str($lookupTable)->singular()->studly()->toString();
        if (!class_exists($modelClass)) {
            return $value;
        }

        // Try exact match first
        $record = $modelClass::where($lookupBy, $value)->first();

        // If not found, try fuzzy matching for transport and country
        if (!$record && in_array($lookupTable, ['transports', 'countries'])) {
            $searchValue = trim((string) $value);

            // For transports - try LIKE match on name field
            if ($lookupTable === 'transports') {
                $record = $modelClass::where('name', 'LIKE', '%' . $searchValue . '%')->first();

                // Try extracting code from parentheses like "CHINA SOUTHERN AIRLINE (CZ)"
                if (!$record && preg_match('/\(([A-Z0-9]{2,3})\)/', $searchValue, $matches)) {
                    $code = $matches[1];
                    $record = $modelClass::where('code', $code)
                        ->orWhere('code1', $code)
                        ->first();
                }
            }

            // For countries - try ISO codes and name fields
            if (!$record && $lookupTable === 'countries') {
                $record = $modelClass::where('iso2', strtoupper($searchValue))
                    ->orWhere('iso3', strtoupper($searchValue))
                    ->orWhere('name_en', 'LIKE', '%' . $searchValue . '%')
                    ->orWhere('name_th', 'LIKE', '%' . $searchValue . '%')
                    ->first();
            }
        }

        return $record?->id;
    }

    /**
     * Check if API supports a specific search parameter
     */
    public function supportsSearchParam(string $unifiedField, string $section = 'tour'): bool
    {
        return isset($this->reverseMappings[$section][$unifiedField]);
    }

    /**
     * Get mapping summary for debugging
     */
    public function getMappingSummary(): array
    {
        return [
            'forward' => $this->mappings,
            'reverse' => $this->reverseMappings,
            'wholesaler_id' => $this->config?->wholesaler_id,
        ];
    }
}
