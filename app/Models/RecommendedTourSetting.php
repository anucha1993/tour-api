<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecommendedTourSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'display_mode',
        'title',
        'subtitle',
        'is_active',
        'cache_minutes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'cache_minutes' => 'integer',
    ];

    const DISPLAY_MODES = [
        'ordered' => 'เรียงตามลำดับ (แสดง section ลำดับแรก)',
        'random' => 'สุ่ม (สุ่มเลือก 1 section)',
        'weighted_random' => 'สุ่มแบบถ่วงน้ำหนัก (ตาม weight)',
        'schedule' => 'ตามกำหนดเวลา (schedule)',
    ];

    /**
     * Get or create the singleton settings row
     */
    public static function getSettings(): self
    {
        return static::firstOrCreate([], [
            'display_mode' => 'ordered',
            'title' => 'ทัวร์แนะนำ',
            'is_active' => true,
            'cache_minutes' => 60,
        ]);
    }
}
