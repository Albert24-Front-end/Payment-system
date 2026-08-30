<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

#[Unguarded] // разрешает mass assignment всех атрибутов
class AuditLog extends Model
{
//    protected static $unguarded = true; // так записывался раньше аналог атрибута
    protected function casts(): array
    {
        return [
            'parameters' => 'array', // Cast the 'parameters' attribute written in JSON to an array
        ];
    }
}
