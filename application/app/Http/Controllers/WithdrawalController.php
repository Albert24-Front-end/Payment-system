<?php

namespace App\Http\Controllers;

use App\Data\Withdrawal\WithdrawalData;
use App\Exceptions\RequestedMoneyAmountIsTooBig;
use App\Http\Requests\Withdrawal\WithdrawalCreationRequest;
use App\Models\WithdrawalRequest;
use App\Services\WithdrawalRequestService;
use Illuminate\Validation\ValidationException;

class WithdrawalController extends Controller
{
    public function create(WithdrawalCreationRequest $request, WithdrawalRequestService $withdrawalRequestService)
    {
        try {
            $withdrawal = $withdrawalRequestService->createWithdrawalRequest(auth()->user(), $request->toDTO());
            return [
                "success" => true,
                "data" => [
                    "request_id" => $withdrawal->id,
                ]
            ];
        } catch (RequestedMoneyAmountIsTooBig $e) {
            throw ValidationException::withMessages(["amount" => $e->getMessage()]);
        }
    }
}
