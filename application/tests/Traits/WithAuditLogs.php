<?php

namespace Tests\Traits;

use App\Contracts\AuditLogContract;
use Mockery\MockInterface;

trait WithAuditLogs
{
    private MockInterface $auditLogSpy;

    public function setUpWithAuditLogs(): void
    {
        $this->auditLogSpy = $this->spy(AuditLogContract::class);
    }

    public function assertLog(
        string  $action,
        int|false|null    $user_id = false,
        int|false|null    $admin_id = false,
        int|false|null    $cashbox_id = false,
        false|string|null$description = false,
        array|false  $parameters = false,
        int $times = 1,
    )
    {
        $this->auditLogSpy->shouldHaveReceived("log")
            ->withArgs(function (
                string  $_action,
                int|false|null    $_user_id = false,
                int|false|null    $_admin_id = false,
                int|false|null    $_cashbox_id = false,
                false|string|null $_description = false,
                array|false   $_parameters = false
            ) use ($action, $user_id, $admin_id, $cashbox_id, $description, $parameters){
                return
                    $action === $_action
                    && ($user_id === false ||$user_id === $_user_id)
                    && ($admin_id === false || $admin_id === $_admin_id)
                    && ($cashbox_id ===false || $cashbox_id === $_cashbox_id)
                    && ($description === false || $description === $_description)
                    && ($parameters === false || $parameters === $_parameters);
            })
            ->times($times);
    }
}
