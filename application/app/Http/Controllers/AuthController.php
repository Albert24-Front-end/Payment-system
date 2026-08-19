<?php

namespace App\Http\Controllers;

use App\Data\Auth\LoginData;
use App\Data\Auth\RegistrationData;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\AuthRegistration;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(AuthRegistration $request, AuthService $authService)
    {
        $authService->register($request->toDTO());
        return response()->json(["success" => true], 201);
    }

    public function login(LoginRequest $request, AuthService $authService)
    {
        try {
            return $authService->login($request->toDTO());
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([
                "email" => "invalid credentials",
            ]);
        }
    }
}
