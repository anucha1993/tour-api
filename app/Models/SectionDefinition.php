<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SectionDefinition extends Model
{
    protected $fillable = [
        'section_name',
        'field_name',
        'data_type',
        'enum_values',
        'is_required',
        'default_value',
        'validation_rules',
        'lookup_table',
        'lookup_match_fields',
        'lookup_return_field',
        'lookup_create_if_not_found',
        'description',
        'sort_order',
        'is_system',
    ];

    protected $casts = [
        'enum_values' => 'array',
        'lookup_match_fields' => 'array',
        'is_required' => 'boolean',
        'lookup_create_if_not_found' => 'boolean',
        'is_system' => 'boolean',
    ];

    /**
     * Get all fields for a section
     */
    public static function getSection(string $sectionName): array
    {
        return static::where('section_name', $sectionName)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('field_name')
            ->toArray();
    }

    /**
     * Get all sections with their fields
     */
    public static function getAllSections(): array
    {
        return static::orderBy('section_name')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('section_name')
            ->map(fn($fields) => $fields->keyBy('field_name'))
            ->toArray();
    }

    /**
     * Get required fields for a section
     */
    public static function getRequiredFields(string $sectionName): array
    {
        return static::where('section_name', $sectionName)
            ->where('is_required', true)
            ->pluck('field_name')
            ->toArray();
    }

    /**
     * Check if field has lookup
     */
    public function hasLookup(): bool
    {
        return !empty($this->lookup_table);
    }

    /**
     * Check if field is array type
     */
    public function isArrayType(): bool
    {
        return str_starts_with($this->data_type, 'ARRAY_');
    }

    /**
     * Get the base type for array types
     */
    public function getBaseType(): string
    {
        if ($this->isArrayType()) {
            return str_replace('ARRAY_', '', $this->data_type);
        }
        return $this->data_type;
    }
}
