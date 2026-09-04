<?php

namespace App\Http\Requests\Payment;

use App\Data\Payments\PaymentData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentCreationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            "terminal_id" => "required|exists:terminals,id",
            "amount" => "required|integer|min:1",
            "description" => "required|string",
            "order_id" => [
                "required",
                "string",
                Rule::unique("payments", "order_id")->where(function($query) {
                    return $query->where("terminal_id", $this->terminal_id);
                })
            ],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function toDTO(): PaymentData
    {
        return new PaymentData(...$this->all());
    }
}
