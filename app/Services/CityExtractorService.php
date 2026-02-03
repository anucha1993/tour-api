<?php

namespace App\Services;

use App\Models\City;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CityExtractorService
{
    /**
     * Cache key for cities
     */
    private const CACHE_KEY = 'city_extractor_cities';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Minimum city name length to consider
     */
    private const MIN_NAME_LENGTH = 2;

    /**
     * Extract cities from tour name
     *
     * @param string $tourName
     * @param int|null $countryId Optional: limit search to specific country
     * @return Collection<City>
     */
    public static function extract(string $tourName, ?int $countryId = null): Collection
    {
        $cities = self::getCities($countryId);
        $foundCities = collect();
        $usedPositions = [];

        // Sort by name length descending (longest match first)
        $sortedCities = $cities->sortByDesc(function ($city) {
            return max(
                mb_strlen($city['name_th'] ?? ''),
                mb_strlen($city['name_en'] ?? '')
            );
        });

        foreach ($sortedCities as $city) {
            $matched = false;
            $matchPosition = null;

            // Try Thai name first
            if (!empty($city['name_th']) && mb_strlen($city['name_th']) >= self::MIN_NAME_LENGTH) {
                $pos = mb_stripos($tourName, $city['name_th']);
                if ($pos !== false && !self::isPositionUsed($usedPositions, $pos, mb_strlen($city['name_th']))) {
                    $matched = true;
                    $matchPosition = $pos;
                    $matchLength = mb_strlen($city['name_th']);
                }
            }

            // Try English name if Thai not matched
            if (!$matched && !empty($city['name_en']) && mb_strlen($city['name_en']) >= self::MIN_NAME_LENGTH) {
                $pos = mb_stripos($tourName, $city['name_en']);
                if ($pos !== false && !self::isPositionUsed($usedPositions, $pos, mb_strlen($city['name_en']))) {
                    $matched = true;
                    $matchPosition = $pos;
                    $matchLength = mb_strlen($city['name_en']);
                }
            }

            if ($matched) {
                $foundCities->push($city);
                // Mark positions as used to prevent overlapping matches
                $usedPositions[] = [
                    'start' => $matchPosition,
                    'end' => $matchPosition + $matchLength - 1
                ];
            }
        }

        // Return as City models
        return City::whereIn('id', $foundCities->pluck('id'))->get();
    }

    /**
     * Extract city IDs from tour name
     *
     * @param string $tourName
     * @param int|null $countryId
     * @return array<int>
     */
    public static function extractIds(string $tourName, ?int $countryId = null): array
    {
        return self::extract($tourName, $countryId)->pluck('id')->toArray();
    }

    /**
     * Check if position range is already used
     */
    private static function isPositionUsed(array $usedPositions, int $start, int $length): bool
    {
        $end = $start + $length - 1;

        foreach ($usedPositions as $used) {
            // Check for overlap
            if ($start <= $used['end'] && $end >= $used['start']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get cities from cache or database
     */
    private static function getCities(?int $countryId = null): Collection
    {
        $cacheKey = self::CACHE_KEY . ($countryId ? "_{$countryId}" : '_all');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($countryId) {
            $query = City::query()
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNotNull('name_th')
                        ->where('name_th', '!=', '')
                        ->orWhere(function ($q2) {
                            $q2->whereNotNull('name_en')
                                ->where('name_en', '!=', '');
                        });
                });

            if ($countryId) {
                $query->where('country_id', $countryId);
            }

            return $query->get(['id', 'name_th', 'name_en', 'country_id'])->map(function ($city) {
                return [
                    'id' => $city->id,
                    'name_th' => $city->name_th,
                    'name_en' => $city->name_en,
                    'country_id' => $city->country_id,
                ];
            });
        });
    }

    /**
     * Clear the cities cache
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY . '_all');
        
        // Clear country-specific caches
        $countryIds = City::distinct()->pluck('country_id');
        foreach ($countryIds as $countryId) {
            Cache::forget(self::CACHE_KEY . "_{$countryId}");
        }
    }

    /**
     * Test extraction with sample tour names
     */
    public static function test(): array
    {
        $samples = [
            'ไต้หวัน ไทเป ตั้นสุ่ย ชมซากุระ หมู่บ้านโบราณจิ่วเฟิ่น ซีเหมินติง 4วัน2คืน',
            'เอินซือ ล่องเรือเขาผิงซาน ชมความงามเมืองเชวียนเอิน จางเจียเจี้ย 6 วัน 5 คืน บิน FD',
            'แอฟริกาใต้ สัตว์ ป่า เขา เอาให้ครบ หุ หุ 8 วัน 5 คืน BY KQ',
            'จีน กวางเจา จูไห่ มาเก๊า เวเนเชี่ยน สวนสนุก CHIMELONG OCEAN KINGDOM',
            'TF-PEKVZ02 ปักกิ่ง กำแพงเมืองจีน (เจวียงกวน) พระราชวังต้องห้าม (Free day)',
            'ญี่ปุ่น โตเกียว ฟูจิ ชมซากุระ 5วัน3คืน',
            'เกาหลี โซล เกาะนามิ ซอรัคซาน 5วัน3คืน',
        ];

        $results = [];
        foreach ($samples as $sample) {
            $cities = self::extract($sample);
            $results[] = [
                'tour_name' => $sample,
                'cities_found' => $cities->map(fn($c) => [
                    'id' => $c->id,
                    'name_th' => $c->name_th,
                    'name_en' => $c->name_en,
                ])->toArray(),
                'count' => $cities->count(),
            ];
        }

        return $results;
    }
}
