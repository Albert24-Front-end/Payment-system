# Payment Statistics Implementation Plan (Version 2)

Status: plan for implementation by an AI coding agent; the code described here has not yet been verified.

## 1. Sources, scope, and instructions for the agent

**Task:** [AI tasks/statistics.md](../AI%20tasks/statistics.md).

**Specification:** [AI specification/base-spec.md](../AI%20specification/base-spec.md).

**Project rules:** `.codex/development.md`, `.codex/testing.md`, `.codex/runtime.md`, and `.codex/project-overview.md`.

**Requirements from the project specification:** A merchant may have several terminals. Merchants need statistics **per terminal**: payment counts and average payment amounts by day, month, and year. Administrators need to see **how much revenue each terminal and each user** generates for the payment system. The specified revenue is 10% of successful payments. Payment amounts are stored as integer tiyins. The system has administrators with full access and support agents with read-only access (plus the ability to reply to tickets). After a user is banned, only conversations with support remain available to them. The API accepts and returns JSON; implementation is checked with tests and HTTP requests.

**Scope:** Provide a read-only API over existing payments. Do not change payments, balances, actual fees, withdrawal requests, webhooks, existing APIs, or the accounting model. Do not add a UI, scheduled jobs, caching, materialized statistics, or a general analytics framework.

### What the specification establishes and what this plan proposes for v1

| Question | Basis | Implementation decision |
| --- | --- | --- |
| Merchant scope | The specification asks for statistics per terminal; a merchant can have several terminals. | Return the series for **one selected terminal**. The merchant can call the endpoint for each terminal they own. Do not add an all-terminal summary or a list of terminal series without a separate requirement. |
| Administrative grouping | The specification asks for revenue by terminal and by user. | Use one administrative endpoint with `group_by=terminal|user`. |
| Qualifying payments | The specification explicitly says “successful” for revenue; it does not specify a status for merchant metrics. | **Proposed uniform rule:** Include only the final successful/paid status in counts, average amounts, and revenue. Exclude pending and failed payments. Do not present this assumption as an explicit requirement from the specification. |
| Reporting date | The specification does not specify it; the earlier plan says there is no `paid_at`. | Use `payments.created_at` after checking the schema. This reports **payments currently marked successful by their creation date**, not revenue by the date they were actually paid. Do not silently substitute `updated_at` for `paid_at`. |
| Timezone | Not specified. | Default to UTC if the application and stored timestamps actually use UTC. Inspect the column type and the application and PostgreSQL connection settings; SQL grouping and PHP period generation must agree. If the project uses another timezone, adapt both and document the choice. |
| Date range | Query parameters are not specified. | Require `period`, `from`, and `to`; `from` and `to` are inclusive calendar dates in `YYYY-MM-DD` format. Filter timestamps with `>= start of from` and `< start of the day after to`. |
| Empty periods | The response format is not specified. | Return all calendar periods in the range for the selected terminal, filling missing periods with zeros. Also fill missing periods for each entity in the administrative report. |
| Entities in the administrative report | The specification asks who generates revenue but does not say whether to include entities with zero payments. | **Proposed compact behavior:** Include only terminals/users with at least one successful payment in the requested range; fill gaps within each included series. If no entities qualify, return `data: []`. Do not enumerate every registered user and terminal with zero-filled series. |
| Deleted terminals | Historical reporting is not specified. | **Proposed behavior:** If the model uses `SoftDeletes`, include payments associated with deleted terminals in historical administrative statistics. Allow an owner to view their own deleted terminal through a separately authorized lookup if consistent with existing project behavior. Check ownership for deleted records too. |
| User attribution | Historical ownership changes are not described. | Group by the terminal's current owner. If the schema has a more accurate snapshot of ownership on each payment, revisit this rule. Do not add an ownership ledger solely for this report. |
| Rounding | The specification sets revenue at 10% but gives no rule for fractional tiyins. | **Proposed rule:** Return whole tiyins, rounding to the nearest integer with ties rounded up. Apply rounding **once per group and period** to `SUM(amount)/10` for revenue and `SUM(amount)/COUNT(*)` for the average payment amount. Avoid `float`; test amounts that do not divide evenly. This is an estimate of revenue, not an actual fee charged on each payment. |
| Support-agent access | The specification gives support agents read-only access to the system. | Statistics are read-only: if the project has a general read-access policy, grant support agents permission to view them as well. Check actual roles, permissions, and restrictions; do not grant write access or expose extra data that support agents currently cannot view. Banned users must not be able to access statistics. |

If these **proposed** decisions conflict with the task, existing behavior, or clarification from the project owner, adjust the API contract and tests **before** implementation. In particular, resolve the meaning of a successful payment, the rounding rule, and support-agent access explicitly; do not bury disagreements in the code.

## 2. Initial HTTP contract

Use `Terminal`, `terminal`, and `terminal_id` consistently across all layers and examples. Keep the project's existing route prefixes, authentication, banned-user middleware, and response conventions.

### Merchant

`GET /api/terminals/{terminal}/statistics?period=day&from=2026-09-01&to=2026-09-02`

- Require `period=day|month|year`, `from`, and `to`. Do not accept `user_id`, `page`, `per_page`, or an arbitrary `terminal_id` as query parameters: the terminal is already identified in the path.
- Permit access only to the terminal owner and any other roles allowed under current project rules. Arrange lookup and authorization so changing the ID cannot reveal another user's data. For someone else's terminal, use the status consistent with the project (403 with route binding and authorization, or 404 with an owner-scoped lookup); select **one** behavior and test it. Return 404 for a nonexistent terminal.
- Do not rely solely on middleware or route binding to protect the SQL query: restrict the payment query to the authorized terminal ID.

Example (align field names with the existing API while preserving their types and meanings):

```json
{
  "success": true,
  "data": [
    {"period_start": "2026-09-01", "payment_count": 2, "payment_amount": 15001, "average_check": 7501},
    {"period_start": "2026-09-02", "payment_count": 0, "payment_amount": 0, "average_check": 0}
  ],
  "meta": {"period": "day", "from": "2026-09-01", "to": "2026-09-02", "timezone": "UTC", "money_unit": "tiyin"}
}
```

`payment_amount` helps verify the average but is not required by the project specification. If the existing API exposes only required metrics, omit this field from the public response while keeping the total internally for aggregation. `period_start` is `YYYY-MM-DD` for a day, the first day of the month for a month, and January 1 for a year. Include the calendar month/year containing either range boundary even when `from` or `to` is inside it; count only payments within the exact requested dates.

### Administrative report

`GET /api/admin/statistics?group_by=terminal&period=month&from=2026-01-01&to=2026-09-30`

- Require `group_by=terminal|user`, `period`, `from`, and `to`. Do not add `user_id` or `terminal_id` filters yet: the statistics requirements do not call for them. Filters in other admin features do not imply that they are needed here.
- Use an explicit statistics viewing permission (`statistics.view`) and the project's existing role mechanism. The `/admin` prefix is not authorization by itself. Add a permission migration only if the project actually stores permissions as records; do not edit old migrations. Assign the permission to read-access roles according to the rule verified above.
- Paginate the entity list using the project's existing convention. If there is none, support `page` and `per_page` with a default of 25 and a maximum of 100. First find entities with successful payments in the date range, then sort and paginate them deterministically, and finally fetch periods for the selected page with **one** aggregate query. `total` is the number of qualifying entities, not the number of payments. Do not paginate “entity × period” rows.
- With `group_by=terminal`, return the ID, a safe display name, and the owner ID if needed. With `group_by=user`, return the ID and a user display field permitted by project rules. Never return `secret_key`, full models, tokens, or unnecessary personal fields.

```json
{
  "success": true,
  "data": [
    {
      "entity_id": 12,
      "entity_label": "Shop A",
      "periods": [
        {"period_start": "2026-09-01", "payment_count": 2, "payment_amount": 15001, "income": 1500},
        {"period_start": "2026-10-01", "payment_count": 0, "payment_amount": 0, "income": 0}
      ]
    }
  ],
  "meta": {"group_by": "terminal", "period": "month", "from": "2026-09-15", "to": "2026-10-10", "timezone": "UTC", "money_unit": "tiyin", "current_page": 1, "per_page": 25, "total": 1, "last_page": 1}
}
```

This example illustrates partial months. Check the exact JSON keys against existing API responses; once chosen, lock them down with feature tests and concise API documentation.

## 3. Input validation and time boundaries

- The FormRequest validates the supported `period`, required real calendar dates in `Y-m-d` format, `from <= to`, the allowed `group_by` for admins only, and pagination parameters for admins only. Do not accept unknown parameters that purport to filter the data scope; a merchant's `user_id` parameter must not expand the query.
- Proposed limit: no more than **366 calendar buckets** per request. Return 422 with a clear error when exceeded. Count buckets using the selected strategy's calendar steps, not a day difference for monthly or yearly groupings. This protects response size; it is not a requirement from the specification. Never silently truncate periods. With admin pagination, the limit applies to each series.
- Compute the start of `from` and the exclusive boundary at the start of `to + 1 day` in the agreed timezone. For PostgreSQL `timestamp with time zone`, group explicitly in that timezone; for `timestamp without time zone`, establish what the stored values represent and use an equivalent expression. Test the actual SQL against PostgreSQL: `date_trunc()` may use the session timezone. Keep date predicates on the underlying `payments.created_at` column so an index can be used.
- Do not use `whereBetween(created_at, ['2026-09-01', '2026-09-30'])`: it may exclude nearly all of the last day. Do not step through months in 30-day increments or years in 365-day increments.

## 4. Architecture for the training project

Keep the straightforward **Controller → FormRequest → DTO → Service** flow if the repository confirms that the project already uses it. Separate the domain services: `MerchantStatisticsService` handles one specific terminal; `AdminStatisticsService` handles admin data scope and both grouping options. Extract small classes for mechanisms that are genuinely shared; do not build a universal `StatisticsService`, repository layer, or analytics engine.

Proposed names (check against the current project structure):

```text
app/Http/Requests/Statistics/TerminalStatisticsRequest.php
app/Http/Requests/Statistics/AdminStatisticsRequest.php
app/Http/Controllers/TerminalStatisticsController.php
app/Http/Controllers/Admin/StatisticsController.php
app/Data/Statistics/TerminalStatisticsData.php
app/Data/Statistics/AdminStatisticsData.php
app/Services/Statistics/MerchantStatisticsService.php
app/Services/Statistics/AdminStatisticsService.php
app/Services/Statistics/Periods/PeriodStrategy.php
app/Services/Statistics/Periods/DayPeriodStrategy.php
app/Services/Statistics/Periods/MonthPeriodStrategy.php
app/Services/Statistics/Periods/YearPeriodStrategy.php
app/Services/Statistics/Periods/PeriodStrategyFactory.php
app/Services/Statistics/PeriodSeriesFiller.php
```

Place thin HTTP controllers, the policy/gate, routes, the permission migration, and tests according to the repository's conventions. `PeriodSeriesFiller` clearly describes its responsibility. Do not add a separate helper for two lines of arithmetic unless needed; if rounding merits extraction, use a small pure function and test it separately.

### Strategy contract

A compact contract (implementation guidance, not mandatory verbatim signatures):

```php
interface PeriodStrategy
{
    public function name(): string;                 // day | month | year
    public function sqlBucketExpression(): string;  // fixed expression for payments.created_at
    public function periodKey(DateTimeInterface $date): string; // YYYY-MM-DD at period start
    public function nextStart(CarbonImmutable $start): CarbonImmutable;
}
```

The factory accepts only `day|month|year` and returns the appropriate strategy. Share the same fixed list of supported values between validation and the factory. The strategy supplies the calendar bucket start, its key, and the step to the next bucket; the common `PeriodSeriesFiller` and/or the factory builds the axis from the period containing `from` through the period containing `to`. Do not use `normalizeRange()` with `whereBetween`: compute the date boundaries once, separately from all strategies. Do not allow a user-supplied SQL column name: SQL expressions must be fixed and verified for the schema.

### Queries and calculations

1. Start from `Payment::query()` and Laravel's builder (`where`, `join`, `selectRaw`, `groupByRaw`). The strategy supplies only a predefined PostgreSQL grouping fragment; bind dates and IDs as parameters. Do not interpolate HTTP values into raw SQL.
2. For merchants, restrict successful payments to the authorized terminal and half-open time range, then get `COUNT(*)` and `SUM(amount)` per bucket. For admins, separately select qualifying entities in the range, sort/paginate them, and aggregate only the entities on the selected page by bucket. Do not load every Payment model.
3. For `group_by=user`, aggregate amount and count **directly by user and period**. Do not average terminal averages or sum rounded terminal revenues: differing weights and rounding would produce different results.
4. PostgreSQL may return `SUM`/`COUNT` as strings. Check integer conversion and overflow; do not switch to `float`. Compute average payment amount and revenue from the unrounded aggregate amount/count after querying. An empty bucket has zero amount, count, average, and revenue.
5. `PeriodSeriesFiller` builds the list of buckets once, indexes aggregate rows by `(entity_id, period_start)` or a single key for the merchant report, and inserts zeros for missing dates. Preserve chronological order and deterministic entity order. The number of SQL queries should depend on pagination, not the number of buckets or terminals.

## 5. TDD sequence

1. Check the `Payment` → `Terminal` relationship, statuses, SQL date type, and access rules in the repository; record any proposed decisions that need revision. Inspect existing factories and ensure internal order IDs are unique when creating test payments.
2. Write a merchant feature test: successful payments for one owned terminal, a missing middle day, and correct count/amount/average. Confirm it fails first. Add the Request, DTO, route, access control, and minimum service needed to pass it.
3. Write unit tests for calendar strategies, the factory, and gap filling (range edges, months, years, leap day). Implement them; verify the strategy's SQL behavior with a PostgreSQL feature test, not only by comparing generated strings.
4. Write feature tests for administrative `group_by=terminal|user`, then add permission, service, and controller. Cover several terminals belonging to one user, several users, and different numbers of payments per terminal.
5. Cover the edge cases below with tests, keeping each increment green. Do not create classes in advance that the current step does not need.

### Required test matrix

- Day/month/year on both endpoints; correct number of periods; partial months/years, year boundaries, and leap day.
- Successful payments only; another user's terminal never appears in the merchant result; `group_by=user` correctly aggregates amount/count across multiple terminals.
- Inclusive lower bound; all of the `to` date included; the beginning of the following day excluded. PHP and PostgreSQL produce the same buckets with the project's actual time settings.
- Zero-filled periods at the beginning, middle, and end; a completely empty series for the selected terminal; an empty list of admin entities.
- Verify 10% revenue and the average payment amount on sums that do not divide evenly, including half a tiyin; monetary JSON values are integers. Verify that user revenue comes from the combined amount rather than rounded terminal revenues.
- Guest 401; denied access as 403 or 404 according to existing project rules; support-agent access under the agreed permissions; a banned user can use only support; nonexistent ID 404.
- Missing/invalid parameters, reversed dates, too many periods, invalid pagination; a merchant cannot change the data scope with a `user_id` parameter.
- If `SoftDeletes` is used and the proposed semantics are adopted, verify historical data remains available while ownership restrictions hold. Do not expose the terminal secret or unnecessary user data.
- Stable pagination of admin entities, a complete period series on each page, and a reasonably constant number of SQL queries as the number of entities grows. GET requests must not write to the database or change existing payment and withdrawal behavior.

## 6. Verification before completion

- Run tests **in the project's actual container and working directory**, using the repository's command and an isolated PostgreSQL test database. Commands suggested by the earlier plan: `php artisan test --filter=Statistics`, the `Period` tests, the full `composer test`, `php artisan route:list --path=statistics -v`, and Pint for touched PHP files. Do not assume the command `docker compose exec -T -u www-data php` remains correct without checking the Compose setup.
- If a new permission migration is needed, test it and its rollback **in an isolated test environment**; do not modify historical migrations. Inspect SQL query plans with representative data before adding indexes; add an index only if warranted.
- Briefly document parameters, JSON shape, successful-only filtering, creation date rather than payment date, timezone, rounding, and the treatment of soft-deleted terminals. In the completion report, list commands actually run, their results, and any remaining assumptions.
