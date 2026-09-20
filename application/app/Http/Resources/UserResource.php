<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    // данные модели изменяются в Resource до отправки пользователю, а не в БД
    public function toArray(Request $request): array
    {
        $response = parent::toArray($request);
        unset($response["role"]);
        return $response;
    }
}
