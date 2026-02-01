<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'description',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    /**
     * Get a setting value by key
     * Supports dot notation: 'tour_aggregations.price_adult'
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        // Check for dot notation (nested key)
        if (str_contains($key, '.')) {
            [$settingKey, $nestedKey] = explode('.', $key, 2);
            $setting = static::where('key', $settingKey)->first();
            
            if (!$setting) {
                return $default;
            }
            
            $value = static::castValue($setting->value, $setting->type);
            
            if (is_array($value)) {
                return data_get($value, $nestedKey, $default);
            }
            
            return $default;
        }
        
        $setting = static::where('key', $key)->first();
        
        if (!$setting) {
            return $default;
        }
        
        return static::castValue($setting->value, $setting->type);
    }

    /**
     * Set a setting value
     */
    public static function set(string $key, mixed $value, ?string $group = null, ?string $type = null): void
    {
        $type = $type ?? static::detectType($value);
        $storedValue = static::prepareValue($value, $type);
        
        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $storedValue,
                'type' => $type,
                'group' => $group ?? 'general',
            ]
        );
        
        // Clear cache
        Cache::forget("setting:{$key}");
    }

    /**
     * Get all settings by group
     */
    public static function getByGroup(string $group): array
    {
        return static::where('group', $group)
            ->get()
            ->mapWithKeys(function ($setting) {
                return [$setting->key => static::castValue($setting->value, $setting->type)];
            })
            ->toArray();
    }

    /**
     * Get all public settings (for frontend)
     */
    public static function getPublic(): array
    {
        return static::where('is_public', true)
            ->get()
            ->mapWithKeys(function ($setting) {
                return [$setting->key => static::castValue($setting->value, $setting->type)];
            })
            ->toArray();
    }

    /**
     * Cast value to correct type
     */
    protected static function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'integer', 'int' => (int) $value,
            'float', 'double' => (float) $value,
            'boolean', 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json', 'array' => is_array($value) ? $value : json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Prepare value for storage
     */
    protected static function prepareValue(mixed $value, string $type): string
    {
        if (in_array($type, ['json', 'array'])) {
            return json_encode($value);
        }
        
        if ($type === 'boolean' || $type === 'bool') {
            return $value ? '1' : '0';
        }
        
        return (string) $value;
    }

    /**
     * Detect type from value
     */
    protected static function detectType(mixed $value): string
    {
        if (is_array($value)) {
            return 'json';
        }
        
        if (is_bool($value)) {
            return 'boolean';
        }
        
        if (is_int($value)) {
            return 'integer';
        }
        
        if (is_float($value)) {
            return 'float';
        }
        
        return 'string';
    }
}
