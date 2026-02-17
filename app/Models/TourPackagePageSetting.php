<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TourPackagePageSetting extends Model
{
    protected $table = 'tour_package_page_settings';

    protected $fillable = [
        'cover_image_url',
        'cover_image_cf_id',
        'cover_image_position',
    ];

    /**
     * Get the single settings row (create if not exists)
     */
    public static function getSettings(): self
    {
        $setting = static::first();
        if (!$setting) {
            $setting = static::create([
                'cover_image_position' => 'center',
            ]);
        }
        return $setting;
    }
}
