# Fixifier development audit

> Day 1 follow-up: the booking isolation defect and quote acceptance expiry are now fixed for approve/dispute/accept, with atomic audit writes. Independently verified: 7 isolation tests / 55 assertions and full suite 15 tests / 151 assertions. See DAY1_REVIEW_AND_HANDOFF.md. The original audit below remains historical; other risks, real MariaDB concurrency testing, rounds and payment integration remain outstanding.

Audit date: 23 September 2026. Scope: active Laravel code, routes, Blade views, JavaScript, migrations, seeders, local configuration and automated tests. This is a source-code and local-test audit, not a penetration test, browser accessibility assessment, payment-provider certification or production deployment assessment. No production workflow was executed during this audit.

## Overall assessment

Fixifier is a functional local prototype with a connected marketplace foundation. It is not ready for real customer payments or unattended operations. Planning estimate: **45% complete toward a production MVP**. This is a judgment based on working end-to-end capabilities and missing controls, not a measured code-coverage percentage or an average of the scores below. There is no signed-off feature specification, so scope and scores should be revised when requirements are approved.

Rating scale: 0% absent; 1–25% scaffold or demo; 26–50% partial implementation; 51–75% working core with material omissions; 76–99% substantially implemented and tested; 100% complete against agreed requirements and acceptance tests. A database table or a copied HTML page alone does not establish feature completion.

## Critical and high-priority findings

1. **Critical: booking updates are not scoped to a booking.** `app/Http/Controllers/Api/V1/WorkflowController.php`, methods `approve` and `dispute`, call `$b->lockForUpdate()->update(...)`. Laravel forwards the unknown instance method to a new query; that query has no booking-ID condition. The resulting update can change every booking's status. Confirmed by source inspection of the controller and installed Eloquent `Model::__call`; no destructive live-database reproduction was performed. Lock a query constrained with `whereKey($b->id)`, retrieve the row inside the transaction, check current ownership/state, and update that retrieved model. Add a regression test with at least two unrelated bookings before further workflow use.
2. **High: payments are database states, not money movement.** Quote acceptance confirms a booking without charging or creating a payment. Approval and dispute resolution change payment states without calling a provider. There are no active checkout, webhook, reconciliation, payout or refund integrations. Seeded payments use the demo provider. Do not describe these records as real escrow or completed transfers.
3. **High: concurrent workflow requests are insufficiently controlled.** Most state checks occur before transactions; several changes are nontransactional. Resolution does not lock/reload the dispute. Introduce transactional state transitions, constrained row locks, idempotency and concurrency tests.
4. **High: repeat disputes fail after rework.** The dispute table has a unique booking ID. Rework retains the resolved dispute, while raising another dispute attempts a new insert. Define dispute rounds or a deliberate reopen operation, preserving decision history.
5. **High: standalone verification is still a demo.** `resources/views/job-verification.blade.php` stores review state in browser localStorage. It has no booking binding or API integration. Its public route and dashboard links do not establish ownership. Preserve its appearance but replace sample data and actions with authenticated, booking-specific endpoints.
6. **High: KYC is not enforced.** The technician directory lists every technician; booking validation checks only the technician role. There is no active document-upload/review workflow, account suspension or verified-provider eligibility rule. Demo verified statuses are seeded values, not actual checks.
7. **High: deployment document-root risk.** XAMPP serves the project under `htdocs`; `index.php` redirects to `public`, but a redirect is not protection for other project files. `database/credentials` exists and is not excluded by the current `.gitignore`. Restrict the web document root to `public`, keep credentials outside public reach/version control, and check server denial rules. This audit did not request secret URLs or inspect credential contents. Actual HTTP exposure and repository tracking were not established.

## Model-by-model scorecard

Scores assess the complete domain capability around each model, including schema, API, interface, controls and tests—not merely the class declaration.

| Model / domain | Completion | Implemented | Missing / required |
|---|---:|---|---|
| User | 60% | Active model, hashed passwords, customer/technician registration, token login/logout, role casts | Password recovery, verified email flow, profile updates, suspension, session/device policy, admin provisioning, authentication tests |
| Booking | 50% | Active model, ownership-filtered list/detail, creation, technician assignment, status enum and core transitions | Critical update scoping fix, state-machine tests, cancellation/rescheduling, reassignment, eligibility rules, concurrency controls |
| Quotation | 55% | Active model, scoped technician submission, amount in minor units, scope/currency/expiry fields, customer acceptance | Expiry enforcement at acceptance, rejection/revision/versioning, race protection and acceptance tests |
| JobEvidence | 60% | Active model, private uploads, file validation, hash/metadata, participant/admin retrieval, both stages required for submission | Before-work sequencing, per-stage evidence rounds for rework, cleanup on failure, storage lifecycle, upload and transition tests |
| Dispute | 40% | Active model, customer creation, admin resolution, rework/release/refund state changes | Scoped booking fix, repeat-dispute design, locked resolution, reasoned history, notifications, provider operations and tests |
| Payment | 20% | Active model, payment-state records, booking relation, dashboard display, demo records | Real payment initiation/authorization, signed webhooks, refunds, payouts, reconciliation, idempotency, ledger and failure recovery |
| AuditEvent | 40% | Active model, workflow event creation, database persistence, admin viewer | Enforced append-only behavior, atomic event writes, coverage of auth/profile/KYC actions, retention/access policy, tamper detection |
| TechnicianProfile | 35% | Active table, seeded profiles, trade/bio/experience/KYC/location/price fields, read access through query builder | Root-level class not integrated under app/Models; no registration-time profile creation, edit API, eligibility controls; new location/price fields not displayed by current technician JS |
| KycDocument | 5% | Root-level draft class/controller | Active model, migration, protected document storage, submission/review API, decision history and UI |
| PaymentTransaction | 5% | Root-level draft class | Active model, ledger schema, provider event IDs, uniqueness/idempotency, reconciliation and audit links |
| Review | 5% | Root-level draft class/controller | Active migration/model/routes, completion/ownership checks, one-review rule, ratings aggregation, moderation and UI |
| ServiceCategory | 10% | Free-text booking category works; root-level draft model/controller exists | Active taxonomy model/table, technician-category pivot, category management, discovery/filtering and data migration |
| PersonalAccessToken (Sanctum) | 65% | Package model, migrated table, issued/revoked bearer tokens | Explicit expiry policy, device/session management, authentication throttling and tests |

Only seven application Eloquent models are active under `app/Models`: User, Booking, Quotation, JobEvidence, Dispute, Payment and AuditEvent. Composer maps `App\\` to `app/`. Root-level PHP drafts are not counted as integrated features; they also contain fields absent from current migrations. Cache/queue tables are infrastructure, not marketplace domain models.

## Module completion

| Module | Completion | Assessment |
|---|---:|---|
| Landing page and navigation | 80% | Blade homepage, named login/booking routes, service query preservation; privacy/terms links have no matching web routes; marketing verification/payment claims exceed implementation |
| Customer portal | 65% | Real booking creation/list/detail, quote acceptance, evidence review, approval and dispute actions; profile read-only; no cancellation, reviews, checkout or notifications |
| Technician portal | 60% | Blade dashboard, assigned jobs, quotes, start, private evidence uploads/submission, recorded payments; no profile editing/KYC submission/availability; location and starting price not shown |
| Admin portal | 55% | Admin-only data API, paginated users/jobs/disputes/payments/audit, evidence review and resolution; KYC read-only; no account management, category management or provider operations |
| Standalone job verification | 20% | Original design served by Blade, route and links exist; review/approval remains a browser-local simulation |
| Database and seeders | 75% | MariaDB connection, five applied migrations, relations/keys, repeatable local/testing demo seeder, 44 booking scenarios in seeder tests | Several missing feature tables, dispute schema limitation, schema-install upgrade hazard, missing domain constraints |
| Security and authorization | 40% | Sanctum-protected APIs, role/ownership checks, escaped UI values, private evidence responses | Public dashboard shells, verification demo not bound to owner, no explicit route throttles, token lifecycle, document-root and credentials hygiene, transition consistency |
| Payments and finance | 20% | Stored records and admin/customer/technician readouts | No provider integration or accounting-grade ledger |
| Notifications | 5% | Root-level draft controller; mail configured to log | Active notifications, event triggers, delivery channels, queue worker, retries and user preferences |
| Automated quality assurance | 25% | Eight passing tests, 96 assertions | No full booking lifecycle test, auth-flow tests, concurrency tests, provider tests, browser suite or CI verification found |
| Deployment/operations | 30% | Deployment guide, local runtime compatibility, private storage and health route | Verified secure deployment, backup/restore drill, monitoring/alerts, CI/CD, queue/scheduler operations and environment-specific readiness checks |

## Confirmed implementation details and limitations

- Landing `/`, customer `/portal`, admin `/admin`, technician `/technician`, booking `/book`, and standalone `/job-verification` routes exist.
- Admin and technician sign-in redirect to their own dashboards. API data has server-side authorization; the Blade shells themselves are public routes. This distinction does not mean protected API records are public.
- Token storage is sessionStorage. Login deletes existing tokens for that user. Installed Sanctum defaults to no token expiry; no application override was found.
- File validation permits JPEG/PNG/WebP up to 10 MB for evidence. Evidence is stored under private storage and served only to booking participants or admins.
- Job start does not require before photos, and evidence upload is allowed only after start. Therefore the app cannot claim enforced before-start evidence capture.
- Expiry is validated when creating a quote, but not when accepting it.
- Existing before/after evidence satisfies submission even after rework; a new evidence round is not required.
- Most audit writes happen after business transactions. An audit failure can leave a changed booking without its event. The AuditEvent model itself allows updates/deletes; README claims of immutability are stronger than implemented controls.
- Customer and technician dashboard totals cover the current page, not full-account aggregates. Admin overview counts are database-wide. Earnings display recorded releases without platform-fee or payout calculation.
- Standard registration creates a User only; a new technician does not receive an editable professional profile.
- The SQL installer uses CREATE TABLE IF NOT EXISTS and inserts migration history. Reimporting into an older schema can record the service-details migration without adding its columns. Use Laravel migrations for upgrades; restrict or redesign installer history insertion.
- Seeding is repeatable and environment-restricted, preserving existing booking edits. It seeds 8 demo users, 4 technician profiles and 44 bookings on a clean database. Existing demo booking records are skipped, so rerunning is not a repair tool for missing related records or evidence files. Quote dates can become stale in a long-lived demo.

## Validation performed

`php artisan test --no-ansi`: **8 tests passed, 96 assertions**. `php artisan migrate:status --no-ansi`: **all five migrations applied**.

Covered: landing/auth-page responses, booking intent, basic admin API access, directory filtering, technician-role booking validation, evidence ownership access, repeatable seed creation and preservation of one edited booking.

Not covered by these results: real authentication calls, full quote-to-completion workflow, real file upload validation, independent-booking state isolation, concurrent decisions, repeated rework/disputes, payment-provider behavior, UI interaction, deployment security. WorkflowContractTest checks README strings, not business transitions. Passing tests do not refute the critical controller defect.

## Recommended implementation order and acceptance gates

1. **Repair workflow integrity immediately.** Fix unscoped updates, lock/reload state inside transactions, enforce quote expiry, and define repeated-dispute/rework behavior. Gate: unrelated bookings remain unchanged; invalid roles/states fail; duplicate/concurrent actions cannot produce conflicting transitions.
2. **Complete the customer verification journey.** Bind the preserved page to a booking, load private evidence, restrict approval to its customer and resolution to admins, show real outcomes and errors. Gate: refresh persists server state, no localStorage approval authority, strangers cannot read or mutate the booking.
3. **Finish technician onboarding and KYC.** Add active TechnicianProfile/KycDocument models, profile editing, secure document review, verification decisions and booking eligibility. Expose location/price already migrated. Gate: new technician can complete onboarding; unapproved providers cannot receive bookings if verification is required by product policy.
4. **Implement real financial operations.** Select provider and settlement rules; add transaction ledger, signed/idempotent callbacks, verified authorization, refunds, payout/reconciliation and failure handling. Gate: sandbox end-to-end tests and duplicate webhook/retry tests pass before real payments.
5. **Complete marketplace operations.** Cancellation/rescheduling, category taxonomy, technician discovery/availability, notifications, ratings, account management and global aggregates. Gate: end-to-end customer/technician/admin acceptance scenarios pass.
6. **Harden and deploy.** Public-only document root, credential hygiene, authentication rate limits/token policy, immutable audit controls, backups/restoration, monitoring, CI, staging and rollback procedures. Gate: secure staging review and restore drill completed.

No application behavior or live data was changed by this audit. The critical findings remain outstanding and should be fixed before further live workflow use.
