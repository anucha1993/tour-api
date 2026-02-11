<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OurClient extends Model
{
    use HasFactory;

    protected $table = 'our_clients';

    protected $fillable = [
        'cloudflare_id',
        'url',
        'thumbnail_url',
        'filename',
        'name',
        'alt',
        'description',
        'website_url',
        'width',
        'height',
        'file_size',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'width' => 'integer',
        'height' => 'integer',
        'file_size' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Scope: Active clients only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Ordered by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }

    /**
     * Get active clients for website display
     */
    public static function getActiveClients(): \Illuminate\Support\Collection
    {
        return static::active()
            ->ordered()
            ->get();
    }
}
