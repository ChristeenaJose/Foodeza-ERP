<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnamPartner extends Model
{
    protected $fillable = [
        'name', 'share_percent', 'notes', 'active',
    ];

    protected $casts = [
        'share_percent' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(OnamEntry::class, 'partner_id');
    }
}
