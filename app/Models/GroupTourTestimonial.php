<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupTourTestimonial extends Model
{
    protected $fillable = [
        'company_name',
        'reviewer_name',
        'reviewer_position',
        'logo_url',
        'logo_cf_id',
        'content',
        'rating',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'rating' => 'integer',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
