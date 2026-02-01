<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'name_en',
        'name_th',
        'slug',
        'country_id',
        'description',
        'is_popular',
        'is_active',
    ];

    protected $casts = [
        'is_popular' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the country that owns the city.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the airports in the city.
     */
    public function airports(): HasMany
    {
        return $this->hasMany(Airport::class);
    }

    /**
     * Scope a query to only include active cities.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include popular cities.
     */
    public function scopePopular($query)
    {
        return $query->where('is_popular', true);
    }

    /**
     * Scope a query to filter by country.
     */
    public function scopeInCountry($query, $countryId)
    {
        return $query->where('country_id', $countryId);
    }
}
