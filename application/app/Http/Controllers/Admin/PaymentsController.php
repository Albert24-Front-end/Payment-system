<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PaymentAdminService;

class PaymentsController extends Controller
{
    public function getList(PaymentAdminService $paymentAdminService)
    {
        return $paymentAdminService->getListOfPayments();
    }
}
