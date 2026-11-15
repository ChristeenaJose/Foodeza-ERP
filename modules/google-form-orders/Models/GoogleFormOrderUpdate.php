<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleFormOrderUpdate extends Model
{
    protected $fillable = [
        'event_id',
        'title',
        'message',
        'last_sent_at',
    ];

    protected $casts = [
        'last_sent_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(GoogleFormOrderEvent::class, 'event_id');
    }
}
