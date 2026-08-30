<?php

namespace Tests\Feature;

use App\Models\Terminal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\WithAuditLogs;

class TerminalTest extends TestCase
{
    use RefreshDatabase, WithAuditLogs;
    public function testTerminalSuccessfulCreation(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post("/api/terminals", [
            "name" => "Test Terminal",
            "success_url" => "https://example.com/success",
            "fail_url" => "https://example.com/fail",
            "webhook_url" => "https://example.com/webhook",
        ]); // вызов теста от указанного юзера

        $response->assertStatus(201);
        $this->assertDatabaseHas("terminals", [
            "name" => "Test Terminal",
            "success_url" => "https://example.com/success",
            "fail_url" => "https://example.com/fail",
            "webhook_url" => "https://example.com/webhook",
        ]);

        $terminal = Terminal::query()
            ->where("user_id", $user->id)
            ->where("name", "Test Terminal")
            ->first();

        $this->assertNotNull($terminal->secret_key);
        $this->assertLog("terminal_created", $user->id, terminal_id: $terminal->id);
    }

    public function testFailedTerminalCreation(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post("/api/terminals", [
            "name" => "",
            "success_url" => "",
            "fail_url" => "",
            "webhook_url" => "",
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["name", "success_url", "fail_url", "webhook_url"]);


        $response = $this->actingAs($user)->post("/api/terminals", [
            "name" => "some name",
            "success_url" => "not-url",
            "fail_url" => "not-url",
            "webhook_url" => "not-url",
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["success_url", "fail_url", "webhook_url"]);

        $terminal = Terminal::factory()->state(["user_id" => $user->id])->create();
        $response = $this->actingAs($user)->post("/api/terminals", [
            "name" => $terminal->name,
            "success_url" => "https://example.com/success",
            "fail_url" => "https://example.com/fail",
            "webhook_url" => "https://example.com/webhook",
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["name"]);
    }
}
