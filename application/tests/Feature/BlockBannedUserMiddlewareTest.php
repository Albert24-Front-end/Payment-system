<?php

namespace Tests\Feature;

use App\Http\Middleware\BlockBannedUserMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BlockBannedUserMiddlewareTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function testBlockedUser403(): void
    {
        $user = User::factory()->state(["status" => User::STATUS_BANNED])->create();
        // чтобы протестировать мидлвар, нужно в тесте создать тестовый роут и на него повесить тестируемый мидлвар
        Route::get("/api/test-route", fn() => ["success" => true])->middleware(BlockBannedUserMiddleware::class);
        // вызываем тестовый роут от забаненного юзера, мидлвар должен дать ошибку 403
        $response = $this->actingAs($user)->get("/api/test-route");

        $response->assertForbidden();
    }
}
