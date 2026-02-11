<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'value',
        'icon',
        'url',
        'sort_order',
        'is_active',
        'group',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    const GROUP_CONTACT = 'contact';
    const GROUP_SOCIAL = 'social';
    const GROUP_BUSINESS_HOURS = 'business_hours';

    const GROUPS = [
        self::GROUP_CONTACT => 'ข้อมูลติดต่อ',
        self::GROUP_SOCIAL => 'โซเชียลมีเดีย',
        self::GROUP_BUSINESS_HOURS => 'เวลาทำการ',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
