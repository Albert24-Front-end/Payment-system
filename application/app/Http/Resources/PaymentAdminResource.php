<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentAdminResource extends JsonResource
{
    protected static function newCollection($resource)
    {
        return new PaymentAdminCollection($resource);
    }
    // выводим юзеру в удобном формате 1 платеж, а PaymentAdminCollection выведет в виде массива data все платежи
    public function toArray(Request $request): array
    {
        return $this->mapPayment();
    }

    private function mapPayment(): array
    {
        return [
            "id" => $this->id,
            "terminal_id" => $this->terminal_id,
            "user_id" => $this->terminal->user_id,
            "terminal_name" => $this->terminal->name,
            "user_email" => $this->terminal->user->email,
            "amount" => $this->amount,
            "status" => $this->status,
            "created_at" => $this->created_at->toISOString(),
        ];
    }
}
