<?php

namespace App\Services;

use App\Http\Resources\PaymentAdminResource;
use App\Models\Payment;

class PaymentAdminService
{
    public function getListOfPayments()
    {
        return Payment::orderByDesc("created_at")
            // жадный поиск юзера через кассу (в кассе прописали belongs to user) - Eager loading
            ->with("terminal.user")
            ->get()->toResourceCollection(PaymentAdminResource::class);
    }
}
