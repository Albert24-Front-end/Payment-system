<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payment\PaymentProcessingRequest;
use App\Services\PaymentProcessingService;

class PaymentProcessingController extends Controller
{
    public function __invoke(
        int $paymentId,
        PaymentProcessingRequest $request,
        PaymentProcessingService $service,
    )
    {
        $data = $request->toDTO();

        return [
            "success" => true,
            "data" => [
                "newStatus" => $service->changePaymentStatus($paymentId, $data->status),
            ]
        ];
    }
}
