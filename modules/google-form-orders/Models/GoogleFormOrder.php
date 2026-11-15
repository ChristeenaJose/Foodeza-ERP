<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleFormOrder extends Model
{
    protected $fillable = [
        'reference',
        'customer_id',
        'submitted_on',
        'event_date',
        'service_type',
        'items',
        'guest_count',
        'location',
        'status',
        'form_response_id',
        'notes',
    ];

    protected $casts = [
        'submitted_on' => 'date',
        'event_date' => 'date',
        'guest_count' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
