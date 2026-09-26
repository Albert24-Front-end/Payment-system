<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Terminal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesStatisticsPayments;

class MerchantStatisticsTest extends TestCase
{
    use CreatesStatisticsPayments, RefreshDatabase;

    private function url(Terminal $terminal, array $parameters = []): string
    {
        return "/api/terminals/{$terminal->id}/statistics?".http_build_query(array_replace([
            'period' => 'day', 'from' => '2026-09-01', 'to' => '2026-09-03',
        ], $parameters));
    }

    public function test_owned_terminal_statistics_include_only_paid_payments_and_fill_gaps(): void
    {
        $user = User::factory()->create();
        $terminal = Terminal::factory()->create(['user_id' => $user->id]);
        foreach ([7500, 7501] as $index => $amount) {
            Payment::factory()->create([
                'terminal_id' => $terminal->id, 'order_id' => 'paid-'.$index,
                'amount' => $amount, 'status' => Payment::STATUS_PAID,
                'created_at' => '2026-09-01 00:00:00',
            ]);
        }
        Payment::factory()->create([
            'terminal_id' => $terminal->id, 'amount' => 99999,
            'status' => Payment::STATUS_FAILED, 'created_at' => '2026-09-02 12:00:00',
        ]);
        $otherOwnedTerminal = Terminal::factory()->create(['user_id' => $user->id]);
        $this->payment($otherOwnedTerminal, 99999, '2026-09-01');
        $this->payment(Terminal::factory()->for(User::factory())->create(), 99999, '2026-09-01');

        $this->actingAs($user)->getJson("/api/terminals/{$terminal->id}/statistics?period=day&from=2026-09-01&to=2026-09-03")
            ->assertOk()->assertExactJson([
                'success' => true,
                'data' => [
                    ['period_start' => '2026-09-01', 'payment_count' => 2, 'payment_amount' => 15001, 'average_check' => 7501],
                    ['period_start' => '2026-09-02', 'payment_count' => 0, 'payment_amount' => 0, 'average_check' => 0],
                    ['period_start' => '2026-09-03', 'payment_count' => 0, 'payment_amount' => 0, 'average_check' => 0],
                ],
                'meta' => ['period' => 'day', 'from' => '2026-09-01', 'to' => '2026-09-03', 'timezone' => 'UTC', 'money_unit' => 'tiyin'],
            ]);
    }

    public static function calendarCases(): array
    {
        return [
            'leap day' => ['day', '2024-02-28', '2024-03-01', ['2024-02-28', '2024-02-29', '2024-03-01']],
            'partial months' => ['month', '2025-12-15', '2026-02-10', ['2025-12-01', '2026-01-01', '2026-02-01']],
            'partial years' => ['year', '2024-12-15', '2026-02-10', ['2024-01-01', '2025-01-01', '2026-01-01']],
        ];
    }

    #[DataProvider('calendarCases')]
    public function test_calendar_grouping_uses_exact_inclusive_dates(string $period, string $from, string $to, array $axis): void
    {
        $terminal = Terminal::factory()->for(User::factory())->create();
        $start = CarbonImmutable::parse($from, 'UTC');
        $end = CarbonImmutable::parse($to, 'UTC');
        $this->payment($terminal, 11, $start->toDateTimeString())->forceFill(['updated_at' => '2026-12-31'])->save();
        $this->payment($terminal, 25, $end->endOfDay()->format('Y-m-d H:i:s'));
        $this->payment($terminal, 9000, $start->subSecond()->toDateTimeString());
        $this->payment($terminal, 9000, $end->addDay()->toDateTimeString());
        $this->payment($terminal, 9000, $from, Payment::STATUS_PENDING);
        // UTC-valued timestamp-without-time-zone grouping must not depend on session timezone.
        DB::statement("SET LOCAL TIME ZONE 'Asia/Tashkent'");
        $response = $this->actingAs($terminal->user)->getJson($this->url($terminal, compact('period', 'from', 'to')))->assertOk();
        $this->assertSame($axis, array_column($response->json('data'), 'period_start'));
        $this->assertSame([11, 0, 25], array_column($response->json('data'), 'payment_amount'));
        $this->assertSame([1, 0, 1], array_column($response->json('data'), 'payment_count'));
        $this->assertSame([11, 0, 25], array_column($response->json('data'), 'average_check'));
    }

    public function test_empty_terminal_and_same_day_range_produce_zero_bucket(): void
    {
        $terminal = Terminal::factory()->for(User::factory())->create();
        $other = Terminal::factory()->for(User::factory())->create();
        $this->payment($other, 999, '2026-09-01');
        $this->actingAs($terminal->user)->getJson($this->url($terminal, ['to' => '2026-09-01']))
            ->assertOk()->assertJsonPath('data', [
                ['period_start' => '2026-09-01', 'payment_count' => 0, 'payment_amount' => 0, 'average_check' => 0],
            ]);
    }

    public function test_authentication_ownership_bans_and_deleted_terminal_history(): void
    {
        $terminal = Terminal::factory()->for(User::factory())->create();
        $this->payment($terminal, 50, '2026-09-01');
        $this->getJson($this->url($terminal))->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson($this->url($terminal))->assertForbidden();
        $terminal->delete();
        $this->getJson($this->url($terminal))->assertForbidden();
        $this->actingAs($terminal->user)->getJson($this->url($terminal))->assertOk()
            ->assertJsonPath('data.0.payment_amount', 50)->assertJsonMissing(['secret_key' => $terminal->secret_key]);
        $this->getJson('/api/terminals/999999999/statistics?period=day&from=2026-09-01&to=2026-09-01')->assertNotFound();
        $terminal->user->forceFill(['status' => User::STATUS_BANNED])->save();
        $this->actingAs($terminal->user->fresh())->getJson($this->url($terminal))->assertForbidden();
    }

    public static function invalidParameters(): array
    {
        return [
            'missing period' => [['period' => null], 'period'],
            'unknown period' => [['period' => 'week'], 'period'],
            'SQL period' => [['period' => "day'); DROP TABLE payments; --"], 'period'],
            'array period' => [['period' => ['day']], 'period'],
            'missing from' => [['from' => null], 'from'],
            'missing to' => [['to' => null], 'to'],
            'impossible date' => [['from' => '2026-02-30'], 'from'],
            'year zero' => [['from' => '0000-01-01', 'to' => '0000-01-02'], 'from'],
            'date with time' => [['to' => '2026-09-03 12:00:00'], 'to'],
            'reversed range' => [['from' => '2026-09-04'], 'to'],
            'too many days' => [['from' => '2024-01-01', 'to' => '2025-01-01'], 'to'],
            'too many months' => [['period' => 'month', 'from' => '2000-01-01', 'to' => '2030-07-01'], 'to'],
            'too many years' => [['period' => 'year', 'from' => '1600-01-01', 'to' => '1966-01-01'], 'to'],
            'user filter' => [['user_id' => 1], 'user_id'],
            'terminal filter' => [['terminal_id' => 1], 'terminal_id'],
            'page' => [['page' => 1], 'page'],
            'page size' => [['per_page' => 1], 'per_page'],
            'admin grouping' => [['group_by' => 'user'], 'group_by'],
        ];
    }

    #[DataProvider('invalidParameters')]
    public function test_invalid_parameters_are_rejected(array $parameters, string $field): void
    {
        $terminal = Terminal::factory()->for(User::factory())->create();
        $this->actingAs($terminal->user)->getJson($this->url($terminal, $parameters))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_exactly366_buckets_are_allowed_for_each_strategy(): void
    {
        $terminal = Terminal::factory()->for(User::factory())->create();
        $this->actingAs($terminal->user);
        foreach ([
            ['period' => 'day', 'from' => '2024-01-01', 'to' => '2024-12-31'],
            ['period' => 'month', 'from' => '2000-01-01', 'to' => '2030-06-01'],
            ['period' => 'year', 'from' => '1600-01-01', 'to' => '1965-12-31'],
        ] as $parameters) {
            $this->getJson($this->url($terminal, $parameters))->assertOk()->assertJsonCount(366, 'data');
        }
    }

    public function test_statistics_requests_only_read_the_database(): void
    {
        $terminal = Terminal::factory()->for(User::factory())->create();
        $this->payment($terminal, 123, '2026-09-01');
        $this->actingAs($terminal->user);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson($this->url($terminal))->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        foreach ($queries as $query) {
            $this->assertMatchesRegularExpression('/^select\b/i', $query['query']);
        }
    }

    public function test_database_sum_outside_integer_range_fails_instead_of_losing_precision(): void
    {
        $terminal = Terminal::factory()->for(User::factory())->create();
        $this->payment($terminal, PHP_INT_MAX, '2026-09-01');
        $this->payment($terminal, 1, '2026-09-01');
        $this->withoutExceptionHandling();
        $this->expectException(\OverflowException::class);
        $this->actingAs($terminal->user)->getJson($this->url($terminal));
    }
}
