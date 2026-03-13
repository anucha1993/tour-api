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
        public ?int $currentPage = null,
        public ?int $lastPage = null,
        public ?int $perPage = null,
    ) {}

    /**
     * Create success result
     */
    public static function success(
        array $tours,
        ?string $nextCursor = null,
        bool $hasMore = false,
        ?int $currentPage = null,
        ?int $lastPage = null,
        ?int $perPage = null,
    ): self {
        return new self(
            success: true,
            tours: $tours,
            nextCursor: $nextCursor,
            hasMore: $hasMore,
            totalCount: count($tours),
            currentPage: $currentPage,
            lastPage: $lastPage,
            perPage: $perPage,
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
     * Supports cursor-based (nextCursor) and page-based (currentPage < lastPage)
     */
    public function shouldContinue(): bool
    {
        if (!$this->success) return false;
        if ($this->hasMore) return true;
        if ($this->currentPage !== null && $this->lastPage !== null) {
            return $this->currentPage < $this->lastPage;
        }
        return false;
    }
}
