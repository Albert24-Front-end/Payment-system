<?php

namespace App\Contracts;

interface AuditLogContract
{
    public function log(
        string $action, // далее идут атрибуты этого действия
        ?int $user_id = null,
        ?int $admin_id = null,
        ?int $terminal_id = null,
        ?string $description = null,
        array $parameters = []
    ): void;
}
