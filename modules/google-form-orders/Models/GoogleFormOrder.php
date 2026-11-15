<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleFormOrder extends Model
{
    protected $fillable = [
        'event_id',
        'reference',
        'customer_id',
        'submitted_on',
        'event_date',
        'service_type',
        'items',
        'guest_count',
        'location',
        'whatsapp_phone',
        'status',
        'form_response_id',
        'raw_payload',
        'confirmation_sent_at',
        'notes',
    ];

    protected $casts = [
        'submitted_on' => 'date',
        'event_date' => 'date',
        'guest_count' => 'integer',
        'raw_payload' => 'array',
        'confirmation_sent_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(GoogleFormOrderEvent::class, 'event_id');
    }
}
