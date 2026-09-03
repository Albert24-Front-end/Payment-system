<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TerminalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    // это инструмент для изменения полей в Модели сущности перед конвертацией в JSON, к-я  произойдет на след этапе для вывода инф-ции юзеру

    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        unset($data['secret_key']); // убрать из модели секретный ключ
        return $data;
    }
}
