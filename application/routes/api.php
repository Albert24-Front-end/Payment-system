<?php

use App\Http\Controllers\AuthentificationController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\TerminalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
// as('auth.') - добавить префикс auth ко всем маршрутам в группе
Route::prefix('/auth')->as('auth.')->group(function () {
    Route::post('/register', [AuthentificationController::class, 'register'])->name('register')->middleware(['throttle:reg']);
    Route::post('/login', [AuthentificationController::class, 'login'])->name('login')->middleware(['throttle:login']);
    Route::post('/verify', [AuthentificationController::class, 'verifyEmail'])->name('verify');
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Эти роуты вызываются только при наличии токена - работает мидлвар
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('/terminals')->as('terminal.')->group(function () {
        Route::get("", [TerminalController::class, 'index'])->name('index');

        Route::post("", [TerminalController::class, 'create'])->name('create');

        Route::put("/{terminal}", [TerminalController::class, 'update'])
            ->middleware("can:update,terminal") // проверка права редактирования кассы
            ->name('update');

        Route::delete("/{terminal}", [TerminalController::class, 'delete'])
            ->middleware("can:delete,terminal") // проверка права удаления кассы
            ->name('delete');

        Route::get("/{terminal}/secret_key", [TerminalController::class, 'getSecretKey'])
            ->middleware('can:viewSecretKey,terminal')
            ->name('secret-key');
    });
});
