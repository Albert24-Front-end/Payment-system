<?php

namespace App\Services;

use App\Contracts\AuditLogContract;
use App\Models\AuditLog;

class OwnDBAuditLogService implements AuditLogContract
{

    public function log(string $action, ?int $user_id = null, ?int $admin_id = null, ?int $terminal_id = null, ?string $description = null, array $parameters = []): void
    {
        // create здесь выполняет сразу 3 действия - создание таблицы в БД, запись полей (все они fillable) в нее, сохранение внесенных в таблицу данных
        AuditLog::create([
            "action" => $action,
            "user_id" => $user_id,
            "admin_id" => $admin_id,
            "terminal_id" => $terminal_id,
            "description" => $description,
            "parameters" => $parameters,
        ]);
    }
}
