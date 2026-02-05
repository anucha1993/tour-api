<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberBillingAddress extends Model
{
    use HasFactory;

    protected $table = 'member_billing_addresses';

    protected $fillable = [
        'member_id',
        'type',
        'is_default',
        'name',
        'company_name',
        'tax_id',
        'branch_name',
        'address',
        'sub_district',
        'district',
        'province',
        'postal_code',
        'phone',
        'email',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Get the member that owns this billing address
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(WebMember::class, 'member_id');
    }
}
