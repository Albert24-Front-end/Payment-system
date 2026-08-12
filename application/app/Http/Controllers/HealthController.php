<?php

namespace App\Http\Controllers;

class HealthController
{
    public function __invoke(): array
    {
        return ["success" => true];
    }
}
