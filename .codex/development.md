# Development rules

## Requirements

- Follow `AI stats task specification/base-spec.md`. Explicit requirements take precedence over inferred conventions; existing defects are not requirements.
- This is a mock payment system with simulated success/failure actions. Do not add real-money payment providers. Merchant webhooks remain part of the application.
- Money is integer tiyins (UZS) in inputs, storage, and calculations. Never use floating point money arithmetic. Preserve positive-integer amount validation.
- Code lives in `application/`, infrastructure in `docker/`. Respect Composer dependencies and the lockfile without incidental upgrades.

## Architecture

Standard Laravel application layout.
- Keep controllers thin: receive validated input, invoke services, return HTTP responses. Put business logic in `app/Services`, calling Eloquent directly. Do not introduce a repository layer.
- Use feature-specific FormRequests and their `toDTO()` pattern with typed readonly DTOs under `app/Data`. For new or changed DTO mappings, use validated, explicitly selected fields rather than copying older `all()` usage.
- Inject services through Laravel's container. Reuse `AuditLogContract` and `SignatureContract`; bind needed abstractions in `AppServiceProvider`.
- Keep relationships, casts, fillable metadata, status constants and query scopes on models. Follow existing attribute-based metadata where applicable. Use named status constants.
- Follow Sanctum authentication and policy/authorization middleware conventions. Verify actual route wiring: a class existing does not prove it protects an endpoint.
- Use existing API resources/collections for serialization. Preserve route names, response fields, pagination, headers and HTTP statuses unless the task changes the contract; test intentional changes.
- Extend `Services/Assistants/UserFilters` for user-list filters. Eager-load relationships used by list resources to avoid queries per item.
- Keep jobs and commands as entry points delegating business operations to services. Follow the existing Laravel bootstrap layout.

## Domain invariants

- Preserve payment transactions and row locks. Finalized payments retain their status without repeated side effects on subsequent requests.
- Withdrawal availability is paid payments minus pending/successful withdrawals for the terminal. Preserve ownership checks and concurrency protection.
- Preserve terminal-scoped order uniqueness and the `Idempotence-Key` header. Test duplicates, lock conflicts and failures when changing idempotence. Assess user/operation isolation rather than blindly copying current cache keys.
- Preserve the signature protocol: sorted payload keys, alternating key/value segments joined by `|`, HMAC-SHA256, and `hash_equals` verification.
- Record domain events through `AuditLogContract`. Exclude passwords, tokens, terminal secrets and card data from logs and ordinary responses. Preserve separately authorized secret access.
- Preserve signed merchant webhooks and tested retry behavior. When changing transaction/queue boundaries, ensure consumers cannot act on uncommitted state; existing in-transaction dispatch is not a universal template.

## Style and scope

- Follow `application/.editorconfig`: UTF-8, LF, four spaces, final newline and its YAML exceptions. Match surrounding PHP style without broad reformatting.
- Use PascalCase classes, descriptive camelCase methods, existing snake_case database/API/DTO fields, and useful parameter/return types. Do not rename public identifiers merely to correct spelling.
- Preserve useful training comments, including Russian comments. Explain intent and non-obvious behavior.
- Incomplete tests, inconsistent formatting, unused imports and logic gaps are not conventions to reproduce. Fix relevant defects with regression coverage and leave unrelated cleanup separate.
- Preserve user edits. Keep secrets, dependencies and generated runtime artifacts out of changes. Report actual validation performed.

## Evidence

Representative sources relative to `application/`:

- `app/Http/Controllers/PaymentCreationController.php`, `app/Http/Requests/Payment/PaymentCreationRequest.php`, `app/Data/Payments/PaymentData.php`, `app/Services/PaymentCreationService.php`.
- `app/Services/PaymentProcessingService.php`, `app/Services/WithdrawalRequestService.php`, `app/Services/SignatureService.php`.
- `app/Providers/AppServiceProvider.php`, `app/Policies/TerminalPolicy.php`, `routes/api.php`, `app/Http/Resources/PaymentAdminResource.php`.

Validated DTO mapping and committed-state queue handling above are improvement rules, not claims that all current code already follows them.
