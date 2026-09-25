# Statistics implementation plan

Status: proposed; implementation has not started.

## Sources and scope

- Task: [AI tasks/statistics.md](../AI%20tasks/statistics.md).
- Base requirements: [AI specification/base-spec.md](../AI%20specification/base-spec.md).
- Project rules: `.codex/development.md`, `.codex/testing.md`, `.codex/runtime.md`, and `.codex/project-overview.md`.
- Relevant implementation: payment/terminal/user models, payment migration,
  `PaymentAdminService`, `PaymentProcessingService`, API routes, `UserPolicy`,
  role/permission migrations, and `ViewPaymentsTest`.

Deliver a read-only JSON API for merchant payment statistics and admin income
statistics. Support day, month, and year grouping using interchangeable period
strategies. Aggregate stored payments in PostgreSQL and fill missing periods in
PHP. Keep controllers thin and query Eloquent directly from services.

No dashboard, real payment integration, scheduled collection, persisted summary
tables, or caching is required for the first implementation.

## Proposed business decisions

The task does not settle the following details. These are explicit implementation
defaults to review before coding, not requirements inferred from existing tests.

| Topic | Proposed behavior |
| --- | --- |
| Qualifying payments | Count only `Payment::STATUS_PAID` for merchant totals/averages and admin income. Exclude pending and failed payments. |
| Reporting timestamp | Group by `payments.created_at`. The schema has no `paid_at`; `updated_at` is not a reliable payment-success timestamp. Reports therefore describe currently successful payments by creation period, not immutable historical settlement revenue. |
| Timezone | UTC, matching `application/config/app.php`. SQL grouping and PHP period iteration must use the same timezone. |
| Requested range | Require `from` and `to` as inclusive `YYYY-MM-DD` dates. Filter timestamps with `created_at >= from at 00:00` and `< day after to at 00:00`. |
| Partial periods | Include the day/month/year buckets intersecting the requested range, but aggregate only payments within the exact requested dates. Label a bucket with its calendar start date. |
| Average check | Successful amount divided by successful payment count for each entity and period; return zero for an empty bucket. |
| Fractional tiyins | Return integer tiyins, rounding half up once per entity-period for average check and 10% income. Use integer quotient/remainder arithmetic, never floating point. |
| Income | Round `successful_amount / 10` per reported bucket. Do not subtract this hypothetical income from merchant totals or change balances. |
| Historical terminals | Include soft-deleted terminals in statistics while preserving ownership restrictions, so deleting a terminal does not erase reported history. |
| User attribution | Attribute through the terminal's current `user_id`; there is no historical ownership ledger. |
| Empty entities | Return zero-filled periods for selected terminals/users without successful payments, including entities with no payments at all. No eligible entities returns `data: []`. |
| Size limits | Initially cap requests at 366 buckets and paginate entities, default 25 and maximum 100 per page. Reject excessive ranges with 422; never silently truncate periods. |

If settlement-date reporting is wanted, revise the timestamp decision first: add
`paid_at`, populate it atomically on the first successful transition, and decide
how to handle historical rows. Do not approximate it from `updated_at` silently.
Rounding separate terminal buckets can differ from rounding a combined user
bucket; document this and calculate each grouping directly from its raw totals.

## Proposed API contract

Both endpoints require Sanctum authentication and return JSON. Names are new
proposals; existing routes and responses remain unchanged.

### Merchant

`GET /api/statistics/terminals`

Query parameters: `period=day|month|year`, `from`, `to`, optional `terminal_id`,
`page`, and `per_page`. Require `period`, `from`, and `to`.

Return one series per terminal owned by the authenticated user, ordered by ID.
Never accept a merchant `user_id` as a way to change the query scope; reject it
as an unsupported filter. Explicit terminal selection must use an ownership-scoped
lookup, including soft-deleted terminals. Return 404 for another user's or a
nonexistent terminal without exposing whether the terminal exists.

### Admin

`GET /api/admin/statistics`

Query parameters: common range/period/pagination parameters plus required
`group_by=terminal|user`, optional `user_id`, and optional `terminal_id`.
Allow `terminal_id` only with terminal grouping. When both filters are supplied,
require the terminal to belong to that user. Return 422 for an invalid filter
combination and 404 for a selected entity that does not exist.

Protect the endpoint with a dedicated `statistics.view` permission. Add it to the
existing admin role with a new reversible migration and a policy ability. Ordinary
authenticated users receive 403. The existing `/admin` prefix provides no blanket
admin authorization, so do not rely on that prefix for access control.

### Response shape

Use the existing `success`/`data` convention and resource-based serialization.
For example, a merchant series contains:

```json
{
  "success": true,
  "data": [
    {
      "terminal_id": 12,
      "terminal_name": "Main terminal",
      "periods": [
        {
          "period_start": "2026-09-01",
          "payment_count": 2,
          "payment_amount": 15001,
          "average_check": 7501
        },
        {
          "period_start": "2026-09-02",
          "payment_count": 0,
          "payment_amount": 0,
          "average_check": 0
        }
      ]
    }
  ],
  "meta": {
    "period": "day",
    "from": "2026-09-01",
    "to": "2026-09-02",
    "timezone": "UTC",
    "currency": "UZS",
    "money_unit": "tiyin",
    "current_page": 1,
    "per_page": 25,
    "total": 1,
    "last_page": 1
  }
}
```

Admin series identify a terminal and its user, or a user for user grouping, and
return `payment_count`, `payment_amount`, and `income` per period. Restrict entity
metadata to IDs and display fields; never serialize terminal secrets or full models.

## Design and implementation sequence

### 1. Establish the behavior with feature tests

- Add `MerchantStatisticsTest` and `AdminStatisticsTest` under `tests/Feature`.
- Use `RefreshDatabase`, existing factories, explicit amounts/statuses/timestamps,
  and unique order IDs within each terminal. Fix the test clock where necessary.
- Start with authorization, a populated day range, and a missing middle day.
  Write failing tests before each implementation increment.

### 2. Add validation, DTOs, and authorization

- Add merchant/admin FormRequests under `app/Http/Requests/Statistics` and readonly
  filter DTOs under `app/Data/Statistics`; map validated fields only.
- Validate real calendar dates, supported period/grouping, ordering of dates,
  entity IDs, filter compatibility, pagination, and maximum bucket count.
- Add a dedicated statistics policy and explicitly wire it to the admin route;
  follow existing policy discovery/registration conventions and verify the wiring.
- Add a permission migration without editing historical migrations or recreating
  the admin role. Rollback removes only the permission/association introduced.
- Scope merchant entities in the service as well as validating explicit filters.

### 3. Implement interchangeable period strategies

- Add `app/Services/Assistants/Statistics/Periods/PeriodStrategyContract.php`,
  `DayPeriod`, `MonthPeriod`, `YearPeriod`, and a small allowlisted resolver.
- A strategy provides a PostgreSQL grouping unit, calendar bucket start, next
  bucket boundary, and stable date key. Use immutable dates and calendar stepping,
  not fixed 30-day months or 365-day years.
- Keep the supported-period registry shared with validation. Adding a period
  requires a strategy, registry entry, and tests, not changes to the service loop.
- Use framework-independent unit tests for calendar behavior where possible.
  Verify SQL expressions against PostgreSQL in feature tests.

### 4. Aggregate in a statistics service

- Add `app/Services/StatisticsService.php` with merchant/admin entry points and
  shared private aggregation logic. Do not add a repository or a generic analytics
  framework.
- First select/paginate eligible entities independently of payments. This retains
  entities with no data and bounds response size. Include soft-deleted terminals
  explicitly and apply ownership/admin filters before aggregation.
- Start from `Payment::query()`, join terminals for user attribution as needed,
  filter `STATUS_PAID`, the half-open timestamp range, and the current entity page.
- Group by entity ID and the strategy's calendar bucket; select `SUM(amount)` and
  `COUNT(*)`. For user grouping, aggregate all eligible terminals together rather
  than averaging terminal averages or summing rounded terminal income.
- Keep date predicates on the raw timestamp column. Use bound values and an
  allowlisted strategy for SQL grouping; never interpolate user input into SQL.
- Fetch aggregate rows only, not all payment models. Use a bounded number of
  queries per page, with no query per entity or per period.
- PostgreSQL aggregate values may arrive as strings; normalize counts and amounts
  deliberately. Guard conversion overflow rather than silently converting to float.

### 5. Fill gaps and calculate metrics in PHP

- Add a small `PeriodSeriesBuilder` under `Services/Assistants/Statistics`.
- Generate the requested calendar buckets once, index SQL results by entity/date,
  and merge into a zero-filled template for every selected entity.
- Keep periods chronological and entity ordering deterministic. Cover leading,
  internal, and trailing gaps and completely empty ranges.
- Calculate average and income from aggregate integer amount/count using a pure
  arithmetic helper if necessary. Test fractional results, ties, and overflow edges.
- Do not use SQL `generate_series` or introduce per-period database requests.

### 6. Expose resources and routes

- Add thin merchant/admin controllers, statistics resources/collections, and
  named routes under the existing Sanctum group in `routes/api.php`.
- Serialize only agreed fields, integer monetary values, metadata and entity
  pagination. An empty series still has every requested bucket.
- Document query parameters, creation-date semantics, successful-only selection,
  timezone, rounding, partial periods, and response examples alongside the API.
- No new audit events are required for read-only reporting by the task.

### 7. Verify database access and complete regression tests

- Inspect query plans using representative data before deciding on indexes.
  Candidate indexes cover payment status/date and terminal/status/date; choose
  based on actual queries rather than adding all candidates blindly.
- If justified, add indexes with a new reversible migration. No payment schema
  changes are otherwise planned under the creation-date assumption.
- Finish the test matrix below, run focused tests, then the full existing suite.
  Check route middleware and formatting on touched PHP files.

## Test matrix and acceptance criteria

- Day/month/year grouping for both endpoints and both admin groupings.
- Multiple payments per bucket; multiple terminals per user; multiple users.
- Correct sum, weighted user aggregation, average, and 10% income, including
  fractional tiyins and integer JSON output. Pending/failed payments contribute zero.
- Exact lower/upper range boundaries, partial months/years, midnight, month/year
  transitions, leap day, and a single-bucket range.
- Leading/internal/trailing gaps, no payments in range, no successful payments,
  a terminal with no payments, a user with no terminals, and no matching entities.
- Guest 401, nonprivileged admin request 403, permitted admin success, merchant
  isolation with and without an explicit terminal filter, and forged filters.
- Soft-deleted terminal history with ownership preserved.
- Invalid/missing period, dates, grouping, pagination and filter combinations;
  reversed or oversized ranges; missing selected entities.
- Deterministic ordering, complete buckets on each entity page, and bounded
  query counts as fixtures grow. No secret fields or database writes from requests.
- Permission migration behavior and actual route authorization, not only direct
  policy method calls. No changes to existing payment/withdrawal behavior.

## Validation commands

Run from `docker/`, using PostgreSQL with the isolated `test_schema` configuration:

```sh
docker compose exec -T -u www-data php php artisan test --filter=Statistics
docker compose exec -T -u www-data php php artisan test --filter=Period
docker compose exec -T -u www-data php composer test
docker compose exec -T -u www-data php php artisan route:list --path=statistics -v
```

Run `php vendor/bin/pint --test` through the same container prefix with an explicit
list of the added/modified PHP paths. Check migration rollback in the isolated
test environment only. Report executed checks and any failures accurately.

## Completion checklist

- [ ] Confirm or revise the proposed business/API defaults before implementation.
- [ ] Add failing tests, then implement validation and access control.
- [ ] Implement and test day/month/year strategies.
- [ ] Implement SQL aggregation and PHP gap filling.
- [ ] Add routes, controllers, resources, and API documentation.
- [ ] Complete edge-case tests and inspect query/index needs.
- [ ] Run focused/full checks and summarize results.

Planning note: existing instruction files still reference the former
`AI stats task specification/base-spec.md` location. The current base specification
was read from `AI specification/base-spec.md`; this plan does not modify the user's
instruction files or task text.
