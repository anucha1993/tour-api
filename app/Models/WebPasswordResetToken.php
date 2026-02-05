<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Password Reset Token สำหรับ Web Members เท่านั้น
 * แยกจาก users (backend admin/staff)
 */
class WebPasswordResetToken extends Model
{
    protected $table = 'web_password_reset_tokens';

    protected $fillable = [
        'email',
        'token',
        'expires_at',
        'used',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'used' => 'boolean',
    ];

    /**
     * Check if token is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if token is valid
     */
    public function isValid(): bool
    {
        return !$this->used && !$this->isExpired();
    }

    /**
     * Mark token as used
     */
    public function markAsUsed(): void
    {
        $this->used = true;
        $this->used_at = now();
        $this->save();
    }

    /**
     * Create a new reset token for email (web member only)
     */
    public static function createForEmail(string $email): self
    {
        // Invalidate old tokens
        self::where('email', $email)->where('used', false)->update(['used' => true]);

        return self::create([
            'email' => $email,
            'token' => bin2hex(random_bytes(32)),
            'expires_at' => now()->addHours(1),
        ]);
    }

    /**
     * Find valid token
     */
    public static function findValidToken(string $token): ?self
    {
        return self::where('token', $token)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->first();
    }
}
