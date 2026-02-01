<?php

namespace App\Services\WholesalerAdapters\Contracts\DTOs;

use Carbon\Carbon;

/**
 * Result from checking availability
 */
class AvailabilityResult
{
    public function __construct(
        public bool $available,
        public int $remainingSeats = 0,
        public float $priceAdult = 0,
        public float $priceChild = 0,
        public ?string $currency = 'THB',
        public ?Carbon $cachedAt = null,
        public ?Carbon $expiresAt = null,
        public ?string $errorMessage = null,
        public ?string $errorCode = null,
    ) {}

    /**
     * Create available result
     */
    public static function available(
        int $remainingSeats,
        float $priceAdult,
        float $priceChild = 0,
        string $currency = 'THB',
        int $ttlMinutes = 5
    ): self {
        return new self(
            available: true,
            remainingSeats: $remainingSeats,
            priceAdult: $priceAdult,
            priceChild: $priceChild,
            currency: $currency,
            cachedAt: now(),
            expiresAt: now()->addMinutes($ttlMinutes),
        );
    }

    /**
     * Create unavailable result
     */
    public static function unavailable(?string $message = null): self
    {
        return new self(
            available: false,
            errorMessage: $message ?? 'No seats available',
        );
    }

    /**
     * Create error result
     */
    public static function error(string $message, ?string $code = null): self
    {
        return new self(
            available: false,
            errorMessage: $message,
            errorCode: $code,
        );
    }

    /**
     * Check if result is still valid
     */
    public function isValid(): bool
    {
        if (!$this->expiresAt) {
            return false;
        }
        return now()->isBefore($this->expiresAt);
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'available' => $this->available,
            'remaining_seats' => $this->remainingSeats,
            'price_adult' => $this->priceAdult,
            'price_child' => $this->priceChild,
            'currency' => $this->currency,
            'cached_at' => $this->cachedAt?->toIso8601String(),
            'expires_at' => $this->expiresAt?->toIso8601String(),
            'error_message' => $this->errorMessage,
        ];
    }
}
