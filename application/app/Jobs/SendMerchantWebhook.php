<?php

namespace App\Jobs;

use App\Services\PaymentProcessingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendMerchantWebhook implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        // сюда лучше не передавать модели, не факт, что потом удастся синхронизировать их работу. Передаем id модели
        public int $payment_id,
        public int $retry_count = 1,
    )
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(PaymentProcessingService $service): void
    {
        $service->sendStatusWebhook($this->payment_id, $this->retry_count);
    }
}
