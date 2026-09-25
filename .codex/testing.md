# Testing rules

- Follow TDD for behavior changes: failing test, implementation, then refactoring. The base specification requires full coverage of generated behavior; incomplete older tests do not justify testing only the happy path.
- Use PHPUnit. Laravel-dependent tests belong in `application/tests/Feature`, extend `Tests\TestCase`, and use `RefreshDatabase` for database tests. Prefer feature tests for framework-dependent services as well as endpoints.
- Reserve `application/tests/Unit` for framework-independent logic, extending `PHPUnit\Framework\TestCase`, following `SignatureServiceTest`.
- Use factories and explicit states. Follow descriptive `test...` methods and call `parent::setUp()` in overrides.
- Assert HTTP status, JSON, validation errors, database state and side effects. Rejected actions must not produce unintended writes, jobs or audit events.
- Cover applicable success, missing/invalid input, boundary amounts, missing resources, authentication, permissions/ownership, duplicates and state transitions. Cover webhook failures/retries and expiry where relevant. Control time instead of sleeping. Add regression tests for behavior fixes.
- Reuse `Tests\Traits\WithAuditLogs`: `assertLog` uses `false` to skip checking a parameter and `null` to require null. Test real audit persistence separately, following `OwnDBAuditLogServiceTest`.
- Fake HTTP, mail, queues or events at relevant boundaries. Never send real merchant requests/emails in tests. Do not mock the behavior under test.
- Preserve PostgreSQL test isolation. `phpunit.xml` sets `APP_ENV=testing` and `DB_SCHEMA=test_schema`; `config/database.php` uses the schema as PostgreSQL's search path, and `docker/postgres/init-schema.sql` creates it. Connection settings still depend on the environment: verify isolation before database-refresh tests. Do not switch to SQLite to bypass PostgreSQL transaction behavior.
- Test configuration uses array cache/session/mail and a sync queue. Account for these differences when testing jobs, cache locks or concurrency.
- Run focused tests during development and the full suite for completed behavior changes using `runtime.md`. Report blockers and pre-existing failures accurately; never claim unexecuted tests passed.
- Documentation-only changes need path/link and diff review, not new PHP tests.

Examples: `application/tests/Feature/PaymentCreationTest.php`, `application/tests/Feature/WithdrawalRequestTest.php`, `application/tests/Traits/WithAuditLogs.php`, `application/tests/Unit/SignatureServiceTest.php`.
