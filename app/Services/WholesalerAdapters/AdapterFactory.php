<?php

namespace App\Services\WholesalerAdapters;

use App\Models\Wholesaler;
use App\Models\WholesalerApiConfig;
use App\Services\WholesalerAdapters\Contracts\AdapterInterface;
use InvalidArgumentException;

/**
 * Factory for creating Wholesaler Adapters
 */
class AdapterFactory
{
    /**
     * Registered adapter classes
     */
    protected static array $adapters = [];

    /**
     * Register an adapter class for a wholesaler code
     */
    public static function register(string $wholesalerCode, string $adapterClass): void
    {
        static::$adapters[strtoupper($wholesalerCode)] = $adapterClass;
    }

    /**
     * Create adapter for a wholesaler
     */
    public static function create(int $wholesalerId): AdapterInterface
    {
        $wholesaler = Wholesaler::findOrFail($wholesalerId);
        $config = WholesalerApiConfig::where('wholesaler_id', $wholesalerId)->first();

        if (!$config) {
            throw new InvalidArgumentException("No API config found for wholesaler ID: $wholesalerId");
        }

        // Check for registered adapter
        $code = strtoupper($wholesaler->code);
        
        if (isset(static::$adapters[$code])) {
            $adapterClass = static::$adapters[$code];
            return new $adapterClass($config);
        }

        // Fall back to generic adapter based on API format
        return static::createGenericAdapter($config);
    }

    /**
     * Create adapter by wholesaler code
     */
    public static function createByCode(string $wholesalerCode): AdapterInterface
    {
        $wholesaler = Wholesaler::where('code', $wholesalerCode)->firstOrFail();
        return static::create($wholesaler->id);
    }

    /**
     * Create generic adapter based on API format
     */
    protected static function createGenericAdapter(WholesalerApiConfig $config): AdapterInterface
    {
        return match ($config->api_format) {
            'rest' => new Adapters\GenericRestAdapter($config),
            'soap' => throw new InvalidArgumentException("SOAP adapters not yet implemented"),
            'graphql' => throw new InvalidArgumentException("GraphQL adapters not yet implemented"),
            default => new Adapters\GenericRestAdapter($config),
        };
    }

    /**
     * Check if adapter exists for wholesaler
     */
    public static function hasAdapter(int $wholesalerId): bool
    {
        try {
            $config = WholesalerApiConfig::where('wholesaler_id', $wholesalerId)->exists();
            return $config;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all registered adapter codes
     */
    public static function getRegisteredCodes(): array
    {
        return array_keys(static::$adapters);
    }
}
