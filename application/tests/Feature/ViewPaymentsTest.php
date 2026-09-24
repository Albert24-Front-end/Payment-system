<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Role;
use App\Models\Terminal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ViewPaymentsTest extends TestCase
{
    use RefreshDatabase;
    private User $adminUser;
    // when using collections - php doc comments for syntax analyser - в платеж не запишем случайно юзера + подсказки от IDE
    /**
     * @var Collection<Payment>
     */
    private Collection $payments;
    /**
     * @var Collection<User>
     */
    private Collection $users;
    /**
     * @var Collection<Terminal>
     */
    private Collection $terminals;
    private CarbonImmutable $now;

    public function setUp(): void
    {
        parent::setUp();
        $roleAdmin = Role::where("name", "admin")->first();
        $this->adminUser = User::factory()->for($roleAdmin)->create();
        $this->users = User::factory()->count(7)->create();
        $this->terminals = Terminal::factory()
            ->state(
                new Sequence(
                    ...$this->users->map(fn (User $user) => ["user_id" => $user->id])
                )
            )->count(7)->create(); // Sequence присваивает каждую кассу по юзерам
        $statuses = [
            Payment::STATUS_PENDING,
            Payment::STATUS_PAID,
            Payment::STATUS_PAID,
            Payment::STATUS_PAID,
            Payment::STATUS_FAILED,
            Payment::STATUS_FAILED,
            Payment::STATUS_FAILED,

        ];
        $this->now = CarbonImmutable::now();
        $this->payments = Payment::factory()
            ->state(
                new Sequence(
                    ...$this->terminals->map(fn (Terminal $terminal, int $index) => [
                        "terminal_id" => $terminal->id,
                        "status" => $statuses[$index],
                        "created_at" => $this->now->subMinutes($index),
                    ])
                )
            )->count(7)->create();
    }

    public function testGetPaymentsList(): void
    {
        $response = $this->actingAs($this->adminUser)->get("/api/admin/payments");
        $response->assertStatus(200);
        $data = $this->payments->map(fn (Payment $payment) => $this->mapPayment($payment))->toArray();
        $response->assertJson([
            "success" => true,
            "data" => $data,
        ]);
    }

    // приводим платежи в удобный для ответа вид
    private function mapPayment(Payment $payment): array
    {
        return [
            "id" => $payment->id,
            "terminal_id" => $payment->terminal_id,
            "user_id" => $payment->terminal->user_id,
            "terminal_name" => $payment->terminal->name,
            "user_email" => $payment->terminal->user->email,
            "amount" => $payment->amount,
            "status" => $payment->status,
            "created_at" => $payment->created_at->toISOString(),
        ];
    }
}
