<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tests\Traits\WithAuditLogs;

class UserBanTest extends TestCase
{
   use RefreshDatabase, WithAuditLogs;

   private User $adminUser;

   public function setUp(): void
   {
       parent::setUp();
       $roleAdmin = Role::where("name", "admin")->first();
       $this->adminUser = User::factory()->for($roleAdmin)->create();
   }

    public function testUserBan(): void
    {
        $userToBan = User::factory()->create();
        $response = $this->actingAs($this->adminUser)->post("/api/admin/users/{$userToBan->id}/ban");
        $response->assertOk();
        $userToBan->refresh();
        $this->assertEquals(User::STATUS_BANNED, $userToBan->status);

        $this->assertLog("user-ban", $userToBan->id, $this->adminUser->id, null);
    }

    public function testUserBanWithInvalidUser(): void
    {
        $userToBan = User::factory()->create();
        $notAdmin = User::factory()->create();
        $response = $this->actingAs($notAdmin)->post("/api/admin/users/{$userToBan->id}/ban");
        $response->assertForbidden();
    }
}
