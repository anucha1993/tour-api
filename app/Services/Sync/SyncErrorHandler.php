<?php

namespace App\Services\Sync;

use App\Models\SyncErrorLog;
use App\Models\SyncLog;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SyncErrorHandler - Centralized error handling for sync operations
 * 
 * Features:
 * - Categorized error types
 * - Error logging to database
 * - Retry decision logic
 * - Error aggregation for reporting
 */
class SyncErrorHandler
{
    // Error types
    public const TYPE_MAPPING = 'mapping';
    public const TYPE_VALIDATION = 'validation';
    public const TYPE_LOOKUP = 'lookup';
    public const TYPE_TYPE_CAST = 'type_cast';
    public const TYPE_API = 'api';
    public const TYPE_DATABASE = 'database';
    public const TYPE_RATE_LIMIT = 'rate_limit';
    public const TYPE_TIMEOUT = 'timeout';
    public const TYPE_UNKNOWN = 'unknown';

    // Retryable error types
    protected array $retryableTypes = [
        self::TYPE_API,
        self::TYPE_DATABASE,
        self::TYPE_RATE_LIMIT,
        self::TYPE_TIMEOUT,
    ];

    protected SyncLog $syncLog;
    protected int $wholesalerId;
    protected array $errors = [];

    public function __construct(SyncLog $syncLog, int $wholesalerId)
    {
        $this->syncLog = $syncLog;
        $this->wholesalerId = $wholesalerId;
    }

    /**
     * Handle and log an error
     */
    public function handle(
        Throwable $exception,
        string $entityType = 'tour',
        ?string $entityCode = null,
        ?array $rawData = null,
        ?string $errorType = null
    ): void {
        // Determine error type if not provided
        if (!$errorType) {
            $errorType = $this->categorizeException($exception);
        }

        // Log to database
        $errorLog = SyncErrorLog::create([
            'sync_log_id' => $this->syncLog->id,
            'wholesaler_id' => $this->wholesalerId,
            'entity_type' => $entityType,
            'entity_code' => $entityCode ?? 'unknown',
            'error_type' => $errorType,
            'error_message' => $this->truncateMessage($exception->getMessage()),
            'raw_data' => $rawData,
            'stack_trace' => $this->getShortStackTrace($exception),
        ]);

        // Store in memory for aggregation
        $this->errors[] = [
            'id' => $errorLog->id,
            'type' => $errorType,
            'code' => $entityCode,
            'message' => $exception->getMessage(),
        ];

        // Log to file
        Log::warning('SyncErrorHandler: Error occurred', [
            'sync_log_id' => $this->syncLog->id,
            'error_type' => $errorType,
            'entity_type' => $entityType,
            'entity_code' => $entityCode,
            'message' => $exception->getMessage(),
        ]);
    }

    /**
     * Log a simple error without exception
     */
    public function logError(
        string $message,
        string $errorType = self::TYPE_UNKNOWN,
        string $entityType = 'tour',
        ?string $entityCode = null,
        ?array $rawData = null
    ): void {
        SyncErrorLog::create([
            'sync_log_id' => $this->syncLog->id,
            'wholesaler_id' => $this->wholesalerId,
            'entity_type' => $entityType,
            'entity_code' => $entityCode ?? 'unknown',
            'error_type' => $errorType,
            'error_message' => $this->truncateMessage($message),
            'raw_data' => $rawData,
        ]);

        $this->errors[] = [
            'type' => $errorType,
            'code' => $entityCode,
            'message' => $message,
        ];
    }

    /**
     * Categorize exception to error type
     */
    protected function categorizeException(Throwable $e): string
    {
        $message = strtolower($e->getMessage());
        $class = get_class($e);

        // Database errors
        if (str_contains($class, 'QueryException') || str_contains($class, 'PDOException')) {
            return self::TYPE_DATABASE;
        }

        // HTTP/API errors
        if (str_contains($class, 'RequestException') || str_contains($class, 'ConnectionException')) {
            return self::TYPE_API;
        }

        // Rate limiting
        if (str_contains($message, 'rate limit') || str_contains($message, 'too many requests')) {
            return self::TYPE_RATE_LIMIT;
        }

        // Timeout
        if (str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
            return self::TYPE_TIMEOUT;
        }

        // Validation
        if (str_contains($class, 'ValidationException')) {
            return self::TYPE_VALIDATION;
        }

        // Type casting
        if (str_contains($message, 'type') || str_contains($message, 'cast')) {
            return self::TYPE_TYPE_CAST;
        }

        return self::TYPE_UNKNOWN;
    }

    /**
     * Check if error type is retryable
     */
    public function isRetryable(string $errorType): bool
    {
        return in_array($errorType, $this->retryableTypes);
    }

    /**
     * Should retry the entire sync?
     */
    public function shouldRetrySync(): bool
    {
        // If more than 50% of errors are retryable, suggest retry
        $retryableCount = 0;
        foreach ($this->errors as $error) {
            if ($this->isRetryable($error['type'])) {
                $retryableCount++;
            }
        }

        $total = count($this->errors);
        return $total > 0 && ($retryableCount / $total) > 0.5;
    }

    /**
     * Get error summary
     */
    public function getSummary(): array
    {
        $summary = [
            'total' => count($this->errors),
            'by_type' => [],
        ];

        foreach ($this->errors as $error) {
            $type = $error['type'];
            if (!isset($summary['by_type'][$type])) {
                $summary['by_type'][$type] = 0;
            }
            $summary['by_type'][$type]++;
        }

        return $summary;
    }

    /**
     * Get all errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get error count
     */
    public function getErrorCount(): int
    {
        return count($this->errors);
    }

    /**
     * Clear errors (for retry)
     */
    public function clear(): void
    {
        $this->errors = [];
    }

    /**
     * Truncate long message
     */
    protected function truncateMessage(string $message, int $maxLength = 1000): string
    {
        if (strlen($message) > $maxLength) {
            return substr($message, 0, $maxLength - 3) . '...';
        }
        return $message;
    }

    /**
     * Get short stack trace (first 5 frames)
     */
    protected function getShortStackTrace(Throwable $e, int $frames = 5): string
    {
        $trace = $e->getTraceAsString();
        $lines = explode("\n", $trace);
        $shortLines = array_slice($lines, 0, $frames);
        return implode("\n", $shortLines);
    }
}
