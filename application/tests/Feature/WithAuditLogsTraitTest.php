<?php

namespace Tests\Feature;

use App\Contracts\AuditLogContract;
use Tests\TestCase;
use Tests\Traits\WithAuditLogs;

class WithAuditLogsTraitTest extends TestCase
{
    use WithAuditLogs;
    public function testBasic()
    {
        $auditLog = app(AuditLogContract::class);

        $auditLog->log("test-action", 10, 10, 10, "descr", ["a" => "b"]);

        $this->assertLog("test-action", 10, 10, 10, "descr", ["a" => "b"]);
    }

    public function testNotFullCall()
    {
        $auditLog = app(AuditLogContract::class);

        $auditLog->log("test-action", 10);

        $this->assertLog("test-action", 10);
    }
}
