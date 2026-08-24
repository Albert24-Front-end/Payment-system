<?php

namespace App\Services;

use App\Contracts\AuditLogContract;
use App\Models\AuditLog;

class OwnDBAuditLogService implements AuditLogContract
{

    public function log(string $action, ?int $user_id = null, ?int $admin_id = null, ?int $cashbox_id = null, ?string $description = null, array $parameters = []): void
    {
        AuditLog::create([
            "action" => $action,
            "admin_id" => $admin_id,
            "cashbox_id" => $cashbox_id,
            "user_id" => $user_id,
            "description" => $description,
            "parameters" => $parameters
        ]);
    }
}
