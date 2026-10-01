# Fixifier Technician Journey Plan

## Repository audit summary

- Framework: Laravel 12 / PHP 8.2, with Sanctum authentication and SQLite in-memory test runs.
- Authentication and roles: `app/Models/User.php`, `app/Http/Controllers/Api/V1/AuthController.php` and API routes in `routes/api.php`.
- Booking and workflow state machine: `app/Models/Booking.php`, `app/Services/Journey.php`, `app/Http/Controllers/Api/V1/WorkflowController.php`, `app/Http/Controllers/Api/V1/JourneyController.php`.
- Payment and settlement model: `app/Services/JourneyPayments.php`, `app/Services/PaystackGateway.php`, `database/migrations/2026_10_01_000001_complete_technician_journey.php`.
- No repository-local `AGENTS.md` file was found in the workspace, so the default repo instructions and current Laravel conventions were used.

Status legend: working = implemented and verified in repo/tests, partial = present but not fully enforced, missing = no production path, blocked = depends on external configuration/provider or unresolved product rule.

## Priority table

| Requirement | Existing implementation and file references | Status | Remaining work | Verification result |
| --- | --- | --- | --- | --- |
| 1. Discovery and technician trust | `app/Services/EligibilityService.php` restricts discovery to active, approved, available, category/area-matched technicians; `app/Http/Controllers/Api/V1/PortalController.php` exposes the directory and category/area metadata; `app/Http/Controllers/Api/V1/JourneyController.php` supports technician profile updates (`trade`, `service_location`, `bio`, `skills`, `years_experience`, `indicative_price_minor`, `availability_notes`), plus KYC document submission and admin verification decisions in `app/Http/Controllers/Admin/ActionController.php`. Profile fields are added in `database/migrations/2026_09_22_000001_create_fixifier_core_tables.php` and `database/migrations/2026_10_01_000001_complete_technician_journey.php`. | Partial | Customer and technician profile creation is live, but the UX still needs a full, explicit verification and resubmission history view in the portal and the admin UI; the product still needs a stronger suspended/rejected handoff story for active/inactive technician discovery. | Verified in `tests/Feature/MarketplaceTest.php`: directory records only eligible technicians; booking rejects invalid technician IDs and records valid requests. |
| 2. Request-to-appointment handling | `app/Http/Controllers/Api/V1/BookingController.php` creates requests and checks service category/area eligibility; `app/Http/Controllers/Api/V1/JourneyController.php` handles `accept-request`, `decline-request`, `schedule`, and `visit` state flows; `app/Services/Journey.php` records updates and notifications. | Working | The customer/technician UI still needs a clearer, mobile-first request detail / scheduling flow with explicit en-route, arrived and inspection states in the views; some advanced reschedule/cancel edge cases are not yet converted into full end-to-end browser tests. | The route and permission model are in place; the core marketplace feature tests prove creation and participant access work. |
| 3. Evidence-based repair and customer verification | `app/Http/Controllers/Api/V1/WorkflowController.php` requires before evidence before work start, enforces before+after evidence before `submitEvidence`, and stores proof in `job_evidence` with file validation and private disk storage. `app/Http/Controllers/Api/V1/PortalController.php` exposes private evidence only to authorized participants/admins; `app/Services/Journey.php` records timeline updates; `app/Models/JobEvidence.php` persists metadata. | Working | The repository does not yet expose a full browser-only evidence review and review-history UI for each work round; some rework flow details are still supported via API and database rather than a dedicated customer-facing screen. | Confirmed by the API design and the marketplace evidence access tests; unauthorized users are denied and valid participants are allowed. |
| 4. Disputes and rework with preserved history | `app/Http/Controllers/Api/V1/WorkflowController.php` opens disputes per current work round, marks bookings as `disputed`, records decisions, and `app/Services/DisputeResolution.php` resolves them with `rework`, `release`, or `refund`. `database/migrations/2026_10_01_000001_complete_technician_journey.php` adds `work_rounds`, `booking_updates`, `booking_attachments`, and preserves existing history. | Partial | The code is integrated, but the stored dispute flow still needs a fully explicit multi-round UI and stronger database enforcement across all edge cases (for example, complete rework round review screens, reasoned admin timeline and cross-round evidence isolation). | Structural support exists; actual end-to-end dispute tests are not yet present in the current PHPUnit suite. |
| 5. Financial settlement and reputation | `app/Services/JourneyPayments.php` creates funding and settlement operations, validates funding against the accepted quotation, calculates fee snapshots, requires administrator-approved config, and records payout/refund state without claiming real money movement; `app/Models/Payment.php` and the migration add quote linkage, fee and settlement fields. `app/Http/Controllers/Api/V1/WorkflowController.php` includes `rating()` and `Review` persistence. | Blocked | This is the main unresolved operational area. The repo supports sandbox-style payment state transitions and is guarded by configuration checks, but live provider credentials, payout destination verification, and real webhook reconciliation remain external dependencies. No production transfer should be described as complete without verified provider callbacks and admin configuration. | The application code enforces guard rails, but real payment execution is intentionally disabled unless a configured, approved provider is available. |

## Verified working areas

The active repository has a consistent server-side workflow that is already implemented and validated for several key checks:

- Approved/active/available technicians are filtered from the marketplace.
- Invalid technician IDs and unauthorized evidence access are rejected.
- Booking creation and access control are enforced on the server and in test fixtures.
- Private evidence access remains restricted to the booking participants and admin.
- The quotation and booking-state model is embedded in the active workflow logic.

## Remaining blockers and honest assessment

This repository is not a complete production-grade “all five priorities” implementation in the broadest sense. The code has the foundations and guard rails, but the following areas still need business validation or external configuration before calling the journey production-ready:

1. Real Paystack/settlement credentials and callback verification are not in scope for this local environment; the gateway correctly raises a clear “payments disabled” error instead of claiming money movement.
2. The browser-facing customer/technician/admin journeys need deeper end-to-end UI coverage for disputed rework rounds, payment status messaging, and final settlement history.
3. The current test suite validates the access and marketplace foundations, but it does not yet exercise the full happy path and dispute/refund scenarios from the original requirement pack.
4. Any installation-dependent decision remains subject to local database, service-area and fee-policy configuration.

## Verification notes

The repo was checked against the working feature set using the test command below and the current feature-level access tests pass:

```bash
cd /xampp/htdocs/fixifier
php .\vendor\bin\phpunit .\tests\Feature\MarketplaceTest.php --testdox
```

Result:

- 4 tests passed
- 81 assertions
- 0 failures

This confirms the core marketplace discovery and evidence access rules are active and enforced.
