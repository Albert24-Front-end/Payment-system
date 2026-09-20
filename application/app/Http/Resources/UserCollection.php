<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
// Коллекция добавляет пагинацию в метаданные
class UserCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            "success" => true,
            "data" => $this->collection,
        ];
    }
}
