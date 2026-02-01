<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourItinerary extends Model
{
    protected $fillable = [
        'tour_id',
        'external_id',
        'data_source',
        'day_number',
        'title',
        'description',
        'places',
        'has_breakfast',
        'has_lunch',
        'has_dinner',
        'meals_note',
        'accommodation',
        'hotel_star',
        'images',
        'sort_order',
    ];

    protected $casts = [
        'places' => 'array',
        'images' => 'array',
        'has_breakfast' => 'boolean',
        'has_lunch' => 'boolean',
        'has_dinner' => 'boolean',
        'day_number' => 'integer',
        'hotel_star' => 'integer',
        'sort_order' => 'integer',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    // Helper methods
    public function getMealsAttribute(): array
    {
        $meals = [];
        if ($this->has_breakfast) $meals[] = 'breakfast';
        if ($this->has_lunch) $meals[] = 'lunch';
        if ($this->has_dinner) $meals[] = 'dinner';
        return $meals;
    }

    public function getMealsCountAttribute(): int
    {
        return (int)$this->has_breakfast + (int)$this->has_lunch + (int)$this->has_dinner;
    }

    public function getMealsTextAttribute(): string
    {
        $meals = [];
        if ($this->has_breakfast) $meals[] = 'เช้า';
        if ($this->has_lunch) $meals[] = 'กลางวัน';
        if ($this->has_dinner) $meals[] = 'เย็น';
        return implode(', ', $meals) ?: '-';
    }
}
