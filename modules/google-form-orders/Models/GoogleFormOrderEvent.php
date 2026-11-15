<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoogleFormOrderEvent extends Model
{
    protected $fillable = [
        'name',
        'location',
        'event_date',
        'start_time',
        'end_time',
        'confirmation_template',
        'notes',
    ];

    protected $casts = [
        'event_date' => 'date',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(GoogleFormOrder::class, 'event_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(GoogleFormOrderUpdate::class, 'event_id')->orderByDesc('id');
    }

    public function timeRangeLabel(): string
    {
        return trim(($this->start_time ?? '').'–'.($this->end_time ?? ''), '–');
    }

    public static function defaultConfirmationTemplate(): string
    {
        return 'Hi {name}, your order placed, please collect {date} {time} {location}';
    }

    public function renderMessage(string $template, GoogleFormOrder $order): string
    {
        $customerName = $order->customer?->name ?: 'there';
        $date = optional($this->event_date)->format('d-m-Y') ?: '';
        $time = $this->timeRangeLabel();
        $location = $this->location ?: ($order->location ?: '');

        return strtr($template, [
            '{name}' => $customerName,
            '{date}' => $date,
            '{time}' => $time,
            '{location}' => $location,
            '{items}' => (string) $order->items,
            '{reference}' => (string) $order->reference,
            '{event}' => (string) $this->name,
        ]);
    }
}
