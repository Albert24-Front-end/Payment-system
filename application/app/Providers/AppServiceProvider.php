<?php

namespace App\Providers;

use App\Contracts\AuditLogContract;
use App\Services\OwnDBAuditLogService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // регистрация зависимости в Service Container. Здесь главный смысл абстракции
        $this->app->singleton(AuditLogContract::class, function ($app) {
            return new OwnDBAuditLogService(); // у одного контракта может быть много реализаций в виде сервисов.
            // Эти сервисы можно менять в зав-ти от ситуации
        });
    } // в методе register файла Service Provider связываются абстракции и их конкретные реализации

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for("reg", function (Request $request) {
            return Limit::perMinutes(30, 10)->by($request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinutes(30, 3)
                ->by($request->email ?: $request->ip())
                ->after(function (Response $response) {
                    return $response->status() === 422;
                });
        });
    } // в методе boot выполняется другой функционал при старте приложения
}
