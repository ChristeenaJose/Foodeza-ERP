<?php

namespace App\Support;

class RecordDates
{
    public static function minDate(): string
    {
        return config('foodeza.records_start_date', '2025-01-01');
    }

    public static function dateRules(): array
    {
        return ['required', 'date', 'after_or_equal:'.self::minDate()];
    }

    public static function optionalDateRules(): array
    {
        return ['nullable', 'date', 'after_or_equal:'.self::minDate()];
    }
}
