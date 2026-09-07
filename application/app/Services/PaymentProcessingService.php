<?php

namespace App\Services;

use App\Contracts\AuditLogContract;
use App\Contracts\SignatureContract;
use App\Jobs\SendMerchantWebhook;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PaymentProcessingService
{
    public function __construct(
        readonly private AuditLogContract $auditLogService,
        // достаем контракт для сервиса подписи
        readonly private SignatureContract $signatureService
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

            SendMerchantWebhook::dispatch($paymentId); // отправляем задачу на выполнение в хранилище - это нерекомендуемый способ реализации отправки

            return $payment->status;
        });
    }

    public function sendStatusWebhook(int $payment_id, int $retry_count): void
    {
        $payment = Payment::findOrFail($payment_id);
        $webhookUrl = $payment->terminal->webhook_url; // тут с помощью "ctrl + shift + ." связаны две модели: платеж и касса
        // готовим данные для тела запроса
        $data = [
            "order_id" => $payment->order_id,
            "amount" => $payment->amount,
            "status" => $payment->status,
        ];
        // готовим подпись в заголовок запроса
        $signature = $this->signatureService->sign($data, $payment->terminal->secret_key);

        try {
            // составляем запрос http
            Http::withHeaders(["X-Signature" => $signature, "Content-Type" => "application/json"])->post($webhookUrl, $data)->throw();
            // throw делает, чтобы http client при ошибке сразу бросал исключение, а не возвращал просто ошибку

            $this->auditLogService->log("to_merchant_webhook_sent", null, null, terminal_id: $payment->terminal_id, parameters: [
                "payment_id" => $payment->id,
                "status" => $payment->status,
                "url" => $payment->terminal->webhook_url,
            ]);
        } catch (ConnectionException | RequestException $e) {
            if ($retry_count < 5 && ($e instanceof ConnectionException || $e instanceof RequestException && $e->response->status() >= 500)) {
                SendMerchantWebhook::dispatch($payment_id, $retry_count + 1)->delay(now()->addMinutes(2 ** ($retry_count - 1)));

                $this->auditLogService->log("webhook_failed_with_retry", null, null, terminal_id: $payment->terminal->id, parameters: [
                    "payment_id" => $payment->id,
                    "status" => $payment->status,
                    "url" => $payment->terminal->webhook_url,
                ]);
            }
            if ($retry_count >= 5) {
                $this->auditLogService->log("webhook_failed_five_times", null, null, terminal_id: $payment->terminal->id, parameters: [
                    "payment_id" => $payment->id,
                    "status" => $payment->status,
                    "url" => $payment->terminal->webhook_url,
                ]);
            }
        }
    }
}
