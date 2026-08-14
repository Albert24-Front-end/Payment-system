<?php

namespace App\Services;

use App\Data\Auth\RegistrationData;
use App\Models\User;

class AuthService
{
    public function register(RegistrationData $data): User
    {
        $user = User::create([
            'email' => $data->email,
            'password' => $data->password,
        ]);
        return $user;
    }
}
