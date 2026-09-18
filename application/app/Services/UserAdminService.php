<?php

namespace App\Services;

use App\Models\User;

class UserAdminService
{
    public function getUserList()
    {
        return User::orderBy("created_at", "DESC")->get();
    }
}
