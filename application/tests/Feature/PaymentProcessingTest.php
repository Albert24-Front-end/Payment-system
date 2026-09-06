<?php

namespace Tests\Feature;

use App\Jobs\SendMerchantWebhook;
use App\Models\Payment;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\WithAuditLogs;

class PaymentProcessingTest extends TestCase
{
    use RefreshDatabase, WithAuditLogs, WithFaker;
    private User $user;
    private Terminal $terminal;
    public function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->terminal = Terminal::factory()->state(["user_id" => $this->user->id])->create();
    }

    public function testPaymentSuccess(): void
    {
        \Queue::fake();
        $payment = Payment::factory()->create(["terminal_id" => $this->terminal->id, "status" => Payment::STATUS_PENDING]);

        $response = $this->post("/api/payments/{$payment->id}/change-status", [ // id - ключ идемпотентности
            "status" => Payment::STATUS_PAID,
        ]);

        // ожидаем, что url обновит статус платежа в БД
        $response->assertStatus(200);
        $this->assertDatabaseHas("payments", [
            "id" => $payment->id,
            "status" => Payment::STATUS_PAID,
        ]);
        // ожидаем, что url поставит задачу оповещения мерчанта об успехе платежа в очередь
        \Queue::assertPushed(SendMerchantWebhook::class, function (SendMerchantWebhook $job) use ($payment) {
            return $payment->id === $job->payment_id; // проверяем, что задача поставлена на обработку пришедшего по url платежа
        });

        $this->assertLog("payment_status_changed", null, null, $this->terminal->id, parameters: [
            "payment_id" => $payment->id,
            "status" => Payment::STATUS_PAID,
        ]);
    }

    public function testPaymentProcessingIdempotence(): void
    {
        // тест на идемпотентность - повторный вызов обработки обработанного платежа не добавляет новую задачу job в очередь queue
        \Queue::fake();
        $payment = Payment::factory()->create(["terminal_id" => $this->terminal->id, "status" => Payment::STATUS_PAID]);
        $response = $this->post("/api/payments/{$payment->id}/change-status", [
            "status" => Payment::STATUS_PAID,
        ]);
        $response->assertStatus(200);
        \Queue::assertNotPushed(SendMerchantWebhook::class);
    }
}
