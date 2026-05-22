<?php

namespace App\Services\WholesalerAdapters\Booking;

use App\Models\WholesalerApiConfig;
use App\Services\WholesalerAdapters\Contracts\BookingAdapterInterface;

/**
 * Base class for all Booking adapters. Provides config access helpers.
 */
abstract class BaseBookingAdapter implements BookingAdapterInterface
{
    protected WholesalerApiConfig $config;
    protected array $bookingConfig;

    public function __construct(WholesalerApiConfig $config)
    {
        $this->config = $config;
        $this->bookingConfig = $config->booking_config ?? [];
    }

    protected function get(string $key, mixed $default = null): mixed
    {
        return $this->bookingConfig[$key] ?? $default;
    }

    protected function holdTtl(): int
    {
        return (int) ($this->config->booking_hold_ttl_seconds ?? 900);
    }

    public function validateConfig(): array
    {
        $errors = [];
        foreach (static::getConfigSchema() as $field) {
            if (($field['required'] ?? false) && empty($this->bookingConfig[$field['key']])) {
                $errors[] = "Field '{$field['label']}' is required";
            }
        }
        return $errors;
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, static::supportedFeatures(), true);
    }

    /** Override in subclass */
    protected static function supportedFeatures(): array
    {
        return [];
    }

    public function healthCheck(): bool
    {
        return empty($this->validateConfig());
    }
}
