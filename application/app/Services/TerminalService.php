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
        readonly private AuditLogContract $terminalAuditLogService
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
        ]); // массовое присваивание для fillable полей
        $terminal->secret_key = Str::random(20); // не fillable поле, к-е невозможно случайно стереть в запросах
        $terminal->save();
        $this->terminalAuditLogService->log("terminal_created", $creator->id, terminal_id: $terminal->id);
    }

    public function getUserTerminals(User $user)
    {
        return Terminal::where("user_id", $user->id)
            ->orderByDesc("created_at") // сортировка по убыванию даты создания - ее заранее подготовили. Для PostgreSQL это необходимо, у него дефолтная сортировка кривая - по дате последнего изменения
            ->get()
            ->toResourceCollection();
    }

    public function getTerminalSecretKey(Terminal $terminal)
    {
        return $terminal->secret_key;
    }

    public function updateTerminal(User $user, Terminal $terminal, TerminalData $terminalData)
    {
        $terminal->fill((array) $terminalData);
        $terminal->save();
        $this->terminalAuditLogService->log("terminal_updated", $user->id, terminal_id: $terminal->id, parameters: (array) $terminalData);
    }

    public function deleteTerminal(User $user, Terminal $terminal)
    {
        $terminal->delete();
        $this->terminalAuditLogService->log("terminal_deleted", $user->id, terminal_id: $terminal->id);
    }
}
