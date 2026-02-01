<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboundApiLog extends Model
{
    protected $fillable = [
        'wholesaler_id',
        'action',
        'endpoint',
        'method',
        'request_headers',
        'request_body',
        'response_code',
        'response_headers',
        'response_body',
        'response_time_ms',
        'tour_id',
        'sync_log_id',
        'status',
        'error_type',
        'error_message',
        'retry_of_id',
        'retry_count',
    ];

    protected $casts = [
        'request_headers' => 'array',
        'request_body' => 'array',
        'response_headers' => 'array',
        'response_body' => 'array',
    ];

    /**
     * Get the wholesaler
     */
    public function wholesaler(): BelongsTo
    {
        return $this->belongsTo(Wholesaler::class);
    }

    /**
     * Get the tour if linked
     */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /**
     * Get the sync log if linked
     */
    public function syncLog(): BelongsTo
    {
        return $this->belongsTo(SyncLog::class);
    }

    /**
     * Get the original log if this is a retry
     */
    public function retryOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'retry_of_id');
    }

    /**
     * Create a log entry (status will be set later via recordResponse/recordError)
     */
    public static function log(
        int $wholesalerId,
        string $action,
        string $endpoint,
        string $method,
        array $requestData = []
    ): self {
        return static::create([
            'wholesaler_id' => $wholesalerId,
            'action' => $action,
            'endpoint' => $endpoint,
            'method' => $method,
            'request_headers' => $requestData['headers'] ?? null,
            'request_body' => $requestData['body'] ?? null,
            'status' => 'success', // default, will be updated by recordResponse/recordError
        ]);
    }

    /**
     * Record response
     */
    public function recordResponse(int $code, array $body, int $timeMs, array $headers = []): void
    {
        $this->update([
            'response_code' => $code,
            'response_body' => $body,
            'response_headers' => $headers,
            'response_time_ms' => $timeMs,
            'status' => $code >= 200 && $code < 300 ? 'success' : 'failed',
        ]);
    }

    /**
     * Record error
     */
    public function recordError(string $type, string $message, int $timeMs = 0): void
    {
        $this->update([
            'status' => $type === 'timeout' ? 'timeout' : 'error',
            'error_type' => $type,
            'error_message' => $message,
            'response_time_ms' => $timeMs,
        ]);
    }

    /**
     * Scope for failed requests that need retry
     */
    public function scopeNeedsRetry($query, int $maxRetries = 3)
    {
        return $query->whereIn('status', ['failed', 'timeout', 'error'])
            ->where('retry_count', '<', $maxRetries);
    }
}
