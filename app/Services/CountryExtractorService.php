<?php

namespace App\Services;

use App\Models\Country;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CountryExtractorService
{
    private const CACHE_KEY = 'country_extractor_countries';
    private const CACHE_TTL = 3600; // 1 hour
    private const MIN_NAME_LENGTH = 2;

    /**
     * Common aliases/short names used in Thai tour titles
     * Maps alias → countries table name_th or name_en to match against
     */
    private const ALIASES = [
        // Thai aliases → country id
        'เยอรมัน' => 'เยอรมนี',
        'เยอรมันนี' => 'เยอรมนี',
        'เช็ค' => 'สาธารณรัฐเช็ก',
        'เช็ก' => 'สาธารณรัฐเช็ก',
        'เกาหลี' => 'เกาหลีใต้',
        'อังกฤษ' => 'สหราชอาณาจักร - อังกฤษ',
        'อเมริกา' => 'สหรัฐอเมริกา',
        'รัสเซีย' => 'รัสเซีย',
        'ฮอลแลนด์' => 'เนเธอร์แลนด์',
        'ดัตช์' => 'เนเธอร์แลนด์',
        'นิวซี' => 'นิวซีแลนด์',
        'ออสซี่' => 'ออสเตรเลีย',
        'สวิส' => 'สวิตเซอร์แลนด์',
        'พม่า' => 'เมียนมาร์',
        'เกาะบาหลี' => 'อินโดนีเซีย',
        'บาหลี' => 'อินโดนีเซีย',
        'ปูซาน' => 'เกาหลีใต้',
        'ไอซ์แลนด์' => 'ไอซ์แลนด์',
        'สแกนดิเนเวีย' => null, // region, not country — skip
        'ยุโรป' => null, // region — skip
        'แอฟริกา' => null, // region — skip, but "แอฟริกาใต้" is a country
        // English aliases
        'UK' => 'สหราชอาณาจักร - อังกฤษ',
        'USA' => 'สหรัฐอเมริกา',
        'Korea' => 'เกาหลีใต้',
        'Czech' => 'สาธารณรัฐเช็ก',
        'Holland' => 'เนเธอร์แลนด์',
        'Swiss' => 'สวิตเซอร์แลนด์',
    ];

    /**
     * Extract countries from tour name
     *
     * @param string $tourName
     * @return Collection<Country>
     */
    public static function extract(string $tourName): Collection
    {
        $countries = self::getCountries();
        $foundCountryIds = [];
        $usedPositions = [];

        // First: try aliases (longest alias first to avoid partial matches)
        $aliases = self::ALIASES;
        uksort($aliases, fn($a, $b) => mb_strlen($b) - mb_strlen($a));

        foreach ($aliases as $alias => $targetName) {
            if (mb_strlen($alias) < self::MIN_NAME_LENGTH) continue;
            if ($targetName === null) continue; // region, skip

            $pos = mb_stripos($tourName, $alias);
            if ($pos !== false && !self::isPositionUsed($usedPositions, $pos, mb_strlen($alias))) {
                // Find country by target name_th
                $country = $countries->first(fn($c) => $c['name_th'] === $targetName);
                if ($country && !in_array($country['id'], $foundCountryIds)) {
                    $foundCountryIds[] = $country['id'];
                    $usedPositions[] = [
                        'start' => $pos,
                        'end' => $pos + mb_strlen($alias) - 1,
                    ];
                }
            }
        }

        // Second: try direct name matching (longest name first)
        $sortedCountries = $countries->sortByDesc(function ($country) {
            return max(
                mb_strlen($country['name_th'] ?? ''),
                mb_strlen($country['name_en'] ?? '')
            );
        });

        foreach ($sortedCountries as $country) {
            if (in_array($country['id'], $foundCountryIds)) continue;

            $matched = false;
            $matchPosition = null;
            $matchLength = 0;

            // Try Thai name
            if (!empty($country['name_th']) && mb_strlen($country['name_th']) >= self::MIN_NAME_LENGTH) {
                $pos = mb_stripos($tourName, $country['name_th']);
                if ($pos !== false && !self::isPositionUsed($usedPositions, $pos, mb_strlen($country['name_th']))) {
                    $matched = true;
                    $matchPosition = $pos;
                    $matchLength = mb_strlen($country['name_th']);
                }
            }

            // Try English name (min 3 chars to avoid false matches)
            if (!$matched && !empty($country['name_en']) && strlen($country['name_en']) >= 3) {
                $pos = mb_stripos($tourName, $country['name_en']);
                if ($pos !== false && !self::isPositionUsed($usedPositions, $pos, mb_strlen($country['name_en']))) {
                    $matched = true;
                    $matchPosition = $pos;
                    $matchLength = mb_strlen($country['name_en']);
                }
            }

            if ($matched) {
                $foundCountryIds[] = $country['id'];
                $usedPositions[] = [
                    'start' => $matchPosition,
                    'end' => $matchPosition + $matchLength - 1,
                ];
            }
        }

        if (empty($foundCountryIds)) {
            return collect();
        }

        return Country::whereIn('id', $foundCountryIds)->get();
    }

    /**
     * Extract country IDs from tour name
     */
    public static function extractIds(string $tourName): array
    {
        return self::extract($tourName)->pluck('id')->toArray();
    }

    /**
     * Check if position range is already used
     */
    private static function isPositionUsed(array $usedPositions, int $start, int $length): bool
    {
        $end = $start + $length - 1;
        foreach ($usedPositions as $used) {
            if ($start <= $used['end'] && $end >= $used['start']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get countries from cache or database
     */
    private static function getCountries(): Collection
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return Country::where('is_active', true)
                ->where(function ($q) {
                    $q->whereNotNull('name_th')
                        ->where('name_th', '!=', '')
                        ->orWhere(function ($q2) {
                            $q2->whereNotNull('name_en')
                                ->where('name_en', '!=', '');
                        });
                })
                ->get(['id', 'name_th', 'name_en', 'region'])
                ->map(fn($country) => [
                    'id' => $country->id,
                    'name_th' => $country->name_th,
                    'name_en' => $country->name_en,
                    'region' => $country->region,
                ]);
        });
    }

    /**
     * Clear the countries cache
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
