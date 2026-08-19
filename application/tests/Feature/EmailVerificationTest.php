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

    public function testHasListener()
    {
        \Event::fake();
        \Event::assertListening(Registered::class, \App\Listeners\SendVerificationEmail::class);
    }

    public function testVerificationEmailSent()
    {
        Str::createRandomStringsUsing(function () {
            return self::FAKE_RANDOM_STRING;
        });
        $listener = app(SendVerificationEmail::class);
        $user = User::factory()->unverified()->create();
        Mail::fake();
        $listener->handle(new Registered($user));
        $emailFromCache = Cache::get("mail-" . self::FAKE_RANDOM_STRING);
        $this->assertEquals($emailFromCache, $user->email);
        Mail::assertSent(EmailVerification::class, function (EmailVerification $mail) use ($user) {
            $mail->assertTo($user->email);
            $mail->assertSeeInHtml(self::FAKE_RANDOM_STRING);
            return true;
        });
    }
}
