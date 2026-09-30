# Admin portal integration

This branch adapts the supplied Fixifier admin journey to the existing Laravel 12 application. It keeps the Composer/PHP requirements, landing page, customer and technician portals, and the eight existing booking states. Admin sign-in uses `/admin/login`; customer sign-in remains `/login`.

## Update an existing local installation

Back up the database and private uploads before applying the migrations. In the VS Code PowerShell terminal:

```powershell
cd C:\xampp\htdocs\fixifier
git status
git switch feature/admin-portal
git pull --ff-only origin feature/admin-portal
composer install
php artisan migrate
php artisan db:seed --class=AdminMarketplaceSeeder
php artisan optimize:clear
php artisan test
```

If `git status` lists new local changes, commit them before pulling. Do not force-reset the working tree. Keep the current `.env`, `APP_KEY` and database credentials. Do not run `migrate:fresh` against an existing database.

Existing active admin accounts can sign in directly. To create another administrator interactively:

```powershell
php artisan fixifier:admin
php artisan serve
```

Open `http://127.0.0.1:8000/admin/login`. The command prompts privately for a password, confirms it, and refuses duplicate emails. Public registration cannot create admins. No default administrator password is added by this integration.

The existing demo seeder remains limited to local/testing. For a deliberate fresh local demonstration, `php artisan db:seed` still creates the existing demo marketplace and then imports its service catalog. Use **AdminMarketplaceSeeder only** for an existing production database; it imports existing service/area vocabulary and never overwrites configured settings or core records.

## Folders and database changes

| Concern | Location |
|---|---|
| Admin session login, portal and actions | `app/Http/Controllers/Admin/` |
| Catalog and settings controllers | `app/Http/Controllers/` |
| Eligibility, ranking, earnings, dispute resolution and view data | `app/Services/` |
| Role/session checks and validated forms | `app/Http/Middleware/`, `app/Http/Requests/` |
| Twelve pages, shared layout and controls | `resources/views/admin/`, `layouts/`, `partials/`, `components/` |
| Original admin stylesheet, Laravel additions and dialog script | `public/assets/css/`, `public/assets/js/` |
| Admin routes | `routes/admin.php`, included from `routes/web.php` |
| Additive schema | `database/migrations/2026_09_30_000001_add_admin_marketplace.php` |
| Repeatable settings/catalog setup | `database/seeders/AdminMarketplaceSeeder.php` |
| Regression coverage | `tests/Feature/AdminIntegrationTest.php` and existing feature tests |

The existing verification/work-round migration dated September 24 is retained. The new migration adds account activity, technician availability and verification versions, booking service area/version/settlement fields, service catalogs, marketplace settings, technician decision history, evidence notes and reviews. It reuses `users`, `technician_profiles`, `bookings`, `quotations`, `job_evidence`, `disputes`, `payments`, `kyc_documents` and `audit_events`.

Existing rows are preserved. Existing service areas are not guessed from addresses; the admin confirms the area when routing legacy unassigned requests. Deactivating a catalog item blocks new discovery/assignment without cancelling existing jobs. Technician approval requires stored private documents; identity/profile upload and editing are still outside this integration.

## Workflows and compatibility

- The stored API states remain `requested`, `quoted`, `confirmed`, `in_progress`, `evidence_submitted`, `completed`, `disputed` and `cancelled`. Admin page labels describe these states without introducing a second booking table.
- Requests can be assigned, returned to the queue or closed only before a quotation/payment commitment. Eligible technicians must match the active category and confirmed service area.
- A new job starts only after before evidence is uploaded. Submission requires before and after evidence for the current work round.
- Rework increments `current_work_round`, returns the existing state to `confirmed`, and retains every earlier evidence and dispute record. The technician uploads a new before record before starting again. Legacy jobs already in progress can supply a missing before record without being stranded.
- Technician writes and customer evidence decisions send `expected_work_round`. The backend rejects stale rounds; it requires the field after round one. Both shipped clients send it. Third-party API clients must add it before handling rework.
- Customer approval changes the job to `completed` and settlement to `release_pending`. An admin ruling similarly requests release or refund. Neither action writes `released_at`/`refunded_at` or claims a provider transfer.
- A customer can submit one 1–5 star review for their completed booking through the customer portal or `POST /api/v1/bookings/{id}/rating`. Moderation retains the original rating/comment and audits the reason. Only published reviews count toward ranking.
- Mutations use transactions and booking/profile locks. Admin forms include versions so an old form cannot override newer work. Private file downloads require an active admin session; existing API evidence access remains booking-scoped.
- Admin sessions and API bearer-token identities are independent, including when used in the same browser. Admin login is rate-limited; web writes require CSRF tokens.

## Operational limits

Escrow, automatic approval, dispatch timeout, fee schedules and payout timing are saved policy drafts/calculations. Saving them does not activate a payment provider, scheduled settlement, auto-dispatch, notifications or automated customer approval. Existing demo payment records remain simulations. Admins cannot simulate customer approval, technician quotations or provider callbacks from this integration.

Booking queues are paginated. Audit/payment panels show the latest 250 records, and booking timelines use the latest 1,000 audit entries; complete historical rows remain in the database. New review moderation shows the latest 250 reviews. No record is deleted by these display limits.

The application uses its existing `private` storage disk. For deployment, preserve that storage and keep it outside the public document root. The admin session requires a working persistent session driver. Use the existing file driver (`SESSION_DRIVER=file`) unless a database/Redis session backend is provisioned; the core SQL installer does not create a sessions table.

## QServers deployment

This branch has not been merged into `main` or deployed to QServers. Review locally before updating the live checkout. QServers' checkout path, document root and available Terminal/SSH access need to be confirmed before writing host-specific deployment commands.

For an approved production update, take a database/private-storage backup, install locked dependencies with `composer install --no-dev --optimize-autoloader`, then run `php artisan migrate --force`, `php artisan db:seed --class=AdminMarketplaceSeeder --force`, and `php artisan optimize:clear`. Keep the existing application key. Set production environment/debug/session-cookie values privately, use HTTPS and point the domain at Laravel's `public/` directory. No Node asset build is required for these admin pages.

Rollback must preserve the audit and work-round history. The new migration refuses to drop populated admin/history tables. Restore the reviewed backup when reverting schema; do not use destructive fresh migrations. The pre-integration branch commit is `6c9e3c5573890f522d0937dafd6a0bbd574a0b13`.

## Validation

Run `php vendor/bin/phpunit` (or `php artisan test`) against the isolated testing database. The suite covers the existing marketplace plus session login/logout, role restrictions, CSRF, throttling, all twelve pages with empty/seeded data, private documents, assignment isolation, stale versions, catalog eligibility, earnings validation, repeatable seeds, an upgrade preserving existing rows, customer-only ratings, audit rollback, and two dispute rounds with fresh evidence and pending settlement.

The integration workspace passed 34 tests with 462 assertions on PHP 8.3 and SQLite. A separate HTTP check verified session login/logout, CSRF rejection, all twelve page responses and asset loading. JavaScript syntax checks passed, and the original admin stylesheet is byte-identical to the converted source package. MySQL/MariaDB/PostgreSQL migration execution, concurrent database stress testing, visual browser comparison and live QServers behavior remain to be verified on those environments.
