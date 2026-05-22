<?php

namespace App\Services\WholesalerAdapters\Contracts\DTOs;

use Carbon\Carbon;

/**
 * Result from creating a booking quote.
 *
 * A quote represents a "soft hold" — for providers that don't reserve seats
 * server-side (e.g. Zego), this is a session/UX countdown only.
 * For providers that do (e.g. Headcode), this is a real hold with seat reservation.
 *
 * The `quoteId` is used to submit the booking later via submitBooking().
 */
class QuoteResult
{
    public function __construct(
        public bool $success,
        public ?string $quoteId = null,
        public ?Carbon $expiresAt = null,
        public float $totalPrice = 0,
        public string $currency = 'THB',
        /** Itemized price breakdown */
        public array $breakdown = [],
        /** Available passenger codes the provider expects (e.g. MA/CH/IF) */
        public array $passengerTypes = [],
        /** Available room codes the provider expects */
        public array $roomTypes = [],
        public bool $isRealHold = false,
        public ?string $errorMessage = null,
        public ?string $errorCode = null,
        public ?array $metadata = null,
    ) {}

    public static function success(
        string $quoteId,
        int $ttlSeconds,
        float $totalPrice,
        array $breakdown = [],
        array $passengerTypes = [],
        array $roomTypes = [],
        bool $isRealHold = false,
        ?array $metadata = null,
    ): self {
        return new self(
            success: true,
            quoteId: $quoteId,
            expiresAt: now()->addSeconds($ttlSeconds),
            totalPrice: $totalPrice,
            breakdown: $breakdown,
            passengerTypes: $passengerTypes,
            roomTypes: $roomTypes,
            isRealHold: $isRealHold,
            metadata: $metadata,
        );
    }

    public static function failed(string $message, ?string $code = null): self
    {
        return new self(
            success: false,
            errorMessage: $message,
            errorCode: $code,
        );
    }

    public function getRemainingSeconds(): int
    {
        if (!$this->success || !$this->expiresAt) {
            return 0;
        }
        return max(0, (int) now()->diffInSeconds($this->expiresAt, false));
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'quote_id' => $this->quoteId,
            'expires_at' => $this->expiresAt?->toIso8601String(),
            'remaining_seconds' => $this->getRemainingSeconds(),
            'total_price' => $this->totalPrice,
            'currency' => $this->currency,
            'breakdown' => $this->breakdown,
            'passenger_types' => $this->passengerTypes,
            'room_types' => $this->roomTypes,
            'is_real_hold' => $this->isRealHold,
            'error_message' => $this->errorMessage,
            'error_code' => $this->errorCode,
        ];
    }
}
