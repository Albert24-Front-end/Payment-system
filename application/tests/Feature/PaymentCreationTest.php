<?php

namespace Tests\Feature;

use App\Contracts\SignatureContract;
use App\Models\Terminal;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\WithAuditLogs;

class PaymentCreationTest extends TestCase
{
    use RefreshDatabase, WithAuditLogs, WithFaker;
    private User $user;
    private Terminal $terminal;

    // Перед каждым тестом будет вызываться setUp() для создания юзера и кассы
    public function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->terminal = Terminal::factory()->state(["user_id" => $this->user->id])->create();
    }
    public function testCreatePayment(): void
    {
        // на время теста изолируем этот тест от работы сервиса подписи - он всегда возвращает нам true, подпись валидна
        $this->mock(
            SignatureContract::class,
            function (MockInterface $mock) {
                $mock->shouldReceive('checkSignature')->andReturn(true);
            }
        );

        $response = $this->post("/api/create-payment", [
            "terminal_id" => $this->terminal->id,
            "amount" => 10000,
            "description" => "Test payment",
            "order_id" => "1",
        ], [
            "X-Signature" => "test-signature"
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(["data" => ["url"]]); // в ключе data должен быть url редиректа от мерчанта
        $this->assertDatabaseHas("payments", [
            "terminal_id" => $this->terminal->id,
            "amount" => 10000,
            "description" => "Test payment",
            "order_id" => "1",
            "status" => 0
        ]);

        $this->assertLog("payment_created", null, null, terminal_id: $this->terminal->id, parameters: ["order_id" => "1"]);
    }

    public function testInvalidSignature(): void
    {
        $this->mock(
            SignatureContract::class,
            function (MockInterface $mock) {
                $mock->shouldReceive('checkSignature')->andReturn(false);
            }
        );

        $response = $this->post("/api/create-payment", [
            "terminal_id" => $this->terminal->id,
            "amount" => 10000,
            "description" => "Test payment",
            "order_id" => "1",
        ], [
            "X-Signature" => "bad signature"
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath("message","Invalid signature");
    }

    public function testMissingData(): void
    {
        $response = $this->post("/api/create-payment", [
            "terminal_id" => null,
            "amount" => null,
            "description" => "",
            "order_id" => "",
        ], [
            "X-Signature" => "good signature"
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(["terminal_id", "amount", "description", "order_id"]);
    }

    public function testInvalidAmount(): void
    {
        $response = $this->post("/api/create-payment", [
            "terminal_id" => $this->terminal->id,
            "amount" => 0,
            "description" => "Test payment",
            "order_id" => "1",
        ], [
            "X-Signature" => "good signature"
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors("amount");

        $response = $this->post("/api/create-payment", [
            "terminal_id" => $this->terminal->id,
            "amount" => 1.5,
            "description" => "Test payment",
            "order_id" => "1",
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors("amount");
    }

    public function testNotUniqueOrderId(): void
    {
        $payment = Payment::factory()->state(["terminal_id" => $this->terminal->id])->create();
        $response = $this->post("/api/create-payment", [
            "terminal_id" => $this->terminal->id,
            "amount" => 10000,
            "description" => "Test payment",
            "order_id" => $payment->order_id,
        ], [
            "X-Signature" => "good signature"
        ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors("order_id");

    }
}
