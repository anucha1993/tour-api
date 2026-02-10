<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
    ];

    /**
     * Cache key prefix
     */
    const CACHE_PREFIX = 'system_setting:';
    const CACHE_TTL = 3600; // 1 hour

    /**
     * Get a setting value by key
     */
    public static function getValue(string $key, $default = null)
    {
        $cacheKey = self::CACHE_PREFIX . $key;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();
            
            if (!$setting) {
                return $default;
            }

            return self::castValue($setting->value, $setting->type);
        });
    }

    /**
     * Set a setting value
     */
    public static function setValue(string $key, $value, ?string $type = null): self
    {
        $setting = self::firstOrNew(['key' => $key]);
        
        if ($type) {
            $setting->type = $type;
        }

        // Convert value to string for storage
        if (is_array($value) || is_object($value)) {
            $setting->value = json_encode($value);
            if (!$type) {
                $setting->type = 'json';
            }
        } elseif (is_bool($value)) {
            $setting->value = $value ? 'true' : 'false';
            if (!$type) {
                $setting->type = 'boolean';
            }
        } else {
            $setting->value = (string) $value;
        }

        $setting->save();

        // Clear cache
        Cache::forget(self::CACHE_PREFIX . $key);

        return $setting;
    }

    /**
     * Get all settings by group
     */
    public static function getByGroup(string $group): array
    {
        $settings = self::where('group', $group)->get();
        
        $result = [];
        foreach ($settings as $setting) {
            // Remove group prefix from key for cleaner response
            $shortKey = str_replace($group . '.', '', $setting->key);
            $result[$shortKey] = self::castValue($setting->value, $setting->type);
        }

        return $result;
    }

    /**
     * Get all settings grouped
     */
    public static function getAllGrouped(): array
    {
        $settings = self::all();
        
        $result = [];
        foreach ($settings as $setting) {
            if (!isset($result[$setting->group])) {
                $result[$setting->group] = [];
            }
            
            // Remove group prefix from key
            $shortKey = str_replace($setting->group . '.', '', $setting->key);
            $result[$setting->group][$shortKey] = [
                'value' => self::castValue($setting->value, $setting->type),
                'type' => $setting->type,
                'description' => $setting->description,
            ];
        }

        return $result;
    }

    /**
     * Update multiple settings at once
     */
    public static function updateMany(array $settings): void
    {
        foreach ($settings as $key => $value) {
            self::setValue($key, $value);
        }
    }

    /**
     * Clear all settings cache
     */
    public static function clearCache(): void
    {
        $settings = self::all();
        foreach ($settings as $setting) {
            Cache::forget(self::CACHE_PREFIX . $setting->key);
        }
    }

    /**
     * Cast value based on type
     */
    protected static function castValue(?string $value, string $type)
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'float' => (float) $value,
            'json', 'array' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Convenience method: Get sync settings
     */
    public static function getSyncSettings(): array
    {
        return [
            'respect_manual_overrides' => self::getValue('sync.respect_manual_overrides', true),
            'always_sync_fields' => self::getValue('sync.always_sync_fields', []),
            'never_sync_fields' => self::getValue('sync.never_sync_fields', []),
            'skip_past_periods' => self::getValue('sync.skip_past_periods', true),
            'skip_disabled_tours' => self::getValue('sync.skip_disabled_tours', true),
        ];
    }

    /**
     * Convenience method: Get auto-close settings
     */
    public static function getAutoCloseSettings(): array
    {
        return [
            'enabled' => self::getValue('auto_close.enabled', false),
            'periods' => self::getValue('auto_close.periods', true),
            'tours' => self::getValue('auto_close.tours', true),
            'threshold_days' => self::getValue('auto_close.threshold_days', 0),
            'run_time' => self::getValue('auto_close.run_time', '01:00'),
        ];
    }
}
