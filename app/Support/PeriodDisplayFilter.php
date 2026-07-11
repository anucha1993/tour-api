<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Global helper for filtering tour periods before returning them
 * to public endpoints. Reads two settings from the Setting KV store:
 *   - period_display.hide_past : bool (default: true)
 *   - period_display.hide_full : bool (default: false)
 *
 * Usage:
 *   $periods = PeriodDisplayFilter::apply($tour->periods);
 */
class PeriodDisplayFilter
{
    protected const CACHE_KEY = 'period_display_settings';
    protected const CACHE_TTL = 300; // 5 minutes

    /**
     * Get current settings (cached).
     *
     * @return array{hide_past: bool, hide_full: bool}
     */
    public static function settings(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $raw = Setting::get('period_display', []);
            if (!is_array($raw)) {
                $raw = [];
            }
            return [
                'hide_past' => array_key_exists('hide_past', $raw)
                    ? (bool) $raw['hide_past']
                    : true, // default: hide past periods
                'hide_full' => array_key_exists('hide_full', $raw)
                    ? (bool) $raw['hide_full']
                    : false, // default: keep showing full periods
            ];
        });
    }

    /**
     * Save settings and invalidate cache.
     */
    public static function save(bool $hidePast, bool $hideFull): void
    {
        Setting::set('period_display', [
            'hide_past' => $hidePast,
            'hide_full' => $hideFull,
        ], 'display', 'json');
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Clear the cache manually (call after Setting::set from other places).
     */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Apply the filter to any iterable of period rows/models.
     * Accepts a Collection, array, or Eloquent relation result.
     */
    public static function apply(mixed $periods): Collection
    {
        $collection = $periods instanceof Collection
            ? $periods
            : collect($periods ?? []);

        $settings = self::settings();
        $today = now()->startOfDay();

        return $collection->filter(function ($period) use ($settings, $today) {
            if ($settings['hide_past']) {
                $startDate = self::extractStartDate($period);
                if ($startDate !== null && $startDate->lt($today)) {
                    return false;
                }
            }
            if ($settings['hide_full']) {
                $available = self::extractAvailable($period);
                if ($available !== null && $available <= 0) {
                    return false;
                }
            }
            return true;
        })->values();
    }

    /**
     * Extract start_date from an object/array.
     */
    protected static function extractStartDate(mixed $period): ?\Illuminate\Support\Carbon
    {
        $raw = null;
        if (is_object($period)) {
            $raw = $period->start_date ?? null;
        } elseif (is_array($period)) {
            $raw = $period['start_date'] ?? null;
        }
        if ($raw === null) {
            return null;
        }
        try {
            if ($raw instanceof \Illuminate\Support\Carbon) {
                return $raw->copy()->startOfDay();
            }
            return \Illuminate\Support\Carbon::parse($raw)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Extract available seats from an object/array.
     */
    protected static function extractAvailable(mixed $period): ?int
    {
        if (is_object($period)) {
            if (property_exists($period, 'available') || isset($period->available)) {
                return (int) $period->available;
            }
            if (isset($period->available_seats)) {
                return (int) $period->available_seats;
            }
        } elseif (is_array($period)) {
            if (array_key_exists('available', $period)) {
                return (int) $period['available'];
            }
            if (array_key_exists('available_seats', $period)) {
                return (int) $period['available_seats'];
            }
        }
        return null;
    }
}
