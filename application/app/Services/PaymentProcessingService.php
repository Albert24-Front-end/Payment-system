<?php

namespace App\Services;

use App\Contracts\AuditLogContract;
use App\Jobs\SendMerchantWebhook;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class PaymentProcessingService
{
    public function __construct(
        readonly private AuditLogContract $auditLogService
    )
    {}

    public function changePaymentStatus(int $paymentId, int $status): int
    {
        // берем транзакцию, чтобы проверить статус, ключ идемп-ти - paymentId
        return DB::transaction(function () use ($paymentId, $status) {
            // select * from payments where id = $paymentId for update
            // блокируем БД от двух одновременных запросов в рамках транзакции
            $payment = Payment::lockForUpdate()->findOrFail($paymentId);
            if ($payment->status !== Payment::STATUS_PENDING) {
                return $payment->status; // возвращаем текущий статус, если платеж не висит как pending
            }
            $payment->status = $status;
            $payment->save();

            $this->auditLogService->log("payment_status_changed", null, null, $payment->terminal->id, parameters: [
                "payment_id" => $payment->id,
                "status" => Payment::STATUS_PAID
            ]);

            SendMerchantWebhook::dispatch($paymentId, $status); // отправляем задачу на выполнение в хранилище - это нерекомендуемый способ реализации отправки

            return $payment->status;
        });
    }
}
