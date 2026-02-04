<?php

namespace App\Services\WholesalerAdapters\DTOs;

/**
 * Data Transfer Object for Periods/Schedules Sync Result (Two-Phase Sync)
 */
class PeriodsResult
{
    public function __construct(
        public readonly bool $success,
        public readonly array $periods = [],
        public readonly ?string $error = null,
        public readonly ?string $errorCode = null,
        public readonly array $rawData = [], // Full API response for itineraries, cities, etc.
    ) {}

    /**
     * Create a successful result
     */
    public static function success(array $periods, array $rawData = []): self
    {
        return new self(
            success: true,
            periods: $periods,
            rawData: $rawData,
        );
    }

    /**
     * Create a failed result
     */
    public static function failed(string $error, ?string $errorCode = null): self
    {
        return new self(
            success: false,
            periods: [],
            error: $error,
            errorCode: $errorCode,
        );
    }

    /**
     * Get the number of periods
     */
    public function count(): int
    {
        return count($this->periods);
    }

    /**
     * Check if there are any periods
     */
    public function hasPeriods(): bool
    {
        return $this->count() > 0;
    }
}
