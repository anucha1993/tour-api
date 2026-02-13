<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Newsletter extends Model
{
    protected $fillable = [
        'subject',
        'content_html',
        'content_text',
        'status',
        'scheduled_at',
        'sent_at',
        'expires_at',
        'template',
        'recipient_filter',
        'total_recipients',
        'sent_count',
        'failed_count',
        'opened_count',
        'batch_size',
        'batch_delay_seconds',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'expires_at' => 'datetime',
        'recipient_filter' => 'array',
        'total_recipients' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'opened_count' => 'integer',
        'batch_size' => 'integer',
        'batch_delay_seconds' => 'integer',
    ];

    // ==================== Scopes ====================

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    // ==================== Helpers ====================

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function canSend(): bool
    {
        return in_array($this->status, ['draft', 'scheduled'])
            && !$this->isExpired();
    }

    // ==================== Relationships ====================

    public function logs()
    {
        return $this->hasMany(NewsletterLog::class);
    }
}
