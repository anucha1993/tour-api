<?php

namespace App\Services\WholesalerAdapters\DTOs;

/**
 * Result from fetching itineraries for a specific tour (Two-Phase Sync)
 */
class ItinerariesResult
{
    public function __construct(
        public readonly bool $success,
        public readonly array $itineraries = [],
        public readonly ?string $error = null,
        public readonly ?string $errorCode = null,
    ) {}

    public static function success(array $itineraries): self
    {
        return new self(
            success: true,
            itineraries: $itineraries,
        );
    }

    public static function failed(string $message, ?string $code = null): self
    {
        return new self(
            success: false,
            itineraries: [],
            error: $message,
            errorCode: $code,
        );
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'itineraries' => $this->itineraries,
            'itineraries_count' => count($this->itineraries),
            'error' => $this->error,
            'error_code' => $this->errorCode,
        ];
    }
}
