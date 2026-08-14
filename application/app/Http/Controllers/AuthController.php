<?php

namespace App\Http\Controllers;

use App\Data\Auth\RegistrationData;
use App\Http\Requests\AuthRegistration;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(AuthRegistration $request, AuthService $authService)
    {
        $authService->register($request->toDTO());
        return response()->json(["success" => true], 201);
    }
}
