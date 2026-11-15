<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileStorageService
{
    public function store(UploadedFile $file, string $type): Attachment
    {
        $subdir = $type === 'receipt' ? 'receipts' : 'invoices';
        $name = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($subdir, $name, 'local');

        return Attachment::create([
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'type' => $type,
        ]);
    }

    public function fullPath(Attachment $attachment): string
    {
        return Storage::disk('local')->path($attachment->stored_path);
    }
}
