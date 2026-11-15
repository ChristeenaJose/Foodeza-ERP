<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MenuServiceType extends Model
{
    protected $fillable = ['name', 'slug', 'sort_order', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'menu_service_type');
    }
}
