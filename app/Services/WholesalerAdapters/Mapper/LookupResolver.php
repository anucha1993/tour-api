<?php

namespace App\Services\WholesalerAdapters\Mapper;

use App\Models\City;
use App\Models\Country;
use App\Models\SectionDefinition;
use App\Models\Transport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Resolves lookups for fields that need to map to IDs
 * (countries, cities, transports, etc.)
 */
class LookupResolver
{
    protected array $countryCache = [];
    protected array $cityCache = [];
    protected array $transportCache = [];

    /**
     * Resolve a lookup value to an ID
     */
    public function resolve(mixed $value, SectionDefinition $definition): LookupResult
    {
        if (!$definition->hasLookup()) {
            return LookupResult::notApplicable($value);
        }

        $table = $definition->lookup_table;
        $matchFields = $definition->lookup_match_fields ?? ['name'];
        $createIfNotFound = $definition->lookup_create_if_not_found;

        // Handle arrays
        if (is_array($value)) {
            $ids = [];
            $notFound = [];

            foreach ($value as $item) {
                $result = $this->resolveSingle($item, $table, $matchFields, $createIfNotFound);
                if ($result->found) {
                    $ids[] = $result->id;
                } else {
                    $notFound[] = $item;
                }
            }

            if (!empty($notFound)) {
                return LookupResult::partialMatch($ids, $notFound);
            }

            return LookupResult::success($ids);
        }

        // Handle single value
        return $this->resolveSingle($value, $table, $matchFields, $createIfNotFound);
    }

    /**
     * Resolve a single value
     */
    protected function resolveSingle(
        mixed $value,
        string $table,
        array $matchFields,
        bool $createIfNotFound
    ): LookupResult {
        if (empty($value)) {
            return LookupResult::notFound($value);
        }

        $stringValue = trim((string) $value);

        return match ($table) {
            'countries' => $this->resolveCountry($stringValue, $matchFields),
            'cities' => $this->resolveCity($stringValue, $matchFields, $createIfNotFound),
            'transports' => $this->resolveTransport($stringValue, $matchFields),
            default => LookupResult::notFound($value, "Unknown lookup table: $table"),
        };
    }

    /**
     * Resolve country
     */
    protected function resolveCountry(string $value, array $matchFields): LookupResult
    {
        // Check cache
        $cacheKey = strtolower($value);
        if (isset($this->countryCache[$cacheKey])) {
            return LookupResult::success($this->countryCache[$cacheKey]);
        }

        // Search in database
        $query = Country::query();

        foreach ($matchFields as $index => $field) {
            if ($index === 0) {
                $query->where($field, 'LIKE', $value);
            } else {
                $query->orWhere($field, 'LIKE', $value);
            }
        }

        $country = $query->first();

        if ($country) {
            $this->countryCache[$cacheKey] = $country->id;
            return LookupResult::success($country->id);
        }

        // Try fuzzy match on name
        $country = Country::where('name_en', 'LIKE', "%{$value}%")
            ->orWhere('name_th', 'LIKE', "%{$value}%")
            ->first();

        if ($country) {
            $this->countryCache[$cacheKey] = $country->id;
            return LookupResult::success($country->id);
        }

        return LookupResult::notFound($value, "Country not found: $value");
    }

    /**
     * Resolve city
     */
    protected function resolveCity(string $value, array $matchFields, bool $createIfNotFound): LookupResult
    {
        // Check cache
        $cacheKey = strtolower($value);
        if (isset($this->cityCache[$cacheKey])) {
            return LookupResult::success($this->cityCache[$cacheKey]);
        }

        // Search in database
        $query = City::query();

        foreach ($matchFields as $index => $field) {
            if ($index === 0) {
                $query->where($field, 'LIKE', $value);
            } else {
                $query->orWhere($field, 'LIKE', $value);
            }
        }

        $city = $query->first();

        if ($city) {
            $this->cityCache[$cacheKey] = $city->id;
            return LookupResult::success($city->id);
        }

        // Try fuzzy match
        $city = City::where('name_en', 'LIKE', "%{$value}%")
            ->orWhere('name_th', 'LIKE', "%{$value}%")
            ->first();

        if ($city) {
            $this->cityCache[$cacheKey] = $city->id;
            return LookupResult::success($city->id);
        }

        // Create if enabled
        if ($createIfNotFound) {
            try {
                $newCity = City::create([
                    'name_en' => $value,
                    'name_th' => $value,
                ]);
                $this->cityCache[$cacheKey] = $newCity->id;
                return LookupResult::created($newCity->id);
            } catch (\Exception $e) {
                return LookupResult::notFound($value, "Failed to create city: " . $e->getMessage());
            }
        }

        return LookupResult::notFound($value, "City not found: $value");
    }

    /**
     * Resolve transport
     */
    protected function resolveTransport(string $value, array $matchFields): LookupResult
    {
        // Check cache
        $cacheKey = strtolower($value);
        if (isset($this->transportCache[$cacheKey])) {
            return LookupResult::success($this->transportCache[$cacheKey]);
        }

        // Search in database
        $query = Transport::query();

        foreach ($matchFields as $index => $field) {
            if ($index === 0) {
                $query->where($field, 'LIKE', $value);
            } else {
                $query->orWhere($field, 'LIKE', $value);
            }
        }

        $transport = $query->first();

        if ($transport) {
            $this->transportCache[$cacheKey] = $transport->id;
            return LookupResult::success($transport->id);
        }

        // Try fuzzy match on name or code
        $transport = Transport::where('name', 'LIKE', "%{$value}%")
            ->orWhere('code', 'LIKE', "%{$value}%")
            ->first();

        if ($transport) {
            $this->transportCache[$cacheKey] = $transport->id;
            return LookupResult::success($transport->id);
        }

        return LookupResult::notFound($value, "Transport not found: $value");
    }

    /**
     * Clear caches
     */
    public function clearCache(): void
    {
        $this->countryCache = [];
        $this->cityCache = [];
        $this->transportCache = [];
    }

    /**
     * Preload countries for performance
     */
    public function preloadCountries(): void
    {
        $countries = Country::all();
        
        foreach ($countries as $country) {
            $this->countryCache[strtolower($country->name_en)] = $country->id;
            $this->countryCache[strtolower($country->name_th)] = $country->id;
            if ($country->iso2) {
                $this->countryCache[strtolower($country->iso2)] = $country->id;
            }
            if ($country->iso3) {
                $this->countryCache[strtolower($country->iso3)] = $country->id;
            }
        }
    }
}

/**
 * Result of lookup resolution
 */
class LookupResult
{
    public function __construct(
        public bool $found,
        public mixed $id = null,
        public mixed $originalValue = null,
        public ?string $error = null,
        public bool $created = false,
        public array $ids = [],
        public array $notFoundValues = [],
    ) {}

    public static function success(mixed $id): self
    {
        return new self(found: true, id: $id);
    }

    public static function created(mixed $id): self
    {
        return new self(found: true, id: $id, created: true);
    }

    public static function notFound(mixed $value, ?string $error = null): self
    {
        return new self(found: false, originalValue: $value, error: $error ?? "Not found: $value");
    }

    public static function partialMatch(array $ids, array $notFound): self
    {
        return new self(found: count($ids) > 0, ids: $ids, notFoundValues: $notFound);
    }

    public static function notApplicable(mixed $value): self
    {
        return new self(found: true, id: $value);
    }

    public function getValue(): mixed
    {
        if (!empty($this->ids)) {
            return $this->ids;
        }
        return $this->id;
    }
}
