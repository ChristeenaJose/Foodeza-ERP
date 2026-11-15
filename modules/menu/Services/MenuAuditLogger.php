<?php

namespace App\Services\Menu;

use App\Models\MenuAuditLog;

class MenuAuditLogger
{
    public static function log(string $action, ?string $subjectType = null, ?int $subjectId = null, ?array $old = null, ?array $new = null): void
    {
        MenuAuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'old_values' => $old,
            'new_values' => $new,
        ]);
    }
}
