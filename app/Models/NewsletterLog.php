<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterLog extends Model
{
    protected $fillable = [
        'newsletter_id',
        'subscriber_id',
        'status',
        'error_message',
        'sent_at',
        'opened_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
    ];

    public function newsletter()
    {
        return $this->belongsTo(Newsletter::class);
    }

    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class);
    }
}
