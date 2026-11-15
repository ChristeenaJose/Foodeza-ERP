<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MenuLocation extends Model
{
    protected $fillable = ['name', 'slug', 'notes', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'menu_location');
    }
}
