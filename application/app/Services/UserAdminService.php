<?php

namespace App\Services;

use App\Models\User;

class UserAdminService
{
    public function getUserList($perPage = 10)
    {
        return User::orderBy("created_at", "DESC")->paginate($perPage);
    }
}
