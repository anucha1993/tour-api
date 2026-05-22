<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingPassenger extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'type',
        'title',
        'first_name',
        'last_name',
        'first_name_th',
        'last_name_th',
        'dob',
        'gender',
        'passport_no',
        'nationality',
        'passport_expiry',
        'passport_issue_date',
        'passport_issue_country',
        'phone',
        'email',
        'special_request',
        'is_lead',
        'room_type',
        'room_index',
    ];

    protected $casts = [
        'dob' => 'date',
        'passport_expiry' => 'date',
        'passport_issue_date' => 'date',
        'is_lead' => 'boolean',
        'room_index' => 'integer',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->title ? $this->title . ' ' : '') . $this->first_name . ' ' . $this->last_name);
    }
}
