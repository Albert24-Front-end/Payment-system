<?php

namespace App\Http\Requests\Payment;

use App\Data\Payments\PaymentProcessingData;
use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentProcessingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'integer',
                Rule::in([
                    Payment::STATUS_PAID,
                    Payment::STATUS_FAILED,
                ]),
            ],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function toDTO(): PaymentProcessingData
    {
        return new PaymentProcessingData(...$this->validated());
    }
}
