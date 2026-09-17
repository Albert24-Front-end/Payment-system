<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
#[Fillable(["user_id", "terminal_id", "amount", "bank_code", "account_number"])]
class WithdrawalRequest extends Model
{
    const STATUS_PENDING = 0;
    const STATUS_SUCCESS = 1;
    const STATUS_REJECTED = 2;
}
