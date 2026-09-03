<?php

namespace Tests\Feature;

use App\Models\Terminal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
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

        // тест на неправильные url
        $response = $this->actingAs($user)->post("/api/terminals", [
            "name" => "some name",
            "success_url" => "not-url",
            "fail_url" => "not-url",
            "webhook_url" => "not-url",
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["success_url", "fail_url", "webhook_url"]);

        // тест на неуникальное имя
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

    public function testTerminalList(): void
    {
        $user = User::factory()->create();
        $terminals = Terminal::factory()->
            count(3)->
            state(new Sequence(["user_id" => $user->id, "created_at" => now()],
            ["user_id" => $user->id, "created_at" => now()->subMinute()],
            ["user_id" => $user->id, "created_at" => now()->subMinutes(3)]))->
            create(); // сортируем кассы по убыванию времени создания, самые свежие - вперед

        // чужие кассы не возвращаются нам - не отфильтрованный юзер уронит тест, т.к. для него создается новая касса
        $otherUser = User::factory()->create();
        Terminal::factory()->state(["user_id" => $otherUser->id])->create();

        $response = $this->actingAs($user)->get("/api/terminals");
        $response->assertOk();

        // готовим данные в виде коллекций
        $data = $terminals->map(function(Terminal $terminal) {
            $terminalData = $terminal->toArray();
            unset($terminalData["secret_key"]);
            return $terminalData;
        })->toArray();
        $response->assertJson(["data" => $data]);
        $response->assertJsonMissingPath("data.0.secret_key");
    }

    public function testUpdateTerminal(): void
    {
        $user = User::factory()->create();
        $terminal = Terminal::factory()->state(["user_id" => $user->id])->create();
        $newData = [
            "name" => "Updated Terminal",
            "success_url" => "https://example.com/success",
            "fail_url" => "https://example.com/fail",
            "webhook_url" => "https://example.com/webhook",
        ];
        $response = $this->actingAs($user)->put("/api/terminals/{$terminal->id}", $newData);
        $response->assertOk();
        $terminal->refresh();
        // сопоставляем данные, чтобы удостовериться в успешном редактировании
        $this->assertEquals("Updated Terminal", $terminal->name);
        $this->assertEquals("https://example.com/success", $terminal->success_url);
        $this->assertEquals("https://example.com/fail", $terminal->fail_url);
        $this->assertEquals("https://example.com/webhook", $terminal->webhook_url);

        $this->assertLog("terminal_updated", $user->id, terminal_id: $terminal->id, parameters: (array) $newData);

        // запрет на редактирование чужих касс
        $otherUser = User::factory()->create();
        $otherTerminal = Terminal::factory()->state(["user_id" => $otherUser->id])->create();
        $response = $this->actingAs($user)->put("/api/terminals/{$otherTerminal->id}", $newData);
        $response->assertForbidden();
    }

    public function testDeleteTerminal(): void
    {
        $user = User::factory()->create();
        $terminal = Terminal::factory()->state(["user_id" => $user->id])->create();

        $response = $this->actingAs($user)->delete("/api/terminals/{$terminal->id}");
        $response->assertOk();
        $this->assertSoftDeleted("terminals", ["id" => $terminal->id]); // мягкое удаление
//        $terminal->refresh();

        $this->assertLog("terminal_deleted", $user->id, terminal_id: $terminal->id);

        // запрет на удаление чужих касс
        $otherUser = User::factory()->create();
        $otherTerminal = Terminal::factory()->state(["user_id" => $otherUser->id])->create();
        $response = $this->actingAs($user)->delete("/api/terminals/{$otherTerminal->id}");
        $response->assertForbidden();
    }
}
