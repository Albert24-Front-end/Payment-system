<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UsersListViewTest extends TestCase
{
    use RefreshDatabase;
    private User $adminUser;
    /**
     * @var Collection<User>
     */
    private Collection $users;

    private ?CarbonImmutable $now = null; // неизменяемые дата и время - если будет попытка изменения (вычитание минут), создастся новый экземпляр класс Carbon

    public function setUp(): void
    {
        parent::setUp();
        $roleAdmin = Role::where("name", "admin")->first();
        $this->assertNotNull($roleAdmin);
        $this->now = CarbonImmutable::now(); // основной экземпляр Carbon - он должен оставаться неизменным
        $this->adminUser = User::factory()->for($roleAdmin)->state(["created_at" => $this->now])->create();
        $this->users = User::factory()
            ->state(new Sequence(["created_at" => $this->now->subMinute()],
                ["created_at" => $this->now->subMinutes(2)],
                ["created_at" => $this->now->subMinutes(3)]))
            ->count(3)
            ->create();
        $this->users->prepend($this->adminUser);
    }
    public function testGetUsers(): void
    {
        $response = $this->actingAs($this->adminUser)->get("/api/admin/users");
        $response->assertStatus(200);

        $data = $this->users->map(function($user) {
            $res = $user->toArray();
            unset($res["role"]);
            return $res;
        })->toArray();
        $response->assertJson([
            "data" => $data,
        ]);
    }

    public function testGetUsersByUserWithoutPermission(): void
    {
        $notAdminUser = User::factory()->create();
        $response = $this->actingAs($notAdminUser)->get("/api/admin/users");
        $response->assertStatus(403); // юзер не имеет право на действие
    }

    public function testGetUsersWithPagination(): void
    {
        for ($p = 1; $p <= 2; $p++) {
            $response = $this->actingAs($this->adminUser)->get("/api/admin/users?perPage=2&page=" . $p);
            $response->assertStatus(200);

            $data = $this->users->slice(($p - 1) * 2, 2)->map(function($user) {
                $res = $user->toArray();
                unset($res["role"]);
                return $res;
            })->values()->toArray();
            $response->assertJson([
                "data" => $data,
                "meta" => [
                    "current_page" => $p,
                ]
            ]);
        }
    }

    public function testEmailFilter(): void
    {
        $emailPart = explode("@", $this->adminUser->email)[0];

        $response = $this->actingAs($this->adminUser)->get("/api/admin/users?email=" . $emailPart);
        $response->assertStatus(200);

        $returnedData = $response->json();
        $this->assertEquals(count($returnedData["data"]), 1);
        $this->assertEquals($returnedData["data"][0]["email"], $this->adminUser->email);
    }
}
