<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AboutAssociation extends Model
{
    protected $table = 'about_associations';

    protected $fillable = [
        'name', 'license_no', 'logo_url', 'logo_cf_id', 'website_url',
        'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
