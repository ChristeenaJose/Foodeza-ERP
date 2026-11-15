<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnamEntry extends Model
{
    protected $fillable = [
        'date', 'type', 'partner_id', 'paid_by', 'amount', 'vat_amount', 'vat_mode',
        'description', 'proof_type', 'receipt_attachment_id', 'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(OnamPartner::class, 'partner_id');
    }

    public function receiptAttachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'receipt_attachment_id');
    }
}
