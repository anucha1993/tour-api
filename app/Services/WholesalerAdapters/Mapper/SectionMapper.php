<?php

namespace App\Services\WholesalerAdapters\Mapper;

use App\Models\SectionDefinition;
use App\Models\WholesalerFieldMapping;
use Illuminate\Support\Facades\Log;

/**
 * Section-based Mapping Engine
 * 
 * Maps raw data from Wholesaler API to our normalized section-based structure.
 * Uses TypeValidator for type conversion and LookupResolver for ID resolution.
 */
class SectionMapper
{
    protected TypeValidator $typeValidator;
    protected LookupResolver $lookupResolver;
    protected array $sectionDefinitions = [];
    protected array $fieldMappings = [];
    protected array $errors = [];

    public function __construct(
        ?TypeValidator $typeValidator = null,
        ?LookupResolver $lookupResolver = null
    ) {
        $this->typeValidator = $typeValidator ?? new TypeValidator();
        $this->lookupResolver = $lookupResolver ?? new LookupResolver();
    }

    /**
     * Load mappings for a wholesaler
     */
    public function loadMappings(int $wholesalerId): self
    {
        // Load section definitions
        $this->sectionDefinitions = SectionDefinition::all()
            ->groupBy('section_name')
            ->map(fn($fields) => $fields->keyBy('field_name'))
            ->toArray();

        // Load wholesaler-specific mappings
        $this->fieldMappings = WholesalerFieldMapping::where('wholesaler_id', $wholesalerId)
            ->where('is_active', true)
            ->get()
            ->groupBy('section_name')
            ->map(fn($mappings) => $mappings->keyBy('our_field'))
            ->toArray();

        // Preload lookups for performance
        $this->lookupResolver->preloadCountries();

        return $this;
    }

    /**
     * Map raw tour data to our structure
     */
    public function mapTour(array $rawData): MappingResult
    {
        $this->errors = [];
        $result = [];

        // Map each section
        foreach (['tour', 'period', 'pricing', 'content', 'media', 'seo'] as $sectionName) {
            $sectionResult = $this->mapSection($sectionName, $rawData);
            $result[$sectionName] = $sectionResult;
        }

        return new MappingResult(
            success: empty($this->errors),
            data: $result,
            errors: $this->errors,
        );
    }

    /**
     * Map a single section
     */
    public function mapSection(string $sectionName, array $rawData): array
    {
        $mappedData = [];
        $definitions = $this->sectionDefinitions[$sectionName] ?? [];
        $mappings = $this->fieldMappings[$sectionName] ?? [];

        foreach ($definitions as $fieldName => $definition) {
            // Get mapping for this field (or null if not mapped)
            $mapping = $mappings[$fieldName] ?? null;

            // Extract value from raw data
            $rawValue = $this->extractValue($rawData, $mapping, $definition);

            // Apply transformation
            $transformedValue = $this->transform($rawValue, $mapping);

            // Validate and convert type
            $definitionModel = new SectionDefinition((array)$definition);
            $validationResult = $this->typeValidator->validate($transformedValue, $definitionModel);

            if (!$validationResult->success) {
                $this->errors[] = [
                    'section' => $sectionName,
                    'field' => $fieldName,
                    'error' => $validationResult->error,
                    'received_value' => $validationResult->originalValue,
                    'expected_type' => $validationResult->expectedType,
                ];

                // Use default value on error
                $mappedData[$fieldName] = $definition['default_value'] ?? null;
                continue;
            }

            // Resolve lookups if needed
            if ($definitionModel->hasLookup()) {
                $lookupResult = $this->lookupResolver->resolve($validationResult->value, $definitionModel);

                if (!$lookupResult->found && !empty($lookupResult->error)) {
                    $this->errors[] = [
                        'section' => $sectionName,
                        'field' => $fieldName,
                        'error' => $lookupResult->error,
                        'received_value' => $lookupResult->originalValue,
                        'error_type' => 'lookup',
                    ];
                }

                $mappedData[$fieldName] = $lookupResult->getValue();
            } else {
                $mappedData[$fieldName] = $validationResult->value;
            }
        }

        return $mappedData;
    }

    /**
     * Extract value from raw data using mapping
     */
    protected function extractValue(array $rawData, ?array $mapping, array $definition): mixed
    {
        // No mapping defined - try direct field name match
        if (!$mapping) {
            $fieldName = $definition['field_name'];
            return $rawData[$fieldName] ?? $definition['default_value'] ?? null;
        }

        // Use mapping to extract
        $mappingModel = new WholesalerFieldMapping($mapping);
        $value = $mappingModel->extractValue($rawData);

        // Fall back to default
        if ($value === null) {
            return $mapping['default_value'] ?? $definition['default_value'] ?? null;
        }

        return $value;
    }

    /**
     * Apply transformation to value
     */
    protected function transform(mixed $value, ?array $mapping): mixed
    {
        if (!$mapping || !$value) {
            return $value;
        }

        $transformType = $mapping['transform_type'] ?? 'direct';
        $config = $mapping['transform_config'] ?? [];

        return match ($transformType) {
            'direct' => $value,
            'value_map' => $this->transformValueMap($value, $config),
            'formula' => $this->transformFormula($value, $config),
            'split' => $this->transformSplit($value, $config),
            'concat' => $this->transformConcat($value, $config),
            'lookup' => $value, // Handled by LookupResolver
            'custom' => $this->transformCustom($value, $config),
            default => $value,
        };
    }

    /**
     * Transform using value map
     * Config: {"map": {"old_value": "new_value"}}
     */
    protected function transformValueMap(mixed $value, array $config): mixed
    {
        $map = $config['map'] ?? [];
        
        if (is_array($value)) {
            return array_map(fn($v) => $map[$v] ?? $v, $value);
        }

        return $map[$value] ?? $value;
    }

    /**
     * Transform using formula
     * Config: {"formula": "value * 1.07"} or {"add": 100}
     */
    protected function transformFormula(mixed $value, array $config): mixed
    {
        if (!is_numeric($value)) {
            return $value;
        }

        if (isset($config['add'])) {
            return $value + $config['add'];
        }

        if (isset($config['multiply'])) {
            return $value * $config['multiply'];
        }

        if (isset($config['subtract'])) {
            return $value - $config['subtract'];
        }

        if (isset($config['divide']) && $config['divide'] != 0) {
            return $value / $config['divide'];
        }

        return $value;
    }

    /**
     * Transform by splitting string
     * Config: {"delimiter": ",", "index": 0}
     */
    protected function transformSplit(mixed $value, array $config): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $delimiter = $config['delimiter'] ?? ',';
        $parts = array_map('trim', explode($delimiter, $value));

        if (isset($config['index'])) {
            return $parts[$config['index']] ?? null;
        }

        return $parts;
    }

    /**
     * Transform by concatenating
     * Config: {"fields": ["field1", "field2"], "separator": " "}
     */
    protected function transformConcat(mixed $value, array $config): mixed
    {
        // This is typically handled differently - concat multiple source fields
        // For now, just return value
        return $value;
    }

    /**
     * Custom transformation using callback
     * Config: {"class": "App\\Transformers\\MyTransformer", "method": "transform"}
     */
    protected function transformCustom(mixed $value, array $config): mixed
    {
        if (!isset($config['class']) || !isset($config['method'])) {
            return $value;
        }

        try {
            $class = app($config['class']);
            $method = $config['method'];
            
            if (method_exists($class, $method)) {
                return $class->$method($value, $config);
            }
        } catch (\Exception $e) {
            Log::warning("Custom transform failed: " . $e->getMessage());
        }

        return $value;
    }

    /**
     * Get accumulated errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Clear errors
     */
    public function clearErrors(): void
    {
        $this->errors = [];
    }

    /**
     * Get preview of mapping (for UI testing)
     */
    public function preview(array $sampleData, int $wholesalerId): array
    {
        $this->loadMappings($wholesalerId);
        
        $result = $this->mapTour($sampleData);

        return [
            'input' => $sampleData,
            'output' => $result->data,
            'success' => $result->success,
            'errors' => $result->errors,
            'field_count' => $this->countMappedFields($result->data),
        ];
    }

    /**
     * Count non-null mapped fields
     */
    protected function countMappedFields(array $data): int
    {
        $count = 0;
        
        foreach ($data as $section => $fields) {
            foreach ($fields as $value) {
                if ($value !== null && $value !== '') {
                    $count++;
                }
            }
        }

        return $count;
    }
}

/**
 * Result of mapping operation
 */
class MappingResult
{
    public function __construct(
        public bool $success,
        public array $data = [],
        public array $errors = [],
    ) {}

    /**
     * Get section data
     */
    public function getSection(string $name): array
    {
        return $this->data[$name] ?? [];
    }

    /**
     * Get all data as flat array (for Tour model)
     */
    public function toTourData(): array
    {
        $flat = [];

        // Tour section maps directly
        foreach ($this->data['tour'] ?? [] as $key => $value) {
            $flat[$key] = $value;
        }

        // Pricing section
        foreach ($this->data['pricing'] ?? [] as $key => $value) {
            $flat[$key] = $value;
        }

        // Content section
        foreach ($this->data['content'] ?? [] as $key => $value) {
            $flat[$key] = $value;
        }

        // Media section
        if (!empty($this->data['media']['cover_image'])) {
            $flat['cover_image_url'] = $this->data['media']['cover_image'];
        }
        if (!empty($this->data['media']['cover_alt'])) {
            $flat['cover_image_alt'] = $this->data['media']['cover_alt'];
        }
        if (!empty($this->data['media']['pdf_url'])) {
            $flat['pdf_url'] = $this->data['media']['pdf_url'];
        }

        // SEO section
        if (!empty($this->data['seo']['slug'])) {
            $flat['slug'] = $this->data['seo']['slug'];
        }

        return $flat;
    }

    /**
     * Get periods data
     */
    public function toPeriodData(): array
    {
        return $this->data['period'] ?? [];
    }
}
