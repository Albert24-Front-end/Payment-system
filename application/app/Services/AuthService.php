<?php

namespace App\Services;

use App\Data\Auth\LoginData;
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

    public function login(LoginData $data): array
    {
        $user = User::where('email', $data->email)->firstOrFail();
        $user->checkPassword($data->password);
        $token = $user->createToken("login-token");
        return [
            "token" => $token->plainTextToken,
        ];
    }
}
