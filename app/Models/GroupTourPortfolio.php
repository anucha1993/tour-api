<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupTourPortfolio extends Model
{
    protected $fillable = [
        'title',
        'caption',
        'group_size',
        'destination',
        'image_url',
        'image_cf_id',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
