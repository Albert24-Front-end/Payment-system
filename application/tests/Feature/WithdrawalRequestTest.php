<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Terminal;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\WithAuditLogs;

class WithdrawalRequestTest extends TestCase
{
    use RefreshDatabase, WithFaker, WithAuditLogs;

    private User $user;
    private Terminal $terminal;
    public function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->terminal = Terminal::factory()->state(["user_id" => $this->user->id])->create();
        Payment::factory()->state([
            "terminal_id" => $this->terminal->id,
            "status" => Payment::STATUS_PAID,
            "amount" => 100_000_000,
        ])->createMany(3);
        // для каждого теста в этом файле создаем юзера, кассу для него и несколько платежей по этой кассе
    }

    public function testSuccessfulWithdrawalRequest(): void
    {
        $this->actingAs($this->user);
        $response = $this->post("/api/withdrawals", [
            "terminal_id" => $this->terminal->id,
            "amount" => 100_000_000,
            "bank_code" => "12345678",
            "account_number" => "1234567890"
        ], [
            "Idempotence-Key" => \Str::uuid(),
        ]);
        $response->assertStatus(200);

        $response->assertJsonStructure(["data" => ["request_id"]]);
        $requestId = $response->json()["data"]["request_id"];

        $this->assertDatabaseHas("withdrawal_requests", [
            "user_id" => $this->user->id,
            "terminal_id" => $this->terminal->id,
            "amount" => 100_000_000,
            "bank_code" => "12345678",
            "account_number" => "1234567890",
            "status" => WithdrawalRequest::STATUS_PENDING,

        ]);
        $this->assertLog(
            "withdrawal_request_created",
            user_id: $this->user->id,
            terminal_id: $this->terminal->id,
            parameters: ["amount" => 100_000_000, "request_id" => $requestId]);
    }

    public function testInvalidWithdrawalRequestCreation(): void
    {
        $this->actingAs($this->user);
        $response = $this->post("/api/withdrawals", [
            "terminal_id" => $this->terminal->id,
            "amount" => 0,
            "bank_code" => "",
            "account_number" => ""
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["amount", "bank_code", "account_number"]);
        $this->assertDatabaseCount("withdrawal_requests", 0);
    }

    public function testTerminalIdIsRequired(): void
    {
        $payload = $this->validPayload();
        unset($payload["terminal_id"]);

        $this->actingAs($this->user)
            ->postJson("/api/withdrawals", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(["terminal_id"]);

        $this->assertDatabaseCount("withdrawal_requests", 0);
    }

    public function testNonexistentTerminalIsRejected(): void
    {
        $payload = $this->validPayload();
        $payload["terminal_id"] = 999999;

        $this->actingAs($this->user)
            ->postJson("/api/withdrawals", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(["terminal_id"]);

        $this->assertDatabaseCount("withdrawal_requests", 0);
    }

    public function testTerminalOwnedByAnotherUserIsRejected(): void
    {
        $anotherUser = User::factory()->create();
        $anotherTerminal = Terminal::factory()
            ->state(["user_id" => $anotherUser->id])
            ->create();

        $payload = $this->validPayload();
        $payload["terminal_id"] = $anotherTerminal->id;

        $this->actingAs($this->user)
            ->postJson("/api/withdrawals", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(["terminal_id"]);

        $this->assertDatabaseCount("withdrawal_requests", 0);
    }

    public function testFieldsWithWrongTypesAreRejected(): void
    {
        $this->actingAs($this->user)
            ->postJson("/api/withdrawals", [
                "terminal_id" => [],
                "amount" => "not-an-integer",
                "bank_code" => [],
                "account_number" => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                "terminal_id",
                "amount",
                "bank_code",
                "account_number",
            ]);

        $this->assertDatabaseCount("withdrawal_requests", 0);
    }

    private function validPayload(): array
    {
        return [
            "terminal_id" => $this->terminal->id,
            "amount" => 100_000_000,
            "bank_code" => "12345678",
            "account_number" => "1234567890",
        ];
    }

    public function testWithdrawalRequestIdempotence(): void
    {
        $key = \Str::uuid();
        $this->actingAs($this->user);

        $response = $this->post("/api/withdrawals", [
            "terminal_id" => $this->terminal->id,
            "amount" => 100_000_000,
            "bank_code" => "12345678",
            "account_number" => "1234567890"
        ], [
            "Idempotence-Key" => $key,
        ]);
        $response->assertStatus(200);

        // дублируем запрос с теми же данными и ключом,
        // чтобы протестировать идемпотентность - в ответ фронту добавится маркер duplicated, чтобы он понял, что сделал retry
        $response = $this->post("/api/withdrawals", [
            "terminal_id" => $this->terminal->id,
            "amount" => 100_000_000,
            "bank_code" => "12345678",
            "account_number" => "1234567890"
        ], [
            "Idempotence-Key" => $key,
        ]);
        $response->assertStatus(200);
        $response->assertJsonStructure(["data" => ["request_id"]]); // проверяет структуру, что в JSON data есть request_id - id запроса withdrawal в БД
        $response->assertJsonPath("duplicated", true); // проверяет, что в JSON есть поле duplicated

        // вводим счетчиков запросов и убеждаемся, что проработал с БД только 1-й запрос, а 2-й вернулся фронту с маркером
        $countRequest = WithdrawalRequest::where([
            "user_id" => $this->user->id,
            "terminal_id" => $this->terminal->id,
            "amount" => 100_000_000,
            "bank_code" => "12345678",
            "account_number" => "1234567890"
        ])->count();

        $this->assertEquals(1, $countRequest);
    }

    public function testRequestedSumIsTooBig(): void
    {
        $this->actingAs($this->user);
        $response = $this->post("/api/withdrawals", [
            "terminal_id" => $this->terminal->id,
            "amount" => 500_000_000,
            "bank_code" => "12345678",
            "account_number" => "1234567890"
        ], [
            "Idempotence-Key" => \Str::uuid(),
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors("amount");
        $this->assertDatabaseCount('withdrawal_requests', 0);
    }
}
