<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Terminal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesStatisticsPayments;

class AdminStatisticsTest extends TestCase
{
    use CreatesStatisticsPayments, RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'admin')->firstOrFail()->id]);
    }

    private function url(array $parameters = []): string
    {
        return '/api/admin/statistics?'.http_build_query(array_replace([
            'group_by' => 'terminal', 'period' => 'day', 'from' => '2026-09-01', 'to' => '2026-09-03',
        ], $parameters));
    }

    public function test_terminal_revenue_includes_only_qualifying_entities_and_fills_their_gaps(): void
    {
        $terminal = Terminal::factory()->for(User::factory())->create();
        $empty = Terminal::factory()->for(User::factory())->create();
        $this->payment($terminal, 7500, '2026-09-02');
        $this->payment($terminal, 7505, '2026-09-02');
        $this->payment($terminal, 9999, '2026-09-02', Payment::STATUS_FAILED);
        $this->payment($empty, 9999, '2026-09-02', Payment::STATUS_PENDING);
        $this->actingAs($this->admin())->getJson($this->url())->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.entity_id', $terminal->id)
            ->assertJsonPath('data.0.entity_label', $terminal->name)
            ->assertJsonPath('data.0.periods', [
                ['period_start' => '2026-09-01', 'payment_count' => 0, 'payment_amount' => 0, 'income' => 0],
                ['period_start' => '2026-09-02', 'payment_count' => 2, 'payment_amount' => 15005, 'income' => 1501],
                ['period_start' => '2026-09-03', 'payment_count' => 0, 'payment_amount' => 0, 'income' => 0],
            ]);
    }

    public function test_user_revenue_rounds_combined_amounts_not_terminal_revenue(): void
    {
        $user = User::factory()->create();
        $first = Terminal::factory()->create(['user_id' => $user->id]);
        $second = Terminal::factory()->create(['user_id' => $user->id]);
        $other = Terminal::factory()->for(User::factory())->create();
        $this->payment($first, 4, '2026-09-01');
        $this->payment($second, 2, '2026-09-01');
        $this->payment($second, 2, '2026-09-01');
        $this->payment($other, 100, '2026-09-02');
        $this->actingAs($this->admin())->getJson($this->url(['group_by' => 'user']))->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.entity_id', $user->id)
            ->assertJsonPath('data.0.periods.0.payment_amount', 8)
            ->assertJsonPath('data.0.periods.0.payment_count', 3)
            ->assertJsonPath('data.0.periods.0.income', 1);
    }

    public static function calendarCases(): array
    {
        $cases = [];
        foreach (MerchantStatisticsTest::calendarCases() as $name => $case) {
            foreach (['terminal', 'user'] as $group) {
                $cases[$group.' '.$name] = [$group, ...$case];
            }
        }

        return $cases;
    }

    #[DataProvider('calendarCases')]
    public function test_calendar_grouping_and_boundaries(string $group, string $period, string $from, string $to, array $axis): void
    {
        $terminal = Terminal::factory()->for(User::factory())->create();
        $start = CarbonImmutable::parse($from, 'UTC');
        $end = CarbonImmutable::parse($to, 'UTC');
        $this->payment($terminal, 14, $from);
        $this->payment($terminal, 15, $end->endOfDay()->format('Y-m-d H:i:s'));
        $this->payment($terminal, 9999, $start->subSecond()->toDateTimeString());
        $this->payment($terminal, 9999, $end->addDay()->toDateTimeString());
        DB::statement("SET LOCAL TIME ZONE 'Asia/Tashkent'");
        $response = $this->actingAs($this->admin())->getJson($this->url([
            'group_by' => $group, 'period' => $period, 'from' => $from, 'to' => $to,
        ]))->assertOk();
        $this->assertSame($axis, array_column($response->json('data.0.periods'), 'period_start'));
        $this->assertSame([14, 0, 15], array_column($response->json('data.0.periods'), 'payment_amount'));
        $this->assertSame([1, 0, 2], array_column($response->json('data.0.periods'), 'income'));
    }

    public function test_access_requires_permission_and_banned_accounts_are_denied(): void
    {
        $this->getJson($this->url())->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson($this->url())->assertForbidden();
        // No support role is provisioned; exercise explicit read-only permission assignment.
        $support = Role::create(['name' => 'support']);
        $reader = User::factory()->create(['role_id' => $support->id]);
        $this->actingAs($reader)->getJson($this->url())->assertForbidden();
        $support->permissions()->attach(Permission::where('name', 'statistics.view')->firstOrFail());
        $this->actingAs($reader->fresh())->getJson($this->url())->assertOk();
        $this->assertFalse($reader->fresh()->hasPermission('users.ban'));
        $reader->forceFill(['status' => User::STATUS_BANNED])->save();
        $this->actingAs($reader->fresh())->getJson($this->url())->assertForbidden();
        $admin = $this->admin();
        $admin->forceFill(['status' => User::STATUS_BANNED])->save();
        $this->actingAs($admin)->getJson($this->url())->assertForbidden();
    }

    public function test_empty_admin_report_does_not_enumerate_inactive_entities(): void
    {
        $terminal = Terminal::factory()->for(User::factory())->create();
        $this->payment($terminal, 100, '2026-09-01', Payment::STATUS_FAILED);
        $this->payment($terminal, 100, '2026-08-31');
        $this->actingAs($this->admin());
        foreach (['terminal', 'user'] as $group) {
            $this->getJson($this->url(['group_by' => $group]))->assertOk()
                ->assertJsonPath('data', [])->assertJsonPath('meta.total', 0)
                ->assertJsonPath('meta.last_page', 1);
        }
    }

    public function test_deleted_terminal_history_and_response_field_allowlist(): void
    {
        $terminal = Terminal::factory()->for(User::factory())->create();
        $this->payment($terminal, 100, '2026-09-01');
        $terminal->delete();
        $this->actingAs($this->admin());
        foreach (['terminal', 'user'] as $group) {
            $response = $this->getJson($this->url(['group_by' => $group]))->assertOk()->assertJsonCount(1, 'data');
            $this->assertSame(['entity_id', 'entity_label', 'periods'], array_keys($response->json('data.0')));
            $this->assertSame(['period_start', 'payment_count', 'payment_amount', 'income'], array_keys($response->json('data.0.periods.0')));
            $response->assertJsonPath('data.0.periods.0.income', 10);
        }
    }

    public function test_entity_pagination_is_stable_and_every_series_is_complete(): void
    {
        $user = User::factory()->create();
        $terminals = Terminal::factory()->count(3)->create(['user_id' => $user->id]);
        foreach ($terminals as $terminal) {
            $this->payment($terminal, 100, '2026-09-01');
            $this->payment($terminal, 100, '2026-09-03');
        }
        $this->actingAs($this->admin());
        foreach ($terminals as $index => $terminal) {
            $this->getJson($this->url(['page' => $index + 1, 'perPage' => 1]))->assertOk()
                ->assertJsonPath('meta.total', 3)->assertJsonPath('meta.last_page', 3)
                ->assertJsonPath('meta.current_page', $index + 1)->assertJsonPath('meta.per_page', 1)
                ->assertJsonCount(1, 'data')->assertJsonCount(3, 'data.0.periods')
                ->assertJsonPath('data.0.entity_id', $terminal->id);
        }
        $this->getJson($this->url(['page' => 4, 'perPage' => 1]))->assertOk()->assertJsonPath('data', []);
        $this->getJson($this->url(['group_by' => 'user', 'perPage' => 1]))->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.periods.0.payment_count', 3);
    }

    public static function invalidParameters(): array
    {
        return [
            'missing grouping' => [['group_by' => null], 'group_by'],
            'invalid grouping' => [['group_by' => 'status'], 'group_by'],
            'missing period' => [['period' => null], 'period'],
            'invalid period' => [['period' => 'week'], 'period'],
            'missing from' => [['from' => null], 'from'],
            'missing to' => [['to' => null], 'to'],
            'invalid date' => [['to' => '2026-02-30'], 'to'],
            'reversed' => [['to' => '2026-08-01'], 'to'],
            'too many buckets' => [['from' => '2024-01-01', 'to' => '2025-01-01'], 'to'],
            'zero page' => [['page' => 0], 'page'],
            'fractional page' => [['page' => '1.5'], 'page'],
            'huge page' => [['page' => '999999999999999999999'], 'page'],
            'zero page size' => [['perPage' => 0], 'perPage'],
            'large page size' => [['perPage' => 101], 'perPage'],
            'unsupported page size alias' => [['per_page' => 25], 'per_page'],
            'user filter' => [['user_id' => 1], 'user_id'],
            'terminal filter' => [['terminal_id' => 1], 'terminal_id'],
        ];
    }

    #[DataProvider('invalidParameters')]
    public function test_invalid_admin_parameters(array $parameters, string $field): void
    {
        $this->actingAs($this->admin())->getJson($this->url($parameters))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_query_count_does_not_grow_per_entity_or_period_and_requests_do_not_write(): void
    {
        $terminal = Terminal::factory()->for(User::factory())->create();
        $this->payment($terminal, 100, '2026-09-01');
        $admin = $this->admin();
        $countQueries = function () use ($admin): int {
            $this->actingAs($admin->fresh());
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->getJson($this->url())->assertOk();
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            foreach ($queries as $query) {
                $this->assertMatchesRegularExpression('/^select\b/i', $query['query']);
            }

            return count($queries);
        };
        $before = $countQueries();
        foreach (Terminal::factory()->for(User::factory())->count(8)->create() as $other) {
            $this->payment($other, 100, '2026-09-02');
        }
        $this->assertSame($before, $countQueries());
        $this->assertLessThanOrEqual(7, $before);
    }

    public function test_permission_migration_rolls_back_without_removing_existing_access(): void
    {
        $migration = require database_path('migrations/2026_09_25_120000_add_statistics_view_permission.php');
        $admin = $this->admin();
        $this->assertTrue($admin->hasPermission('statistics.view'));
        $migration->down();
        $this->assertFalse($admin->fresh()->hasPermission('statistics.view'));
        $this->assertTrue($admin->fresh()->hasPermission('users.view'));
        $this->assertDatabaseHas('roles', ['name' => 'admin']);
        $migration->up();
        $this->assertTrue($admin->fresh()->hasPermission('statistics.view'));
    }
}
