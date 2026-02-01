<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transport extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'code',
        'code1',
        'name',
        'type',
        'image',
        'status',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Transport types
     */
    public const TYPE_AIRLINE = 'airline';
    public const TYPE_BUS = 'bus';
    public const TYPE_VAN = 'van';
    public const TYPE_BOAT = 'boat';

    public const TYPES = [
        self::TYPE_AIRLINE => 'สายการบิน',
        self::TYPE_BUS => 'รถบัส',
        self::TYPE_VAN => 'รถตู้',
        self::TYPE_BOAT => 'เรือ',
    ];

    /**
     * Status options
     */
    public const STATUS_ON = 'on';
    public const STATUS_OFF = 'off';

    /**
     * Scope: Active transports only
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ON);
    }

    /**
     * Scope: Filter by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Airlines only
     */
    public function scopeAirlines($query)
    {
        return $query->where('type', self::TYPE_AIRLINE);
    }

    /**
     * Check if transport is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ON;
    }

    /**
     * Get type label
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
