<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(["user_id", "name", "success_url", "fail_url", "webhook_url"])]
//#[Hidden("secret_key")] // автоматически прячем ключ
class Terminal extends Model
{
    use HasFactory, SoftDeletes;
}
