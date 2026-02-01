<?php

namespace App\Services\WholesalerAdapters\Contracts\DTOs;

use Carbon\Carbon;

/**
 * Result from holding a booking
 */
class HoldResult
{
    public function __construct(
        public bool $success,
        public ?string $holdId = null,
        public ?Carbon $expiresAt = null,
        public ?string $errorMessage = null,
        public ?string $errorCode = null,
        public ?array $metadata = null,
    ) {}

    /**
     * Create success result
     */
    public static function success(string $holdId, int $ttlMinutes = 15, ?array $metadata = null): self
    {
        return new self(
            success: true,
            holdId: $holdId,
            expiresAt: now()->addMinutes($ttlMinutes),
            metadata: $metadata,
        );
    }

    /**
     * Create failed result
     */
    public static function failed(string $message, ?string $code = null): self
    {
        return new self(
            success: false,
            errorMessage: $message,
            errorCode: $code,
        );
    }

    /**
     * Check if hold is still valid
     */
    public function isValid(): bool
    {
        if (!$this->success || !$this->expiresAt) {
            return false;
        }
        return now()->isBefore($this->expiresAt);
    }

    /**
     * Get remaining seconds
     */
    public function getRemainingSeconds(): int
    {
        if (!$this->expiresAt) {
            return 0;
        }
        return max(0, now()->diffInSeconds($this->expiresAt, false));
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'hold_id' => $this->holdId,
            'expires_at' => $this->expiresAt?->toIso8601String(),
            'remaining_seconds' => $this->getRemainingSeconds(),
            'error_message' => $this->errorMessage,
            'error_code' => $this->errorCode,
            'metadata' => $this->metadata,
        ];
    }
}
