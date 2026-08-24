<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class LoginTest extends TestCase
{
    /**
     * A basic feature test example.
     */

    use RefreshDatabase, WithFaker;
    public function testSuccessfulLogin(): void
    {
        $user = User::factory()->create();
        $response = $this->post('/api/auth/login', [
            "email" => $user->email,
            "password" => "password",
        ]);
        $response->assertStatus(200);
        $response->assertJsonStructure(['token']);
    }

    public function testEmptyFields(): void
    {
        $user = User::factory()->create();
        $response = $this->post('/api/auth/login', [
            "email" => "",
            "password" => "",
        ]);

        $response->assertStatus(422);
    }

    public function testInvalidPassword(): void
    {
        $user = User::factory()->create();
        $response = $this->post('/api/auth/login', [
            "email" => $user->email,
            "password" => "password12345",
        ]);
        $response->assertStatus(422);
        $response->assertOnlyJsonValidationErrors(['email']); // только по email ошибка
    }

    public function testInvalidEmail(): void
    {
        $user = User::factory()->create();
        $response = $this->post('/api/auth/login', [
            "email" => $this->faker->email(),
            "password" => "password",
        ]);
        $response->assertStatus(422);
        $response->assertOnlyJsonValidationErrors(['email']);

        $response = $this->post('/api/auth/login', [
            "email" => "kkklllmmm",
            "password" => "password",
        ]);
        $response->assertStatus(422);
        $response->assertOnlyJsonValidationErrors(['email']);
    }

    public function testBruteForceLoginProtection(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i <= 4; $i++) {
            $resp = $this->post('/api/auth/login', [
                "email" => $user->email,
                "password" => "password123",
            ]);
            $resp->assertStatus($i < 3 ? 422 : 429);
        }
    }
}
