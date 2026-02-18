<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'admin_notes',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    const STATUS_NEW = 'new';
    const STATUS_READ = 'read';
    const STATUS_REPLIED = 'replied';
    const STATUS_ARCHIVED = 'archived';

    const STATUSES = [
        self::STATUS_NEW => 'ใหม่',
        self::STATUS_READ => 'อ่านแล้ว',
        self::STATUS_REPLIED => 'ตอบแล้ว',
        self::STATUS_ARCHIVED => 'จัดเก็บ',
    ];

    public function scopeNew($query)
    {
        return $query->where('status', self::STATUS_NEW);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function markAsRead(): void
    {
        if (!$this->read_at) {
            $this->update([
                'read_at' => now(),
                'status' => $this->status === self::STATUS_NEW ? self::STATUS_READ : $this->status,
            ]);
        }
    }
}
