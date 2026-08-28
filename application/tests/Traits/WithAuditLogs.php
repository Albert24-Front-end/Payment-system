<?php

namespace Tests\Traits;
// трейт-помощник для запуска тестов без обращения к БД при вызове каждого метода и его записи в логе создает мок
// - заглушку для реализации класса/контракта. Мок позволяет тестировать методы без изменения БД, обращений к ней.

use App\Contracts\AuditLogContract;
use Mockery\MockInterface;

trait WithAuditLogs
{
    // это шпион - облегченный мок, к-й не работает с рез-ми ожидаемого вызова AuditLog
    private MockInterface $auditLogSpy;

    public function setUpWithAuditLogs(): void
    {
        $this->auditLogSpy = $this->spy(AuditLogContract::class);
    }

    // метод assertLog, проверяющий факт добавления AuditLog ко всем методам без его внесения в БД
    public function assertLog(
        string  $action,
        int|false|null    $user_id = false,
        int|false|null    $admin_id = false,
        int|false|null    $terminal_id = false,
        false|string|null $description = false,
        array|false  $parameters = false,
        int $times = 1,
    )
    {   // был ли вызов метода log с параметрами из return
        $this->auditLogSpy->shouldHaveReceived('log')->withArgs(function(
            string  $_action,
            int|false|null    $_user_id = false,
            int|false|null    $_admin_id = false,
            int|false|null    $_terminal_id = false,
            false|string|null $_description = false,
            array|false   $_parameters = false
        ) use($action, $user_id, $admin_id, $terminal_id, $description, $parameters) {
            return
                $action === $_action
                && ($user_id === false ||$user_id === $_user_id)
                && ($admin_id === false || $admin_id === $_admin_id)
                && ($terminal_id ===false || $terminal_id === $_terminal_id)
                && ($description === false || $description === $_description)
                && ($parameters === false || $parameters === $_parameters);
        })->times($times);
    }
}
