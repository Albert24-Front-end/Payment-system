<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;

class VerificationCodeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            "code" => ["required",
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (!Cache::has("mail-$value")) {
                        $fail("Invalid code");
                    }
                } // до контроллера не дойдет невалидный код из кэша],
            ],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
