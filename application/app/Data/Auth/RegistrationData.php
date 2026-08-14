<?php

namespace App\Data\Auth;

readonly class RegistrationData
{
    public function __construct(
        public string $email,
        public string $password,
    )
    {

    }
}
