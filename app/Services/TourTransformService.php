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

            // For departure section, strip array prefix like "Periods[].PeriodStartDate" -> "PeriodStartDate"
            // This is because when transforming individual period items, they don't have the array prefix
            $cleanApiField = $apiFieldPath;
            if ($section === 'departure' && preg_match('/^(\w+)\[\]\.(.+)$/', $apiFieldPath, $matches)) {
                $cleanApiField = $matches[2]; // Get the field name after []
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
     * @return array Transformed data with unified field names
     */
    public function transformToUnified(array $apiData, string $section = 'tour'): array
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
     */
    protected function getNestedValue(array $data, string $path): mixed
    {
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
