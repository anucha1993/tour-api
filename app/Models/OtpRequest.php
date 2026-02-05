<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtpRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone_msisdn',
        'message_id',
        'otp_code',
        'ttl',
        'expires_at',
        'purpose',
        'attempts',
        'max_attempts',
        'verified',
        'verified_at',
        'ip_address',
        'user_agent',
        'web_member_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'verified' => 'boolean',
        'ttl' => 'integer',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
    ];

    /**
     * Relationship to web member
     */
    public function webMember()
    {
        return $this->belongsTo(WebMember::class);
    }

    /**
     * Check if OTP is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if max attempts reached
     */
    public function isMaxAttemptsReached(): bool
    {
        return $this->attempts >= $this->max_attempts;
    }

    /**
     * Check if OTP can be verified
     */
    public function canVerify(): bool
    {
        return !$this->verified && !$this->isExpired() && !$this->isMaxAttemptsReached();
    }

    /**
     * Increment attempts
     */
    public function incrementAttempts(): void
    {
        $this->attempts++;
        $this->save();
    }

    /**
     * Mark as verified
     */
    public function markAsVerified(): void
    {
        $this->verified = true;
        $this->verified_at = now();
        $this->save();
    }

    /**
     * Scope for pending OTPs
     */
    public function scopePending($query)
    {
        return $query->where('verified', false)
            ->where('expires_at', '>', now());
    }

    /**
     * Scope by phone number
     */
    public function scopeForPhone($query, string $phone)
    {
        return $query->where('phone_msisdn', $phone);
    }

    /**
     * Scope by purpose
     */
    public function scopeForPurpose($query, string $purpose)
    {
        return $query->where('purpose', $purpose);
    }

    /**
     * Check rate limit for phone
     */
    public static function isRateLimitedByPhone(string $phone, int $maxRequests = 3, int $minutes = 10): bool
    {
        $count = self::where('phone_msisdn', $phone)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->count();

        return $count >= $maxRequests;
    }

    /**
     * Check rate limit for IP
     */
    public static function isRateLimitedByIp(string $ip, int $maxRequests = 20, int $minutes = 10): bool
    {
        $count = self::where('ip_address', $ip)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->count();

        return $count >= $maxRequests;
    }
}
