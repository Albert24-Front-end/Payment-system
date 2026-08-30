<?php

namespace App\Services;

use App\Contracts\AuditLogContract;
use App\Data\Auth\LoginData;
use App\Data\Auth\RegistrationData;
use App\Mail\EmailVerification;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthentificationService
{
    public function __construct(
        // Dependency Inversion Principle из SOLID
        readonly private AuditLogContract $auditService // за абстракцией скрывается конкретная ее реализация - ее-то и берет Auth Service, не зная, как работает Audit Log с БД
    )
    {
    }

    public function register(RegistrationData $data): User
    {
        $user = User::create([
            'email' => $data->email,
            'password' => $data->password,
        ]);
        event(new Registered($user)); // event dispatcher
        $this->auditService->log('user_registration', $user->id);
        return $user;
    }

    public function login(LoginData $data): array
    {
        $user = User::where('email', $data->email)->firstOrFail();
        $user->checkPassword($data->password);
        $token = $user->createToken('login-token');
        $this->auditService->log('user_login', $user->id);
        return [
            'token' => $token->plainTextToken,
        ];
    }

    public function sendVerificationEmail(User $user): void
    {
        $code = Str::random(6);
        Cache::put("mail-$code", $user->email, now()->addHour());
        // отправка email
        Mail::to($user)->send(new EmailVerification($code));
        $this->auditService->log('verification_code_sent', $user->id, parameters: ['email' => $user->email]);
    }

    public function verifyEmail(string $code): void
    {
        $email = Cache::get("mail-$code");
        $user = User::where('email', $email)->firstOrFail();
        $user->email_verified_at = now();
        $user->save();
        $this->auditService->log('email_verified', $user->id, parameters: ['email' => $user->email]);
    }
}
