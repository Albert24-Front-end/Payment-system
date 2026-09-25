<?php

namespace Tests\Traits;

use App\Models\Payment;
use App\Models\Terminal;
use Illuminate\Support\Str;

trait CreatesStatisticsPayments
{
    private function payment(Terminal $terminal, int $amount, string $date, int $status = Payment::STATUS_PAID): Payment
    {
        return Payment::factory()->create([
            'terminal_id' => $terminal->id,
            'order_id' => (string) Str::uuid(),
            'amount' => $amount,
            'status' => $status,
            'created_at' => $date,
            'updated_at' => $date,
        ]);
    }
}
