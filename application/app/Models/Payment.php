<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(["terminal_id", "order_id", "amount", "description", "status"])]
class Payment extends Model
{
    use HasFactory;
    const STATUS_PENDING = 0;
    const STATUS_PAID = 1;
    const STATUS_FAILED = 2;

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }
}
