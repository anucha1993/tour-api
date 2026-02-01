<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Country extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'iso2',
        'iso3',
        'name_en',
        'name_th',
        'slug',
        'region',
        'flag_emoji',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Region constants
     */
    public const REGION_ASIA = 'Asia';
    public const REGION_EUROPE = 'Europe';
    public const REGION_AFRICA = 'Africa';
    public const REGION_NORTH_AMERICA = 'North America';
    public const REGION_SOUTH_AMERICA = 'South America';
    public const REGION_OCEANIA = 'Oceania';
    public const REGION_MIDDLE_EAST = 'Middle East';

    public const REGIONS = [
        self::REGION_ASIA => 'เอเชีย',
        self::REGION_EUROPE => 'ยุโรป',
        self::REGION_AFRICA => 'แอฟริกา',
        self::REGION_NORTH_AMERICA => 'อเมริกาเหนือ',
        self::REGION_SOUTH_AMERICA => 'อเมริกาใต้',
        self::REGION_OCEANIA => 'โอเชียเนีย',
        self::REGION_MIDDLE_EAST => 'ตะวันออกกลาง',
    ];

    /**
     * Scope: Active countries only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by region
     */
    public function scopeInRegion($query, string $region)
    {
        return $query->where('region', $region);
    }

    /**
     * Get display name (prefer Thai, fallback to English)
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name_th ?: $this->name_en;
    }

    /**
     * Get region label in Thai
     */
    public function getRegionLabelAttribute(): string
    {
        return self::REGIONS[$this->region] ?? $this->region ?? '-';
    }

    /**
     * Relationship: Cities in this country
     */
    public function cities()
    {
        return $this->hasMany(City::class);
    }
}
