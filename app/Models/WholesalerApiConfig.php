<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class WholesalerApiConfig extends Model
{
    protected $fillable = [
        'wholesaler_id',
        // API Connection
        'api_base_url',
        'api_version',
        'api_format',
        // Authentication
        'auth_type',
        'auth_credentials',
        'auth_header_name',
        // Rate Limiting
        'rate_limit_per_minute',
        'rate_limit_per_day',
        // Timeouts
        'connect_timeout_seconds',
        'request_timeout_seconds',
        'retry_attempts',
        // Sync Settings
        'sync_enabled',
        'sync_method',
        'sync_mode', // single or two_phase
        'sync_schedule',
        'sync_limit',
        'full_sync_schedule',
        // Webhook
        'webhook_enabled',
        'webhook_secret',
        'webhook_url',
        // Features Support
        'supports_availability_check',
        'supports_hold_booking',
        'supports_modify_booking',
        // Status
        'is_active',
        'last_health_check_at',
        'last_health_check_status',
        // Field Mapping
        'enabled_fields',
        // PDF Branding
        'pdf_header_image',
        'pdf_footer_image',
        'pdf_header_height',
        'pdf_footer_height',
        // Aggregation Config
        'aggregation_config',
        // Notification Settings
        'notifications_enabled',
        'notification_emails',
        'notification_types',
        // City Extraction
        'extract_cities_from_name',
    ];

    protected $casts = [
        'sync_enabled' => 'boolean',
        'webhook_enabled' => 'boolean',
        'supports_availability_check' => 'boolean',
        'supports_hold_booking' => 'boolean',
        'supports_modify_booking' => 'boolean',
        'is_active' => 'boolean',
        'last_health_check_at' => 'datetime',
        'last_health_check_status' => 'boolean',
        'enabled_fields' => 'array',
        'aggregation_config' => 'array',
        'notifications_enabled' => 'boolean',
        'notification_emails' => 'array',
        'notification_types' => 'array',
        'extract_cities_from_name' => 'boolean',
    ];

    /**
     * Encrypt credentials before saving
     */
    public function setAuthCredentialsAttribute($value): void
    {
        if ($value) {
            $this->attributes['auth_credentials'] = Crypt::encryptString(
                is_array($value) ? json_encode($value) : $value
            );
        } else {
            $this->attributes['auth_credentials'] = null;
        }
    }

    /**
     * Decrypt credentials when accessing
     */
    public function getAuthCredentialsAttribute($value): ?array
    {
        if ($value) {
            try {
                $decrypted = Crypt::decryptString($value);
                return json_decode($decrypted, true);
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Get the wholesaler that owns this config
     */
    public function wholesaler(): BelongsTo
    {
        return $this->belongsTo(Wholesaler::class);
    }

    /**
     * Get field mappings for this wholesaler
     */
    public function fieldMappings(): HasMany
    {
        return $this->hasMany(WholesalerFieldMapping::class, 'wholesaler_id', 'wholesaler_id');
    }

    /**
     * Get sync cursor for this wholesaler
     */
    public function syncCursor(): HasMany
    {
        return $this->hasMany(SyncCursor::class, 'wholesaler_id', 'wholesaler_id');
    }

    /**
     * Check if API is healthy
     */
    public function isHealthy(): bool
    {
        if (!$this->last_health_check_at) {
            return false;
        }
        
        // Consider healthy if last check was within 10 minutes and successful
        return $this->last_health_check_status && 
               $this->last_health_check_at->diffInMinutes(now()) < 10;
    }

    /**
     * Get health status string
     * Based on health check, or fallback to last sync status
     */
    public function getHealthStatusAttribute(): string
    {
        if (!$this->is_active) {
            return 'inactive';
        }
        
        // If we have health check data, use it
        if ($this->last_health_check_at) {
            if (!$this->last_health_check_status) {
                return 'down';
            }
            
            if ($this->last_health_check_at->diffInMinutes(now()) > 30) {
                return 'degraded';
            }
            
            return 'healthy';
        }
        
        // Fallback: use last sync status from sync_logs
        $lastSync = \App\Models\SyncLog::where('wholesaler_id', $this->wholesaler_id)
            ->latest()
            ->first();
        
        if ($lastSync) {
            if ($lastSync->status === 'completed') {
                return 'healthy';
            }
            if ($lastSync->status === 'failed') {
                return 'down';
            }
            if (in_array($lastSync->status, ['running', 'pending'])) {
                return 'degraded';
            }
        }
        
        return 'unknown';
    }
}
