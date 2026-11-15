<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AvailabilityRule extends Model
{
    protected $fillable = [
        'subject_type', 'subject_id', 'days_of_week',
        'start_time', 'end_time', 'date_from', 'date_to',
        'location_id', 'service_type_id', 'active',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'date_from' => 'date',
        'date_to' => 'date',
        'active' => 'boolean',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(MenuLocation::class, 'location_id');
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(MenuServiceType::class, 'service_type_id');
    }
}
