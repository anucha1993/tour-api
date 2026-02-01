<?php

namespace App\Services\WholesalerAdapters\Contracts\DTOs;

/**
 * Result from syncing tours from wholesaler
 */
class SyncResult
{
    public function __construct(
        public bool $success,
        public array $tours = [],
        public ?string $nextCursor = null,
        public bool $hasMore = false,
        public int $totalCount = 0,
        public ?string $errorMessage = null,
        public ?string $errorCode = null,
    ) {}

    /**
     * Create success result
     */
    public static function success(
        array $tours,
        ?string $nextCursor = null,
        bool $hasMore = false
    ): self {
        return new self(
            success: true,
            tours: $tours,
            nextCursor: $nextCursor,
            hasMore: $hasMore,
            totalCount: count($tours),
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
     * Check if has more data to fetch
     */
    public function shouldContinue(): bool
    {
        return $this->success && $this->hasMore && $this->nextCursor !== null;
    }
}
