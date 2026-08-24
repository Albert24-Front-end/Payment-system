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

        $auditLog->log("test-action", 12, 13, 14, "descr", ["a" => "b"]);

        $this->assertLog("test-action", 12, 13, 14, "descr", ["a" => "b"]);

    }

    public function testNotFullCall()
    {
        $auditLog = app(AuditLogContract::class);

        $auditLog->log("test-action", 12, );

        $this->assertLog("test-action", 12 );

    }
}
