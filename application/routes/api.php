<?php

use App\Http\Controllers\AuthentificationController;
use App\Http\Controllers\HealthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::prefix('/auth')->as('auth.')->group(function () {
    Route::post('/register', [AuthentificationController::class, 'register'])->name('register')->middleware(['throttle:reg']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
