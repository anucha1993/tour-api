<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'quotation_number',
        'web_member_id',
        'tour_id',
        'period_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'pax_adult',
        'pax_child',
        'pax_infant',
        'travel_date_preference',
        'notes',
        'title',
        'description',
        'items',
        'subtotal',
        'discount',
        'total_amount',
        'valid_until',
        'admin_notes',
        'status',
        'sent_at',
        'accepted_at',
        'declined_at',
        'decline_reason',
        'converted_booking_id',
        'handled_by_user_id',
    ];

    protected $casts = [
        'items' => 'array',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'valid_until' => 'date',
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'pax_adult' => 'integer',
        'pax_child' => 'integer',
        'pax_infant' => 'integer',
    ];

    public static function generateNumber(): string
    {
        $prefix = 'QT' . date('Ymd');
        $last = self::where('quotation_number', 'like', $prefix . '%')
            ->orderBy('quotation_number', 'desc')
            ->value('quotation_number');
        $seq = 1;
        if ($last) {
            $seq = (int) substr($last, -4) + 1;
        }
        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(WebMember::class, 'web_member_id');
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_user_id');
    }
}
