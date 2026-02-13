<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Subscriber extends Model
{
    protected $fillable = [
        'email',
        'status',
        'source_page',
        'interest_country',
        'confirmation_token',
        'token_expires_at',
        'confirmed_at',
        'subscribed_at',
        'unsubscribed_at',
        'unsubscribe_token',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    // ==================== Scopes ====================

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeUnsubscribed($query)
    {
        return $query->where('status', 'unsubscribed');
    }

    // ==================== Helpers ====================

    /**
     * Generate a confirmation token for double opt-in
     */
    public function generateConfirmationToken(int $expiresInHours = 24): string
    {
        $token = Str::random(64);
        $this->update([
            'confirmation_token' => $token,
            'token_expires_at' => now()->addHours($expiresInHours),
        ]);
        return $token;
    }

    /**
     * Confirm subscription (double opt-in)
     */
    public function confirm(): bool
    {
        if ($this->status === 'active') {
            return true; // Already confirmed
        }

        if ($this->token_expires_at && $this->token_expires_at->isPast()) {
            return false; // Token expired
        }

        $this->update([
            'status' => 'active',
            'confirmed_at' => now(),
            'subscribed_at' => now(),
            'confirmation_token' => null,
            'token_expires_at' => null,
            'unsubscribe_token' => Str::random(64),
        ]);

        return true;
    }

    /**
     * Unsubscribe
     */
    public function unsubscribe(): void
    {
        $this->update([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);
    }

    /**
     * Check if token is valid
     */
    public function isTokenValid(): bool
    {
        return $this->confirmation_token
            && $this->token_expires_at
            && !$this->token_expires_at->isPast();
    }

    // ==================== Relationships ====================

    public function newsletterLogs()
    {
        return $this->hasMany(NewsletterLog::class);
    }
}
