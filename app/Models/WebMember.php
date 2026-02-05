<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class WebMember extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'line_id',
        'email_verified',
        'email_verified_at',
        'phone_verified',
        'phone_verified_at',
        'consent_marketing',
        'consent_terms',
        'consent_privacy',
        'consent_at',
        'status',
        'last_login_at',
        'last_login_ip',
        'avatar',
        'birth_date',
        'gender',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified' => 'boolean',
        'email_verified_at' => 'datetime',
        'phone_verified' => 'boolean',
        'phone_verified_at' => 'datetime',
        'consent_marketing' => 'boolean',
        'consent_terms' => 'boolean',
        'consent_privacy' => 'boolean',
        'consent_at' => 'datetime',
        'last_login_at' => 'datetime',
        'birth_date' => 'date',
        'locked_until' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Get full name
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Check if account is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if account is locked
     */
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Check if phone is verified
     */
    public function isPhoneVerified(): bool
    {
        return $this->phone_verified;
    }

    /**
     * Check if email is verified
     */
    public function isEmailVerified(): bool
    {
        return $this->email_verified;
    }

    /**
     * Get OTP requests
     */
    public function otpRequests()
    {
        return $this->hasMany(OtpRequest::class);
    }

    /**
     * Get wishlist tours
     */
    public function wishlists()
    {
        return $this->belongsToMany(Tour::class, 'web_member_wishlists')
            ->withTimestamps();
    }

    /**
     * Increment failed login attempts
     */
    public function incrementFailedAttempts(): void
    {
        $this->failed_login_attempts++;
        
        // Lock account after 5 failed attempts
        if ($this->failed_login_attempts >= 5) {
            $this->locked_until = now()->addMinutes(15);
        }
        
        $this->save();
    }

    /**
     * Reset failed login attempts
     */
    public function resetFailedAttempts(): void
    {
        $this->failed_login_attempts = 0;
        $this->locked_until = null;
        $this->save();
    }

    /**
     * Update last login info
     */
    public function updateLastLogin(string $ip): void
    {
        $this->last_login_at = now();
        $this->last_login_ip = $ip;
        $this->resetFailedAttempts();
    }

    /**
     * Normalize phone to MSISDN format (66xxxxxxxxx)
     */
    public static function normalizePhone(string $phone): string
    {
        $s = preg_replace('/[^\d]/', '', trim($phone));

        // Handle 0066 prefix
        if (str_starts_with($s, '0066')) {
            $s = '66' . substr($s, 4);
        }

        // Handle 0 prefix (Thai local format)
        if (preg_match('/^0\d{9}$/', $s)) {
            return '66' . substr($s, 1);
        }

        // Already in MSISDN format
        if (preg_match('/^66\d{9}$/', $s)) {
            return $s;
        }

        throw new \InvalidArgumentException('Invalid Thai phone number format');
    }
}
