<?php

namespace App\Services;

use App\Contracts\AuditLogContract;
use App\Data\Withdrawal\WithdrawalData;
use App\Exceptions\RequestedMoneyAmountIsTooBig;
use App\Models\Payment;
use App\Models\User;
use App\Models\WithdrawalRequest;

class WithdrawalRequestService
{
    public function __construct(
        readonly private AuditLogContract $auditLogService,
    )
    {}

    public function createWithdrawalRequest(User $user, WithdrawalData $withdrawalData): WithdrawalRequest
    {
        return \DB::transaction(function () use ($user, $withdrawalData) {

            // узнаем уровень вложенности транзакций в Laravel на данный момент
            if (\DB::transactionLevel() === 1) {
                \DB::statement("SET TRANSACTION ISOLATION LEVEL SERIALIZABLE");
            }

            // количество денег по оплаченным транзакциям у юзера-мерчанта
            $userPaymentsAmount = Payment::where("terminal_id", $withdrawalData->terminal_id)
                ->where("status", Payment::STATUS_PAID)
                ->sum("amount");

            // количество запрошенных и успешно выведенных денег юзером-мерчантом
            $userWithdrawnAmount = WithdrawalRequest::where("terminal_id", $withdrawalData->terminal_id)
                ->whereIn("status", [WithdrawalRequest::STATUS_PENDING, WithdrawalRequest::STATUS_SUCCESS])
                ->sum("amount");

            $userMoneyAmount = $userPaymentsAmount - $userWithdrawnAmount; // оставшиеся в платежке денег у мерчанта

            if ($withdrawalData->amount > $userMoneyAmount) {
                throw new RequestedMoneyAmountIsTooBig();
            }

            $withdrawalRequest = new WithdrawalRequest((array) $withdrawalData);
            $withdrawalRequest->status = WithdrawalRequest::STATUS_PENDING;
            $withdrawalRequest->user_id = $user->id;

            $withdrawalRequest->save();

            $this->auditLogService->log(
                "withdrawal_request_created",
                user_id: $user->id,
                terminal_id: $withdrawalData->terminal_id,
                parameters: ["amount" => $withdrawalRequest->amount, "request_id" => $withdrawalRequest->id]
            );
            return $withdrawalRequest;
        }, 3);
    }
}
