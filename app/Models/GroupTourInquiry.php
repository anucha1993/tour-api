<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupTourInquiry extends Model
{
    protected $fillable = [
        'name',
        'organization',
        'phone',
        'email',
        'line_id',
        'group_type',
        'group_size',
        'destination',
        'travel_date_start',
        'travel_date_end',
        'details',
        'status',
        'admin_notes',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'travel_date_start' => 'date',
        'travel_date_end' => 'date',
    ];
}
