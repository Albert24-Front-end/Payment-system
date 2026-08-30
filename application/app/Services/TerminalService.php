<?php

namespace App\Services;

use App\Contracts\AuditLogContract;
use App\Data\Terminals\TerminalData;
use App\Models\AuditLog;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Support\Str;

class TerminalService
{
    public function __construct(
        // Dependency Inversion Principle из SOLID
        readonly private AuditLogContract $terminalService
    )
    {
    }

    public function createAuditLog(User $creator, TerminalData $terminalData)
    {
        $terminal = new Terminal([
            "name" => $terminalData->name,
            "user_id" => $creator->id,
            "success_url" => $terminalData->success_url,
            "fail_url" => $terminalData->fail_url,
            "webhook_url" => $terminalData->webhook_url,
        ]);
        $terminal->secret_key = Str::random(20); // не fillable поле, к-е невозможно случайно стереть в запросах
        $terminal->save();
        $this->terminalService->log("terminal_created", $creator->id, terminal_id: $terminal->id);
    }
}
