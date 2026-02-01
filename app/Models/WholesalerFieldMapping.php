<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WholesalerFieldMapping extends Model
{
    protected $fillable = [
        'wholesaler_id',
        'section_name',
        'our_field',
        'their_field',
        'their_field_path',
        'transform_type',
        'transform_config',
        'default_value',
        'is_required_override',
        'notes',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'transform_config' => 'array',
        'is_active' => 'boolean',
        'is_required_override' => 'boolean',
    ];

    /**
     * Get the wholesaler that owns this mapping
     */
    public function wholesaler(): BelongsTo
    {
        return $this->belongsTo(Wholesaler::class);
    }

    /**
     * Get the section definition for this field
     */
    public function sectionDefinition(): ?SectionDefinition
    {
        return SectionDefinition::where('section_name', $this->section_name)
            ->where('field_name', $this->our_field)
            ->first();
    }

    /**
     * Get value from source data using their_field or their_field_path
     */
    public function extractValue(array $sourceData): mixed
    {
        // Try direct field first
        if ($this->their_field && isset($sourceData[$this->their_field])) {
            return $sourceData[$this->their_field];
        }

        // Try JSON path
        if ($this->their_field_path) {
            return $this->getValueByPath($sourceData, $this->their_field_path);
        }

        return null;
    }

    /**
     * Get value by dot notation path
     */
    protected function getValueByPath(array $data, string $path): mixed
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
     * Get all mappings for a wholesaler by section
     */
    public static function getMappingsForWholesaler(int $wholesalerId): array
    {
        return static::where('wholesaler_id', $wholesalerId)
            ->where('is_active', true)
            ->orderBy('section_name')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('section_name')
            ->map(fn($mappings) => $mappings->keyBy('our_field'))
            ->toArray();
    }

    /**
     * Check if this mapping is required
     */
    public function isRequired(): bool
    {
        // Override takes precedence
        if ($this->is_required_override !== null) {
            return $this->is_required_override;
        }

        // Fall back to section definition
        $definition = $this->sectionDefinition();
        return $definition ? $definition->is_required : false;
    }
}
