<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\WithAuditLogs;

class RegistrationTest extends TestCase
{
    /**
     * A basic feature test example.
     */

    use WithFaker;
    use RefreshDatabase;
    use WithAuditLogs;

    public function testSuccessfulRegistration(): void
    {
        \Event::fake(); // Слушатели не вызываются, изолируем тест от других тем самым
        $email = $this->faker->email();
        $password = "123456";
        $response = $this->post('/api/auth/register', [
            "email" => $email,
            "password" => $password,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => $email]);
        $user = User::where('email', $email)->first();
        $this->assertTrue(\Hash::check($password, $user->password));
        $this->assertEquals(Str::lower($email), $user->email);
        \Event::assertDispatched(Registered::class);
        $this->assertLog("user_registration", $user->id);
    }

    public function testInvalidRegistrationData(): void
    {
        $response = $this->post('/api/auth/register', [
            "email" => "",
            "password" => "",
        ]);

        $response->assertStatus(422);

        $response = $this->post('/api/auth/register', [
            "email" => $this->faker->email(),
            "password" => "",
        ]);

        $response->assertStatus(422);

        $response = $this->post('/api/auth/register', [
            "email" => $this->faker->name,
            "password" => "123456",
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function testDuplicateRegistrationData(): void
    {
        $user = User::factory()->create();
        $response = $this->post('/api/auth/register', [
            "email" => $user->email,
            "password" => "123456",
        ]);
        $response->assertStatus(422);

        $response = $this->post('/api/auth/register', [
            "email" => Str::upper($user->email),
            "password" => "123456",
        ]);
        $response->assertStatus(422);
    }

    public function testSingleIPRegistrationRestriction(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $resp = $this->withServerVariables(["REMOTE_ADDR" => "3.3.3.3"])->post('/api/auth/register', [
                "email" => $this->faker->email(),
                "password" => "123456",
            ]);
            $resp->assertStatus($i < 10 ? 201 : 429);
        }
    }
}
