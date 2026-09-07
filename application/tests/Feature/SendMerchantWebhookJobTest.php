<?php

namespace Tests\Feature;

use App\Contracts\SignatureContract;
use App\Jobs\SendMerchantWebhook;
use App\Models\Payment;
use App\Models\Terminal;
use App\Models\User;
use App\Services\PaymentProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\WithAuditLogs;

class SendMerchantWebhookJobTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    use RefreshDatabase, WithAuditLogs, WithFaker;

    const string WEBHOOK_TEST_URL = "http://example.com/webhook";
    const string TEST_SIGNATURE = "good signature";

    private User $user;
    private Terminal $terminal;
    private Payment $payment;
    public function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->terminal = Terminal::factory()->state(["user_id" => $this->user->id, "webhook_url" => self::WEBHOOK_TEST_URL])->create();
        $this->payment = Payment::factory()->state(["terminal_id" => $this->terminal->id, "status" => Payment::STATUS_PAID])->create();

        $this->mock(
            SignatureContract::class,
            function (MockInterface $mock) {
                $mock->shouldReceive("sign")->andReturn(self::TEST_SIGNATURE);
            }
        );
    }
    public function testSuccessfulWebhookCall(): void
    {
        Http::fake();
        $service = app(PaymentProcessingService::class);
        $job = new SendMerchantWebhook($this->payment->id);
        $job->handle($service);

        Http::assertSent(function (Request$request) {
            // посылаем мерчанту на проверку соответствия в теле запроса url, id заказа, сумму, статус, подпись (в заголовке)
            return $request->url() === self::WEBHOOK_TEST_URL
                && $request->method() === "POST"
                && $request["order_id"] === $this->payment->order_id
                && $request["amount"] === $this->payment->amount
                && $request["status"] === $this->payment->status
                && $request->hasHeader("X-Signature", self::TEST_SIGNATURE) === true;
        });

        $this->assertLog("to_merchant_webhook_sent", null, null, terminal_id: $this->terminal->id, parameters: [
            "payment_id" => $this->payment->id,
            "status" => $this->payment->status,
            "url" => $this->terminal->webhook_url,
        ]);
    }

    public function testFailedWebhookCall(): void
    {
        $this->freezeTime(); // now() возвращает теперь время на момент начала теста
        Http::fake([
            '*' => Http::failedConnection(),
        ]);
        \Queue::fake();

        $service = app(PaymentProcessingService::class);
        $job = new SendMerchantWebhook($this->payment->id, 3);
        $job->handle($service);
        Queue::assertPushed(SendMerchantWebhook::class, function (SendMerchantWebhook $job) {
            return $job->retry_count === 4 && $job->payment_id === $this->payment->id && $job->delay->diff(now())->i === 4;
        });

        $this->assertLog("webhook_failed_with_retry", null, null, terminal_id: $this->terminal->id, parameters: [
            "payment_id" => $this->payment->id,
            "status" => $this->payment->status,
            "url" => $this->terminal->webhook_url,
        ]);
    }

    public function testFiveFailedWebhookCalls(): void
    {
        $this->freezeTime(); // now() возвращает теперь время на момент начала теста
        Http::fake([
            '*' => Http::failedConnection(),
        ]);
        \Queue::fake();

        $service = app(PaymentProcessingService::class);
        $job = new SendMerchantWebhook($this->payment->id, 5);
        $job->handle($service);
        Queue::assertNotPushed(SendMerchantWebhook::class);

        $this->assertLog("webhook_failed_five_times", null, null, terminal_id: $this->terminal->id, parameters: [
            "payment_id" => $this->payment->id,
            "status" => $this->payment->status,
            "url" => $this->terminal->webhook_url,
        ]);
    }
}
