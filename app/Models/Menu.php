<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use HasFactory;

    protected $fillable = [
        'location',
        'title',
        'url',
        'target',
        'icon',
        'parent_id',
        'sort_order',
        'is_active',
        'css_class',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'parent_id' => 'integer',
    ];

    // Locations
    const LOCATION_HEADER = 'header';
    const LOCATION_FOOTER_COL1 = 'footer_col1';
    const LOCATION_FOOTER_COL2 = 'footer_col2';
    const LOCATION_FOOTER_COL3 = 'footer_col3';

    const LOCATIONS = [
        self::LOCATION_HEADER => 'เมนู Header',
        self::LOCATION_FOOTER_COL1 => 'Footer คอลัมน์ 1',
        self::LOCATION_FOOTER_COL2 => 'Footer คอลัมน์ 2',
        self::LOCATION_FOOTER_COL3 => 'Footer คอลัมน์ 3',
    ];

    // Relationships
    public function parent()
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    public function allChildren()
    {
        return $this->hasMany(Menu::class, 'parent_id')
            ->orderBy('sort_order');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRootItems($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeByLocation($query, string $location)
    {
        return $query->where('location', $location);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
