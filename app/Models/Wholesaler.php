<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Wholesaler extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        // ข้อมูลพื้นฐาน
        'code',
        'name',
        'logo_url',
        'website',
        'is_active',
        'notes',
        
        // ข้อมูลติดต่อ
        'contact_name',
        'contact_email',
        'contact_phone',
        
        // ข้อมูลใบกำกับภาษี
        'tax_id',
        'company_name_th',
        'company_name_en',
        'branch_code',
        'branch_name',
        'address',
        'phone',
        'fax',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get active wholesalers only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get tours for this wholesaler.
     */
    public function tours()
    {
        return $this->hasMany(Tour::class, 'wholesaler_id');
    }

    /**
     * Get API configs for this wholesaler.
     */
    public function apiConfigs()
    {
        return $this->hasMany(WholesalerApiConfig::class, 'wholesaler_id');
    }

    /**
     * Find wholesaler by code.
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }
}
