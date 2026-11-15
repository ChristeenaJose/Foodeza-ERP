<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'date', 'supplier_id', 'category_id', 'amount', 'vat_amount',
        'description', 'receipt_attachment_id', 'proof_type', 'bank_transaction_id',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function receiptAttachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'receipt_attachment_id');
    }
}
