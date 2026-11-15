<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    protected $fillable = [
        'original_name', 'stored_path', 'mime_type', 'file_size', 'type',
    ];
}
