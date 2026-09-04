<?php

namespace App\Services;

use App\Contracts\AuditLogContract;
use App\Contracts\SignatureContract;
use App\Data\Payments\PaymentData;
use App\Exceptions\InvalidSignature;
use App\Models\Payment;
use App\Models\Terminal;

class PaymentCreationService
{
    public function __construct(
        // если подключается контракт, его надо передать в провайдер
        readonly private SignatureContract $signatureService,
        readonly private AuditLogContract $auditLogService
    )
    {
    }
    public function createPayment(PaymentData $data, string $signature): string
    {
        $terminal = Terminal::findOrFail($data->terminal_id);
        if (!$this->signatureService->checkSignature((array) $data, $terminal->secret_key, $signature)) {
            throw new InvalidSignature();
        }

        $payment = Payment::create([
            ...(array) $data,
            "status" => Payment::STATUS_PENDING,
        ]);

        // в аудит лог нельзя класть номера карты, пароли, ключи и другую секретную инф-ию
        $this->auditLogService->log("payment_created", null, null, terminal_id: $terminal->id, parameters: ["order_id" => $payment->order_id]);

        return config("app.url") . "/api/process-payments/{$payment->id}";
    }
}
