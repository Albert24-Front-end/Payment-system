<?php

namespace App\Http\Requests\Terminal;

use App\Data\Terminals\TerminalData;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TerminalCreationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            "name" => [
                "required",
                "string",
                "max:255",
                Rule::unique("terminals")->where(fn(Builder $query) => $query->where("user_id", $this->user()->id)), // правило на уникальность имени в пределах одного юзера
            ],
            "success_url" => ["required", "string", "max:255", "url"],
            "fail_url" => ["required", "string", "max:255", "url"],
            "webhook_url" => ["required", "string", "max:255", "url"],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function toDTO(): TerminalData
    {
        return new TerminalData(...$this->all());
    }
}
