<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    protected $fillable = [
        'reference',
        'customer_id',
        'title',
        'service_type',
        'event_date',
        'start_time',
        'end_time',
        'location',
        'guest_count',
        'status',
        'quoted_amount',
        'deposit_amount',
        'notes',
    ];

    protected $casts = [
        'event_date' => 'date',
        'quoted_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'guest_count' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
