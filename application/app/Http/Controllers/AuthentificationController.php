<?php

namespace App\Http\Controllers;

use App\Data\Auth\RegistrationData;
use App\Http\Requests\AuthRegistration;
use App\Services\AuthentificationService;
use Illuminate\Http\Request;

class AuthentificationController extends Controller
{
    public function register(AuthRegistration $request, AuthentificationService $authService)
    {
//        $regData = new RegistrationData(...$request->all());
        $authService->register($request->toDTO());
        return response()->json(["success" => true], 201);
    }
}
