# Fixifier Plus Laravel API

Production-oriented API foundation for the Fixifier service marketplace.

## Included

- Laravel 12 on PHP 8.2+, with PostgreSQL or MySQL/MariaDB.
- Sanctum token authentication.
- Customer, technician and administrator roles.
- Technician profile and KYC state.
- Booking, quotation and controlled job-state transitions.
- Mandatory `before` and `after` evidence records.
- Customer approval, disputes and payment settlement state.
- Immutable application audit events.
- PostgreSQL constraints and indexes.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=AdminMarketplaceSeeder
php artisan fixifier:admin
php artisan storage:link
php artisan serve
```

Configure a private S3-compatible disk before production. Never expose KYC or job evidence through unrestricted public URLs; return short-lived signed URLs instead.

## Core workflow

### Local database

XAMPP uses MariaDB on `127.0.0.1:3306`, database `mtechedg_fixifier`. The local `.env` selects Laravel's `mariadb` connection. Run `php artisan migrate` for schema updates; this preserves existing application records. The MySQL/MariaDB installer at `database/fixifier_mysql.sql` includes the core, Sanctum, cache and queue tables with migration tracking. Prefer migrations for existing databases: `CREATE TABLE IF NOT EXISTS` in the installer does not modify existing tables. File sessions/cache and synchronous queues remain configured locally.

For database diagnostics when XAMPP's intl extension is disabled, run `php -d extension=intl artisan db:show`.

`requested → quoted → confirmed → in_progress → evidence_submitted → completed`

Alternative paths: a request may be cancelled; submitted evidence may become disputed; an administrator resolves the dispute before money is released or refunded.

## Security decisions

- Authorization is enforced server-side through roles and record ownership.
- Technicians cannot complete jobs without both evidence types.
- Customers alone approve their completed work.
- Settlement updates run in database transactions with row locking.
- Audit events are append-only at application level.
- Payment gateway webhooks must be signature-verified and idempotent before deployment.

## Marketplace frontend

The application root `/` serves the supplied green-and-gold landing page from `resources/views/landing.blade.php`, preserving its design and responsive behavior. Login, registration, technician and service-search links connect to the marketplace using Laravel-generated URLs. The marketplace is available at `/portal`, `/login` and `/register`. No Node build is required for these pages. Register a customer or technician account, or sign in with an existing account; administrator access uses an existing administrator account.

The frontend connects to `/api/v1` for authentication, booking lists and creation, quotations, job progress, private evidence uploads and review, customer approval, and administrator dispute resolution. Session tokens are retained in sessionStorage for the current tab. Evidence images require authenticated booking ownership or administrator access. Booking summaries and payment records cover the current paginated result page.

Administrator sign-in is now `/admin/login`, using a separate Laravel session with CSRF protection. The 12 admin journey pages retain the supplied HTML/CSS design and operate on existing marketplace records. Admins configure services and areas, verify protected identity documents, route requests, review evidence, resolve work-round disputes and moderate ratings. Technician discovery and assignment require approved, active, available profiles in active services and areas. Customer and technician APIs keep bearer-token authentication. Completion records a pending settlement; it never proves a transfer. See [Admin portal integration](docs/ADMIN_PORTAL_INTEGRATION.md) for migrations, setup and operating limits.

Run `php artisan test` to validate the application, including marketplace authorization tests. Composer targets PHP 8.2.12 to match local XAMPP. Keep this platform setting when updating dependencies so the lock file remains compatible with the local runtime.
