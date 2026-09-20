<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class UserAdminService
{
    public function getUserList($perPage = 10, array $filters = [])
    {
        return User::orderBy("created_at", "DESC")
            ->when($filters["email"] ?? null, fn(Builder$query, $email) => $query->whereLike("email", "%{$email}%"))
            ->paginate($perPage);
    }
}
