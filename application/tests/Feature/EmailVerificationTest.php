<?php

namespace Tests\Feature;

use App\Listeners\SendVerificationEmail;
use App\Mail\EmailVerification;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\WithAuditLogs;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase, WithAuditLogs;

    const FAKE_RANDOM_STRING = 'fake-random-string';

    public function testHasListeners()
    {
        \Event::fake(); // слушатели не выполняются, вместо этого Laravel записывает, какое событие отправлено
        \Event::assertListening(Registered::class, \App\Listeners\SendVerificationEmail::class); // проверяем, Registration действительно породила событие Registered
    }

    // Listener testing
    public function testEmailVerification()
    {
        Str::createRandomStringsUsing(function () {
            return self::FAKE_RANDOM_STRING;
        }); // создали контролируемую "случайную" строку для проведения теста
        $listener = app(SendVerificationEmail::class); // app() imports all dependencies from Laravel and makes dependency resolution through container
        $user = User::factory()->create();
        Mail::fake(); // Письма не отправляются во время теста
        $listener->handle(new Registered($user));
        $emailFromCache = Cache::get("mail-" . self::FAKE_RANDOM_STRING);
        // php artisan make:mail EmailVerification - создали пустой класс для письма Mail
        $this->assertEquals($user->email, $emailFromCache);
        Mail::assertSent(EmailVerification::class, function (EmailVerification $mail) use ($user) {
            $mail->assertTo($user->email);
            $mail->assertSeeInHtml(self::FAKE_RANDOM_STRING);
            return true;
        });
        $this->assertLog("verification_code_sent", $user->id, parameters: ['email' => $user->email]);
    }

    public function testSuccessfulVerification(): void
    {
        $user = User::factory()->unverified()->create();
        Cache::put("mail-" . self::FAKE_RANDOM_STRING, $user->email);

        $resp = $this->post('/api/auth/verify', [
            'code' => self::FAKE_RANDOM_STRING,
        ]);
        $resp->assertStatus(200);
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertLog("email_verified", $user->id, parameters: ['email' => $user->email]);
    }

    public function testInvalidVerificationCode(): void
    {
        $resp = $this->post('/api/auth/verify', [
            'code' => '',
        ]);
        $resp->assertStatus(422);

        $resp = $this->post('/api/auth/verify', [
            'code' => 'Some code',
        ]);
        $resp->assertStatus(422);
    }
}
