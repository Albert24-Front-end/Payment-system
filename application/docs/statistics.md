# Payment statistics API

Statistics are read-only and use **currently paid payments grouped by creation
date** (`payments.created_at`), not by settlement date. Pending/failed payments
are excluded. All amounts are integer tiyins (UZS). SQL and calendar buckets use
UTC; the current PostgreSQL column stores UTC as `timestamp without time zone`.

## Terminal statistics

```http
GET /api/terminals/12/statistics?period=day&from=2026-09-01&to=2026-09-02
Accept: application/json
Authorization: Bearer <token>
```

Requires an authenticated, non-banned owner of the selected terminal. Another
owner receives 403; a nonexistent terminal receives 404. Soft-deleted terminals
remain available to their owners. Admin/support readers use the admin endpoint
for terminals they do not own.

Only `period`, `from`, and `to` are accepted. All three are required. `period` is
`day`, `month`, or `year`; dates must be real calendar dates in `YYYY-MM-DD` format
with `from <= to`. Unknown parameters (including user/terminal filters and
pagination) receive 422.

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

An empty terminal returns all requested periods with zero metrics. Payments from
other terminals are never included, even if they belong to the same merchant.

## Administrative statistics

```http
GET /api/admin/statistics?group_by=terminal&period=month&from=2026-09-15&to=2026-10-10&page=1&perPage=10
Accept: application/json
Authorization: Bearer <token>
```

Requires authentication, an active account, and `statistics.view` permission.
The new migration grants this permission to the existing `admin` role. The
project does not currently provision a support role or a general read-access
policy. A support role can be explicitly assigned this permission without write
permissions; role name alone grants nothing. Banned accounts are denied even
when they hold the permission. These restrictions apply to the new routes;
existing support/authentication routes are unchanged.

Required parameters are `group_by=terminal|user`, `period`, `from`, and `to`.
Optional entity pagination uses `page` (positive integer, at most 2147483647)
and `perPage` (1–100, default 10), matching the existing admin user-list endpoint.
The response metadata uses Laravel's `per_page` field. This adopts the plan's
instruction to follow existing pagination conventions instead of its fallback
`per_page=25` request example. No user/terminal filters are accepted.

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
  "meta": {
    "period": "month", "from": "2026-09-15", "to": "2026-10-10", "timezone": "UTC", "money_unit": "tiyin",
    "group_by": "terminal", "current_page": 1, "per_page": 10, "total": 1, "last_page": 1
  }
}
```

Entities are ordered by ID. `total` counts qualifying entities, not payments or
periods. Each entity has at least one paid payment in the exact requested range;
gaps inside its series are filled with zeros. No qualifying entities, or a page
past the last page, returns `data: []`. Each entity page contains complete series.

`entity_label` is the terminal name or user email, following existing admin
display conventions. Only these identifiers/display fields and metrics are
exposed. User grouping sums across terminals of the current owner, including
soft-deleted terminals; it does not reconstruct historical ownership.

## Boundaries and calculations

- Both dates are inclusive: query from midnight on `from` to, but not including,
  midnight after `to`.
- Bucket keys are the day's date, the month's first date, or January 1. Partial
  months/years include only payments in the exact requested range.
- At most 366 calendar buckets are allowed for any strategy. An excessive range
  receives 422 rather than silently truncated data.
- `payment_count` is the number of paid payments; `payment_amount` is their sum.
- `average_check` is amount/count; `income` is amount/10. Round once per entity
  and period to whole tiyins, with ties rounded up, using integer arithmetic.
  Empty periods have zero metrics.
- User income comes from the combined unrounded amount, not from summing rounded
  terminal income. For example, two terminals with 4 tiyins each yield 1 tiyin
  of user income even though each terminal rounds to zero.
- Income is a reporting estimate; it does not charge fees or modify balances.
- Aggregates must fit a nonnegative PHP integer (64-bit in the development
  container). An out-of-range aggregate raises an overflow error rather than
  emitting an inaccurate floating-point amount; reduce the report range if needed.

## Deployment and verification

The existing `2026_09_23_122150_add_status_to_users_table` migration must also be
applied so banned-user checks have their status column. Both it and the new
permission migration were applied to the local development database during
implementation. For another environment, run from `docker/`:

```sh
docker compose exec -T -u www-data php php artisan migrate --path=database/migrations/2026_09_23_122150_add_status_to_users_table.php --path=database/migrations/2026_09_25_120000_add_statistics_view_permission.php
docker compose exec -T -u www-data php php artisan route:list --path=statistics -v
docker compose exec -T -u www-data php php artisan test --filter=Statistics
docker compose exec -T -u www-data php composer test
```

Tests use the isolated PostgreSQL `test_schema`. The permission migration's
up/down behavior is covered there; never use a development database rollback as
a test. New period support requires a `PeriodStrategy` implementation and a
registry entry in `PeriodStrategyFactory`; validation shares that registry.

The services aggregate in SQL and fill gaps in PHP, with one merchant aggregate
query and three admin queries for a populated page (count, entities, aggregates).
A local query-plan review with 10,000 temporary payments across 100 terminals
used the existing terminal/order index for merchant reporting and inexpensive
scans for administrative reports. No new index was justified at that scale;
reassess with larger representative data if report latency grows.
