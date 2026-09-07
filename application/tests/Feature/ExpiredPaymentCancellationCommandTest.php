<?php

namespace Tests\Feature;

use App\Console\Commands\CancelExpiredPayments;
use App\Jobs\SendMerchantWebhook;
use App\Models\Payment;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\WithAuditLogs;

class ExpiredPaymentCancellationCommandTest extends TestCase
{
    use RefreshDatabase, WithAuditLogs;
    /**
     * A basic feature test example.
     */
    public function testCancelCommands(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $terminal = Terminal::factory()->state(["user_id" => $user->id])->create();
        $payment = Payment::factory()->state(["terminal_id" => $terminal->id, "created_at" => now()->subMinutes(30), ])->create();

        $this->artisan("app:cancel-expired-payments");
        $payment->refresh();

        $this->assertEquals(Payment::STATUS_FAILED, $payment->status);
        Queue::assertPushed(SendMerchantWebhook::class, function (SendMerchantWebhook $job) use ($payment) {
            return $job->payment_id === $payment->id;
        });

        $this->assertLog("expired_payment_canceled", null, null, $terminal->id, parameters: ["payment_id" => $payment->id]);
    }
}
