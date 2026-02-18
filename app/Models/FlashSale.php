<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlashSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'banner_image_url',
        'start_date',
        'end_date',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
    ];

    // ─── Relationships ───
    public function items()
    {
        return $this->hasMany(FlashSaleItem::class)->orderBy('sort_order');
    }

    // ─── Scopes ───
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRunning($query)
    {
        $now = now();
        return $query->active()
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now);
    }

    public function scopeUpcoming($query)
    {
        return $query->active()
            ->where('start_date', '>', now());
    }

    // ─── Helpers ───
    public function isRunning(): bool
    {
        $now = now();
        return $this->is_active
            && $this->start_date <= $now
            && $this->end_date >= $now;
    }

    public function isUpcoming(): bool
    {
        return $this->is_active && $this->start_date > now();
    }

    public function isExpired(): bool
    {
        return $this->end_date < now();
    }

    public function getStatusLabelAttribute(): string
    {
        if (!$this->is_active) return 'ปิดใช้งาน';
        if ($this->isExpired()) return 'หมดเวลา';
        if ($this->isRunning()) return 'กำลังดำเนินการ';
        if ($this->isUpcoming()) return 'รอเปิด';
        return 'ไม่ทราบ';
    }
}
