<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(["user_id", "name", "success_url", "fail_url", "webhook_url"])]
class Terminal extends Model
{
    use HasFactory;
}
