<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AboutService extends Model
{
    protected $table = 'about_services';

    protected $fillable = [
        'title', 'description', 'icon', 'sort_order', 'is_active',
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
