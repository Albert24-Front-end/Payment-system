<?php

namespace App\Http\Requests\Withdrawal;

use App\Data\Withdrawal\WithdrawalData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WithdrawalCreationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            "terminal_id" => [
                "required",
                "integer",
                Rule::exists("terminals", "id")
                    ->where("user_id", $this->user()->id)
                    ->whereNull("deleted_at"),
            ],
            "amount" => ["required", "integer", "min:1"],
            "bank_code" => ["required", "string"],
            "account_number" => ["required", "string"],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function toDTO(): WithdrawalData
    {
        return new WithdrawalData(...$this->validated());
    }
}
