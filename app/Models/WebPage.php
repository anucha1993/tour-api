<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebPage extends Model
{
    protected $fillable = [
        'key',
        'title',
        'content',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
