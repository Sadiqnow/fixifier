# Database Developer: observed schema and implementation handoff

Status: proposal based on actual staging database inspection (user confirmed the local database is staging); no new migrations applied, no staging backup claimed. Read DEVELOPMENT_AUDIT.md, FIXIFIER_AGENT_BY_AGENT_FOUR_DAY_WORKPLAN.md, existing DAY1_DATABASE_PLAN.md and active migrations. This document extends the prior design with observed keys and finance persistence. SHARED_CONTRACT_V1.md remains authoritative for booking states.

## Environment and evidence

Observed endpoint: local XAMPP `127.0.0.1:3306`, database `mtechedg_fixifier`, MariaDB **10.4.32**. Laravel selects the mariadb driver. User confirmed this local database is staging during the review. The approved external backup directory is still requested. A separate cPanel deployment engine/version has not been verified.

`DATABASE_SCHEMA_OBSERVED.txt` records column types/defaults/nullability, all indexes, foreign-key references, engine/collation, statuses and migration batches from information_schema. It contains schema metadata and aggregate counts, not customer rows or passwords. This is an inventory, NOT a backup.

Five applied migrations: core + personal access tokens batch 1; cache + queue tables batch 2; technician service details batch 3. The disabled default users migration is inactive. All 15 tables are InnoDB with utf8mb4_unicode_ci in this inspection.

## Actual table/key map

| Table | Primary / unique keys | Other important indexes / relations |
|---|---|---|
| users | id; email; nullable phone | role; referenced by bookings, profiles, evidence, disputes, audit |
| technician_profiles | id; user_id | kyc_status; user FK cascade; service_location and starting_price_minor present |
| bookings | id; reference | customer_id/status, technician_id/status, service_category, status; both participant FKs to users |
| quotations | id; booking_id | booking FK cascade; one immutable history is not supported today |
| job_evidence | id | booking_id/type, technician_id FK index; booking FK cascade; no round association |
| disputes | id; booking_id | status, opened_by, resolved_by; user FKs and booking cascade; prevents another dispute even after resolution |
| payments | id; booking_id; provider_reference | status; booking cascade; no retry attempt/event identity |
| audit_events | id | action, subject_type/subject_id, actor_id; actor FK sets null; no append-only enforcement |
| personal_access_tokens | id; token | tokenable_type/tokenable_id, expires_at; polymorphic owner |
| migrations | id | migration and batch, no migration-name unique key |
| cache | key | expiration |
| cache_locks | key | expiration |
| jobs | id | queue |
| job_batches | string id | batch counters/payload fields |
| failed_jobs | id; uuid | queue/connection/exception, failed_at |

Status columns are strings, not database enums; no explicit allowed-status CHECK is declared by the active migrations. Booking enum: requested, quoted, confirmed, in_progress, evidence_submitted, completed, disputed, cancelled. Observed counts respectively 6,6,5,5,6,6,5,5 (44 total). Five disputes are open; payment states authorized=21/released=6; KYC pending=2/verified=2. These are demo values, not provider or KYC evidence.

Quotes use accepted_at/expires_at rather than status/version. Payment amounts are unsigned bigint minor units with NGN currency. Audit JSON is reported as longtext by MariaDB; that is not automatically a broken JSON column. Imported required timestamps captured_at and expires_at show CURRENT_TIMESTAMP defaults: review explicit timestamp defaults during fresh/upgrade comparison rather than assuming migration source exactly describes server defaults.

## Constraints and compatibility risks

1. Unique disputes.booking_id blocks repeated rounds; unique quotations.booking_id blocks version history. Replace only after same-booking round/version keys and supporting FK indexes are in place.
2. Keep payments as the legacy one-per-booking aggregate; add child attempts instead of overwriting a provider reference on retry. Existing globally unique provider_reference lacks provider/account/environment scope. Do not drop it until compatibility cutover.
3. Actual imported unique index names include `booking_id`, `reference`, `email`, `provider_reference`, not always Laravel-generated names such as disputes_booking_id_unique. Migration code must discover and verify index columns or support known names, failing on unexpected drift. Never drop an index by guessed name.
4. MySQL partial WHERE unique indexes are unavailable here. One-open-dispute-per-round can use a generated nullable key with unique index, but requires native MariaDB rehearsal. Closed rows produce NULL; open rows produce work_round_id. Enforce non-null round FK after backfill. Include booking_id in composite references to prevent cross-booking associations.
5. MariaDB and MySQL differ in JSON, generated-column DDL, CHECK enforcement by version and timestamp behavior. Target confirmed MariaDB 10.4 first; MySQL 8 compatibility is a proposed test matrix, not established evidence. Avoid reliance on PostgreSQL partial indexes or SQLite locking semantics.
6. DDL may commit independently. Split expand/backfill/constrain/cutover phases; do not promise a transaction rolls back a full schema deployment. Cyclic booking/current-round FKs are added after both tables exist and backfill validates.
7. Current SQL installer may mark alterations migrated without altering existing tables. Restrict it to empty-database installs; existing data upgrades must use reviewed Laravel migrations. Do not manually insert migration history to bypass errors.

## Proposed migration sequence (design, not executable migrations yet)

| Phase / proposed migration | Additions and integrity |
|---|---|
| 01 expand technician verification | kyc_submissions: technician FK, version, status, provenance demo/legacy/provider/manual_review, submitted_at; unique technician/version. kyc_documents: submission FK, type, disk/private path, MIME, bytes, SHA256, submitted_at. kyc_review_decisions: submission FK, reviewer FK, outcome, reason, decided_at, supersedes_decision_id; append-only. Profile active flag and selected approved submission reference. Eligibility requires genuine reviewed provenance, not a seeded verified label. |
| 02 expand work rounds and quote versions | booking_work_rounds: booking FK, round_number, status ready/in_progress/submitted/approved/disputed/rework_required, timestamps; unique booking/round_number and booking/id. Nullable current_work_round_id on bookings, nullable work_round_id on evidence and disputes. Quote version + status offered/accepted/rejected/expired/superseded; unique booking/version and booking/id; accepted_quotation_id on bookings. |
| 03 expand dispute decisions | disputes sequence per booking, round association, structured resolution outcome; dispute_decisions with dispute FK, admin FK, decision, reason, decided_at and unique decision request identity. Historical decisions immutable. Generated open_work_round_id uniqueness protects one open case per work round; replace old booking-only uniqueness at cutover. |
| 04 expand payment attempts | payment_attempts: booking/payment/quote references, attempt_number, provider, merchant account scope, environment, idempotency_key, request_hash, provider_reference nullable, amount_minor, currency, status, requested_at/confirmed_at/failed_at. Unique payment/attempt_number, unique scoped idempotency key and scoped provider reference; index status/updated_at. Bind quote and payment to same booking with composite keys. No fabricated successful attempt from legacy demo payment. |
| 05 provider events and application receipts | provider_events: scoped provider_event_id unique, received_at, signature verification result, payload hash, protected/minimized payload, processing status/retries/error, processed_at. Event identity is provider+account+environment+event ID. effect receipts unique event/effect key in same transaction as financial posting. Duplicate receipt causes no second effect; same event identity with different payload goes to reconciliation exception. Event arrival order does not define business truth. |
| 06 refunds and payouts | refunds: confirmed attempt FK, amount_minor, currency, reason/decision FK, idempotency_key, provider reference, status and request/confirmation timestamps. payouts: booking/payee/confirmed-source references, gross/fee/net minor amounts, currency, fee-policy snapshot, destination token reference (not raw bank secrets), idempotency key, provider ref, status/timestamps. Unique scoped operation identities; explicit retry attempt child records for ambiguous/retry requests. Multiple partial refunds allowed only by decided policy. |
| 07 audit/outbox/reconciliation | append-only decision/event records with correlation/request ID and outcome; outbox unique operation key, payload, available_at, attempts, lease, delivered_at; reconciliation_exceptions links provider events/attempt/refund/payout, reason, resolution record. Business action + audit/outbox insert atomic. Workers make external calls after commit and use same idempotency key on transport retry. |
| 08 backfill then constrain/cutover | deterministic round 1, quotation version 1, preserve all IDs/files and original states; classify unverifiable histories as legacy/incomplete. Validate nulls, duplicates, same-booking FKs and monetary identities before adding non-null constraints and removing old quote/dispute uniqueness. Retain old response fields while introducing explicit history/current selectors. |

Money rules: all amounts nonnegative integer minor units, currency immutable per financial operation. Refund cumulative success cannot exceed captured amount; payout cannot exceed eligible confirmed funds less confirmed refunds/fees/reserves. Those cross-row invariants require locked transactional calculations and provider verification, not just CHECK constraints. Serialize conflicting financial decisions on one parent aggregate, with a documented lock order booking → payment → attempt/operation; never call a provider under the lock.

Proposed financial states (must be ratified with provider): attempts initiated/pending/authorized/captured/failed/cancelled; refunds and payouts requested/pending/succeeded/failed/cancelled. Only externally verified evidence permits captured/succeeded. Authorization is not capture, approval is not payout, and a refund decision is not a successful refund. If provider semantics differ, update the shared contract before implementation.

## Backup and staging prerequisite

No staging changes were applied. Need staging endpoint, database name, database version and an approved backup directory outside every public document root. Obtain credentials via approved secret configuration, not chat or checked-in files. Take backups before any staging migration.

Method: pause relevant application writes, uploads and workers for a coordinated database/private-files snapshot; record timestamp, app revision, migration list, engine/sql_mode, row counts and file manifest. Use a compatible mysqldump/mariadb-dump with `--single-transaction --quick --routines --triggers --events --hex-blob --default-character-set=utf8mb4 --result-file=<outside-webroot-file>` and a protected `--defaults-extra-file` supplied as the first option. Use `--databases` only when restore naming is deliberately controlled; prefer a single-schema dump without embedded database creation for a separate restore target. Do not put passwords on the command line or pipe binary dumps through PowerShell text encoding. All tables observed are InnoDB; coordinate any future nontransactional tables separately.

Archive storage/app/private at the same write-freeze point; encrypt backup artifacts, restrict access and record SHA-256 checksums. The audit/schema inventory is not a substitute for these artifacts. Restore into a newly created isolated database and isolated private storage with the MariaDB client SOURCE command or native input redirection; never restore over staging as the rehearsal. Verify migration history, counts, FKs/orphans, evidence file hashes, selected sample API access and round backfill invariants. Record restore duration, operator and recovery point. Resume writes only after snapshot completion; retain verified backup per approved policy.

Rollback: before cutover, remove only unused expanded structures after verification. After multiple rounds, quote versions or financial records exist, old uniqueness cannot be safely reinstated. Prefer roll-forward; restore requires a write freeze and explicit reconciliation of post-backup activity. Do not describe a migration down() that discards history as a safe rollback.

## Decisions and Day 2 handoff

Lead must confirm staging and restore target, provider/account/environment identity, supported event identifiers, fee policy, partial refund and paid cancellation rules, payout timing, review window and KYC provenance criteria. Payment retry with unchanged request reuses the idempotency key; a materially different quote/payment intent requires a new identity. Same-key different payload must be a conflict.

Engineer: preserve scoped parent-row locks and atomic audits; change HasOne quote/dispute relations to explicit current selection plus history before relaxing uniqueness. Rework → confirmed + new ready work round; require new before evidence before start and current-round after evidence before submission. UI must carry expected round/version and handle 409 reloads.

Database owner: author migration files only after target contract review, then rehearse fresh and restored-existing-data paths on MariaDB. Prove: old records preserved; second dispute after rework succeeds; two open cases for one round fail; cross-booking round/quote links fail; repeated event/operation cannot post twice; failures roll back business/audit state; installer cannot falsely mark alterations applied. Run native-engine concurrent-connection tests in addition to SQLite tests.

Reserved branch `db/day2-rounds-kyc`; database owner coordinates app/Models with engineer per baseline ownership. No branch creation or merge claimed. Review package: this proposal, DATABASE_SCHEMA_OBSERVED.txt, prior DAY1_DATABASE_PLAN.md, shared state contract and backend isolation evidence. Staging inspection is complete. Backup and executable migration rehearsal remain pending the approved backup destination and restore setup, not silently marked complete.
