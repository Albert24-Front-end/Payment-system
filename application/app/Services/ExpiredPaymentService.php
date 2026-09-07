<?php

namespace App\Services;

use App\Contracts\AuditLogContract;
use App\Jobs\SendMerchantWebhook;
use App\Models\Payment;

class ExpiredPaymentService
{
    public function __construct(
        readonly private AuditLogContract $auditLogService,
    )
    {}

    public function cancelExpiredPayments()
    {
        $expiredPayments = Payment::where("created_at", "<", now()->subMinutes(15))
            ->where("status", Payment::STATUS_PENDING)
            ->get();


        foreach ($expiredPayments as $expiredPayment) {
            $expiredPayment->status = Payment::STATUS_FAILED;
            $expiredPayment->save();

            // отправляем задачу послать вебхук мерчанту в очередь
            SendMerchantWebhook::dispatch($expiredPayment->id);

            $this->auditLogService->log("expired_payment_canceled", null, null, $expiredPayment->terminal->id, parameters: ["payment_id" => $expiredPayment->id]);
        }
    }
}
