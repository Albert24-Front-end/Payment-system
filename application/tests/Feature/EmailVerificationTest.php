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

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    const FAKE_RANDOM_STRING = 'fake-random-string';

    public function testHasListeners()
    {
        \Event::fake();
        \Event::assertListening(Registered::class, \App\Listeners\SendVerificationEmail::class);
    }

    // Listener testing
    public function testEmailVerification()
    {
        Str::createRandomStringsUsing(function () {
            return self::FAKE_RANDOM_STRING;
        });
        $listener = app(SendVerificationEmail::class); // app() imports all dependencies from Laravel
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
    }
}
