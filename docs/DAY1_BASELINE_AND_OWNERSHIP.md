# Day 1 baseline and ownership

Source: `C:/xampp/htdocs/fixifier`. Local Laravel working tree, PHP 8.2.12-compatible dependencies, MariaDB application connection. Tests use SQLite :memory:. No `.git` directory; `git` not on PATH and not found at standard Program Files Git path. No commit SHA/remote/worktree or real merge can be identified. Do not initialize/commit the whole folder blindly: root drafts, credentials and vendor files require a reviewed import. Branches below are RESERVED, not created. Git provisioning and source repository recovery remain blockers to actual branch isolation.

## Inventory

- Active controllers under `app/Http/Controllers/Api/V1`: AuthController, BookingController, WorkflowController, PortalController, AdminPortalController. Base Controller under app/Http/Controllers.
- Active models under app/Models: User, Booking, Quotation, JobEvidence, Dispute, Payment, AuditEvent. Sanctum token model supplied by vendor. Technician profiles currently query-builder based.
- Requests: CreateBookingRequest, SubmitQuotationRequest. Enums: BookingStatus, UserRole. Service: Audit. Provider: AppServiceProvider.
- Five applied migrations: 0001_01_01_000001_create_cache_table; 0001_01_01_000002_create_jobs_table; 2026_09_22_000001_create_fixifier_core_tables; 2026_09_22_072821_create_personal_access_tokens_table; 2026_09_23_000001_add_technician_service_details. Disabled default users migration is not active.
- Core tables: users, technician_profiles, bookings, quotations, job_evidence, disputes, payments, audit_events, personal_access_tokens; infrastructure migrations/cache/cache_locks/jobs/job_batches/failed_jobs.
- Views: landing, marketplace, admin, technician, job-verification; welcome is unused by listed application web routes. Active browser scripts: public/js/marketplace.js, admin.js, technician.js; verification has embedded demo script.
- Baseline tests: Unit/ExampleTest, Feature/ExampleTest, MarketplaceTest, DemoSeederTest, WorkflowContractTest; Tests/TestCase. New engineer-owned BookingIsolationTest is the first gate.
- Seeders: DatabaseSeeder calls DemoMarketplaceSeeder; local/testing only, 8 demo users/44 bookings on fresh test DB. Root-level model/controller files are drafts, not production source under Composer's App namespace mapping.
- `routes/console.php` exists. No staged release or staging database credentials were supplied. No staging backup/restore is claimed. No live database migration is part of this Day 1 change.

## Route inventory

Web views: `/`→landing; `/portal`, `/login`, `/register`, `/book`, `/technician/login`, `/technician/register`→marketplace; `/admin`→admin; `/technician`→technician; `/job-verification`→standalone verification. These are public shells; API authorization protects live data.

API routes, payloads and actors are enumerated in SHARED_CONTRACT_V1.md. Public auth/register and auth/login; all other application API routes auth:sanctum. Framework routes: `/up`, `/sanctum/csrf-cookie`, GET/PUT `/storage/{path}`. Framework storage routes require separate signed-route/storage review; their existence is not evidence that private evidence is anonymously accessible.

## Exclusive ownership and reserved branches

| Workstream | Reserved branch | Exclusive files / responsibility |
|---|---|---|
| Lead | lead/day1-contract-baseline | docs/SHARED_CONTRACT_V1.md, this baseline, DAY1_REVIEW_AND_HANDOFF.md; routes/*.php integration, bootstrap/config/composer/test configuration; final gate and review |
| Software Engineer | fix/day1-booking-isolation | app/Http/Controllers/Api/V1/WorkflowController.php and tests/Feature/BookingIsolationTest.php on Day 1; app/Http/Requests, policies/services and backend feature tests on Day 2 |
| Database developer | db/day2-rounds-kyc | docs/DAY1_DATABASE_PLAN.md; database/migrations, seeders, SQL installer, app/Models and dedicated migration tests after contract approval |
| UI expert | ui/day2-verification | docs/DAY1_SCREEN_MATRIX.md; resources/views, public/js, public/css and browser tests after endpoint agreement |

Agents currently share one filesystem; only disjoint file ownership is operational. No two agents may edit the same file. Backend requests model changes from DB owner; UI requests route changes from Lead. Commit boundaries once Git is available: baseline import excluding secrets → regression test showing red → scoped fix showing green → contract/schema/UI independently reviewed. Never retroactively claim current work happened on separate branches.

## Merge windows and gates

Morning contract sync; midday backend/schema compatibility review; end-of-day integrated tests and demo. First gate: A/B isolation tests for approval and dispute with B payment unaffected, forbidden actors, duplicate decisions, atomic audit rollback, quote expiry. Second gate: full existing suite. Production MariaDB concurrency requires a dedicated isolated test database and separate connections; SQLite results do not prove row-lock concurrency behavior.

Lead review must inspect the actual diff-equivalent code, constrained lock location, authorization order, transaction closure, audit placement and regression assertions. No unscoped booking update or speculative finance change is accepted. Remaining workflow paths must be listed as unresolved rather than included in the narrow fix claim.
