<?php

namespace App\Http\Controllers;

use App\Data\Auth\LoginData;
use App\Data\Auth\RegistrationData;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\AuthRegistration;
use App\Services\AuthentificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthentificationController extends Controller
{
    public function register(AuthRegistration $request, AuthentificationService $authService)
    {
//        $regData = new RegistrationData(...$request->all());
        $authService->register($request->toDTO());
        return response()->json(["success" => true], 201);
    }

    public function login(LoginRequest $request, AuthentificationService $authService)
    {
        try {
            return $authService->login($request->toDTO());
        }
        catch (\Throwable $th) {
            throw ValidationException::withMessages([
                "email" => "Invalid credentials",
            ]);
        }
    }
}
