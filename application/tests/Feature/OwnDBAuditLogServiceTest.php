<?php

namespace Tests\Feature;

use App\Services\OwnDBAuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class OwnDBAuditLogServiceTest extends TestCase
{
    use RefreshDatabase;
    public function testLog(): void
    {
        // тест именно конкретной реализации - OwnDBAuditLogService
        $log = new OwnDBAuditLogService();
        $log->log("test", 10, 10, 10, "descr", ["a" => "b"]);

        $this->assertDatabaseHas('audit_logs', [
            "action" => "test",
            "user_id" => 10,
            "admin_id" => 10,
            "terminal_id" => 10,
            "description" => "descr",
            "parameters" => $this->castAsJson(["a" => "b"])
        ]);

        // проверяем, что метод нормально работает с неполным набором аргументов
        $log->log("some-action", 13);
        $this->assertDatabaseHas('audit_logs', [
            "action" => "some-action",
            "user_id" => 13,
        ]);

    }
}
