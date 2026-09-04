<?php

namespace App\Http\Controllers;

use App\Data\Payments\PaymentData;
use App\Exceptions\InvalidSignature;
use App\Http\Requests\Payment\PaymentCreationRequest;
use App\Services\PaymentCreationService;
use Illuminate\Http\Request;

class PaymentCreationController extends Controller
{
    public function __invoke(PaymentCreationRequest $request, PaymentCreationService $service)
    {
        try {
            $url = $service->createPayment($request->toDTO(), $request->header("X-Signature"));
            return response()->json(["data" => ["url" => $url]], 201);
        } catch (InvalidSignature) {
            return response()->json(["success" => false, "message" => "Invalid signature"], 400);
        }
    }
}
