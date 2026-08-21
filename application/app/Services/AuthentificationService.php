<?php

namespace App\Services;

use App\Data\Auth\RegistrationData;
use App\Models\User;

class AuthentificationService
{
    public function register(RegistrationData $data): User
    {
        $user = User::factory()->create([
            'email' => $data->email,
            'password' => $data->password,
        ]);
        return $user;

    }

//    public function login(LoginData $data): User
//    {
//
//    }
}
