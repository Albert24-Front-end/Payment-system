<?php

namespace App\Data\Auth;

readonly class LoginData
{
    public function __construct(
        public string $email,
        public string $password,
    )
    {

    }
}
