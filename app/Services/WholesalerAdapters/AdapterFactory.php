<?php

namespace App\Services\WholesalerAdapters;

use App\Models\Wholesaler;
use App\Models\WholesalerApiConfig;
use App\Services\WholesalerAdapters\Booking\ManualBookingAdapter;
use App\Services\WholesalerAdapters\Booking\ZegoBookingAdapter;
use App\Services\WholesalerAdapters\Contracts\AdapterInterface;
use App\Services\WholesalerAdapters\Contracts\BookingAdapterInterface;
use Illuminate\Support\Str;
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

        // Headcode integration: load and invoke a custom PHP file
        if ($config->integration_type === 'headcode') {
            return static::createHeadcodeAdapter($config);
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
     * Create a headcode adapter from a custom PHP file in storage/headcode/
     *
     * Security: filename is validated to [a-zA-Z0-9_-] only before loading.
     * The file must define class Headcode{StudlyCase(filename)}Adapter
     * extending HeadcodeBaseAdapter.
     */
    protected static function createHeadcodeAdapter(WholesalerApiConfig $config): AdapterInterface
    {
        $headcodeFile = $config->headcode_file ?? '';

        if (empty($headcodeFile)) {
            throw new InvalidArgumentException(
                "Headcode integration requires headcode_file for wholesaler ID: {$config->wholesaler_id}"
            );
        }

        // Strip .php extension if provided, then validate (prevent path traversal)
        $basename = pathinfo($headcodeFile, PATHINFO_FILENAME);
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $basename)) {
            throw new InvalidArgumentException("Invalid headcode filename: '{$basename}'");
        }

        $filePath = storage_path("headcode/{$basename}.php");
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("Headcode file not found: {$filePath}");
        }

        require_once $filePath;

        // Derive class name: e.g. 'look_planets' → 'HeadcodeLookPlanetsAdapter'
        $className = 'Headcode' . Str::studly($basename) . 'Adapter';

        if (!class_exists($className)) {
            throw new InvalidArgumentException(
                "Class {$className} not found in {$filePath}. " .
                "The file must define: class {$className} extends \\App\\Services\\WholesalerAdapters\\HeadcodeBaseAdapter"
            );
        }

        return new $className($config);
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

    // ═══════════════════════════════════════════════════════════
    // BOOKING ADAPTERS (Outbound)
    // ═══════════════════════════════════════════════════════════

    /**
     * Registered booking adapter classes keyed by provider code.
     *
     * @var array<string, class-string<BookingAdapterInterface>>
     */
    protected static array $bookingAdapters = [
        'zego' => ZegoBookingAdapter::class,
        'manual' => ManualBookingAdapter::class,
    ];

    /**
     * Register a custom booking adapter class.
     */
    public static function registerBookingAdapter(string $providerCode, string $adapterClass): void
    {
        static::$bookingAdapters[strtolower($providerCode)] = $adapterClass;
    }

    /**
     * Create the configured booking adapter for a wholesaler.
     */
    public static function createBookingAdapter(int $wholesalerId): BookingAdapterInterface
    {
        $config = WholesalerApiConfig::where('wholesaler_id', $wholesalerId)->first();
        if (!$config) {
            throw new InvalidArgumentException("No API config found for wholesaler ID: {$wholesalerId}");
        }
        if (!$config->booking_enabled) {
            throw new InvalidArgumentException("Booking is not enabled for wholesaler ID: {$wholesalerId}");
        }

        $provider = strtolower((string) $config->booking_provider);
        if (!isset(static::$bookingAdapters[$provider])) {
            throw new InvalidArgumentException("Unknown booking provider: '{$provider}'");
        }

        $class = static::$bookingAdapters[$provider];
        return new $class($config);
    }

    /**
     * List all available booking provider codes with their human names + schemas.
     * Used by admin UI to render the provider dropdown + dynamic form.
     */
    public static function listBookingProviders(): array
    {
        $result = [];
        foreach (static::$bookingAdapters as $code => $class) {
            $result[] = [
                'code' => $class::getProviderCode(),
                'name' => $class::getProviderName(),
                'schema' => $class::getConfigSchema(),
            ];
        }
        return $result;
    }

    /**
     * Get the config schema for a single booking provider.
     */
    public static function getBookingProviderSchema(string $providerCode): ?array
    {
        $code = strtolower($providerCode);
        if (!isset(static::$bookingAdapters[$code])) {
            return null;
        }
        $class = static::$bookingAdapters[$code];
        return [
            'code' => $class::getProviderCode(),
            'name' => $class::getProviderName(),
            'schema' => $class::getConfigSchema(),
        ];
    }
}
