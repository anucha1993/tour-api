<?php

namespace App\Services\WholesalerAdapters\Contracts\DTOs;

/**
 * Result from booking operations (confirm, cancel, modify)
 */
class BookingResult
{
    public function __construct(
        public bool $success,
        public ?string $bookingRef = null,
        public ?string $confirmationNumber = null,
        public ?string $status = null,
        public ?string $errorMessage = null,
        public ?string $errorCode = null,
        public ?array $metadata = null,
    ) {}

    /**
     * Create success result
     */
    public static function success(
        string $bookingRef,
        ?string $confirmationNumber = null,
        string $status = 'confirmed',
        ?array $metadata = null
    ): self {
        return new self(
            success: true,
            bookingRef: $bookingRef,
            confirmationNumber: $confirmationNumber,
            status: $status,
            metadata: $metadata,
        );
    }

    /**
     * Create cancelled result
     */
    public static function cancelled(string $bookingRef, ?array $refundInfo = null): self
    {
        return new self(
            success: true,
            bookingRef: $bookingRef,
            status: 'cancelled',
            metadata: ['refund' => $refundInfo],
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
     * Check if booking is confirmed
     */
    public function isConfirmed(): bool
    {
        return $this->success && $this->status === 'confirmed';
    }

    /**
     * Check if booking is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->success && $this->status === 'cancelled';
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'booking_ref' => $this->bookingRef,
            'confirmation_number' => $this->confirmationNumber,
            'status' => $this->status,
            'error_message' => $this->errorMessage,
            'error_code' => $this->errorCode,
            'metadata' => $this->metadata,
        ];
    }
}
