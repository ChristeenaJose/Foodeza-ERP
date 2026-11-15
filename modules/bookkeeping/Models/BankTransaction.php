<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'date', 'description', 'amount', 'reference',
        'matched_income_id', 'matched_expense_id', 'imported_at',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'imported_at' => 'datetime',
    ];
}
