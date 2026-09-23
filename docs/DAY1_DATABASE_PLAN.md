# Day 1 database handoff

23 September 2026. **Design only: no schema, seeds, or database records changed by this handoff.** The shared Day 1 contract controls public status names and payloads. All proposals below require implementation and migration tests on Day 2; they are not existing capabilities.

## Inspected baseline

Sources: the five active PHP migrations, `app/Models/Booking.php`, `app/Enums/BookingStatus.php`, `database/fixifier_mysql.sql`, `DEVELOPMENT_AUDIT.md`, and the supplied four-day agent workplan. The default users migration is disabled. This review inspected schema source; it did not independently query the live database or verify a staging backup.

| Table/domain | Current source | Required additive target |
| --- | --- | --- |
| bookings | Customer/technician foreign keys, eight string states, participant/status indexes | Current work-round and accepted-quote references; preserve existing IDs/states |
| quotations | Unique booking ID; amount, currency, expiry, acceptance time | Immutable versions; unique booking/version; explicit selected/accepted version |
| job_evidence | Booking and technician IDs; type, private path, metadata/hash | Work-round association; index round/type; no old-round evidence satisfying current work |
| disputes | Unique booking ID; open/resolved state, text resolution and reviewer | Work-round association, per-booking dispute sequence, structured outcome, one open dispute per work round |
| technician_profiles | Unique user; KYC status/verified time; location/starting price | Account eligibility and evidence-backed review trail; active profile model |
| KYC | No active table | Private document metadata and append-only review decisions |
| payments | One payment/booking and unique provider reference | Day 3 attempts/events/refunds/payouts with quote identity and idempotency |
| audit_events | Actor/action/subject/JSON metadata | Atomic business action + audit insert; append-only application/DB access policy |

`Booking` currently exposes `quotation()` and `dispute()` as `HasOne`. Expanding the schema without changing these relations/serialization would silently select the wrong record. Coordinate `quotations()` / `disputes()` history and explicit current selections with the engineer; preserve compatibility fields until UI migration finishes.

The installer executes `CREATE TABLE IF NOT EXISTS` and then inserts migration-history rows. On an old schema this can mark the service-detail migration applied without adding its columns. Do not reimport it as an upgrade or insert history to silence pending migrations. Inspect actual columns/indexes against migration history first. Day 2 must define a fresh-install-only installer or generate it from the migration baseline.

## Proposed rounds and quotation schema

- `booking_work_rounds`: ID, booking FK, `round_number` unsigned integer, start/submission/closure timestamps, closure outcome; unique `(booking_id, round_number)` and unique `(booking_id, id)` for same-booking composite references.
- `bookings.current_work_round_id`: initially nullable; backfill round 1 and validate before constraining. Use a composite FK with booking ID or an equally tested constraint to prevent selecting another booking's round.
- `job_evidence.work_round_id` and `disputes.work_round_id`: initially nullable; composite `(booking_id, work_round_id)` references the work-round booking/ID pair. Preserve original evidence files and IDs.
- `quotations.version`: initially 1; replace unique booking ID only after adding unique `(booking_id, version)` and a booking FK-supporting index. Add quote status and supersession timestamps as agreed with the shared contract. Accepted versions are immutable. Serialize quote creation/revision/acceptance under a booking-row lock.
- `bookings.accepted_quotation_id`: nullable, constrained to the same booking; payment attempts reference this exact quotation ID and snapshot amount/currency. Do not charge against whichever quote happens to be latest.
- `disputes.round_number`: monotonically increasing per booking, independent of work-round number; unique `(booking_id, round_number)`. Existing dispute becomes sequence 1. Add structured resolution outcome and required decision reason without deleting existing text.

Keep booking states exactly `requested`, `quoted`, `confirmed`, `in_progress`, `evidence_submitted`, `completed`, `disputed`, `cancelled`. Rework creates work round N+1 and returns the booking to **confirmed**, then requires new before evidence before start. It must not jump directly to `in_progress`. Old evidence and resolved disputes remain historical. Payment authorization and booking state are separate facts; rework does not imply a second charge.

### One open dispute per work round

Proposed MariaDB implementation: generated nullable `open_work_round_id`, computed as `CASE WHEN status = 'open' THEN work_round_id ELSE NULL END`, with a unique index. Resolved rows have NULL, preserving multiple historical decisions while rejecting two open rows for the same round. `work_round_id` must be non-null once backfill completes. Verify generated-column syntax and index behavior against the installed MariaDB version in a disposable database before shipping; SQLite-only tests cannot establish this guarantee.

Lock the parent booking first for opening and resolving disputes, then load the current round/dispute; allocate the sequence under that lock. The generated key is a final database safeguard, not authorization. Add an explicit `(booking_id)` index before removing the old unique index so the FK remains supported. Resolution and round creation occur in one transaction with the audit event.

## Proposed KYC schema

`kyc_documents`: technician/user FK, document type, private disk/path, MIME, size, SHA-256, submitted time, submission/version ID and review status. No raw private storage path in public directory responses. `kyc_review_decisions`: document/submission FK, reviewer/admin FK, approved/rejected outcome, reason, decision time; preserve old decisions and files subject to an agreed retention policy. Derive profile approval from the latest approved submission and account eligibility. Existing seeded `verified` statuses are demo labels, never evidence that an actual review occurred. Plan a provenance field/backfill review queue rather than silently granting real eligibility.

## Migration, backfill and rollback sequence

1. Inventory the actual staging engine/version, table definitions, indexes, row counts, FK violations and migration history. Record drift; resolve deliberately before any upgrade.
2. Produce a timestamped database backup plus private evidence/KYC storage snapshot outside the public web root. Record checksum, source version, restore command and responsible operator. Restore into an isolated staging database and verify counts/relationships before accepting the backup. **No backup or restore rehearsal is claimed complete here.**
3. Add tables and nullable references first. Backfill each booking to round 1 without changing IDs, booking status, timestamps, money or file paths. Link existing evidence/dispute to round 1 and quote to version 1. Existing past rework cannot be reliably reconstructed from final state alone: flag legacy history as incomplete instead of inventing rounds.
4. Validate same-booking references, accepted-quote mappings, duplicate sequences and null references; report exceptions for review. Add final constraints only after validation. Keep backfill checkpointed and repeatable.
5. Deploy compatible relations/services and then relax unique booking-only quote/dispute indexes. Ensure old application workers are stopped during the compatibility cutover. MariaDB DDL may commit independently: separate schema phases from data transactions and use explicit recovery checkpoints.
6. Run fresh-install and upgraded-fixture tests, including second dispute after rework, duplicate open-dispute rejection, cross-booking round rejection, quote-version acceptance, and current-round-only evidence. Repeat relevant constraint/concurrency tests on MariaDB.
7. Rollback before writes to new histories can drop only new unused structures. After multiple versions/rounds exist, do not restore old unique booking indexes or drop history blindly. Roll forward or restore the verified backup with a controlled write freeze and a plan for post-backup writes. Reverting PHP alone is unsafe once the new schema is populated.

## Day 2 ownership and acceptance

Database workstream owns migrations, schema tests, installer consistency and coordinated seeder changes. Engineer owns models/services/transaction boundaries; UI owns rendering and API requests. Lead approves shared status/contract changes and migration rollout. Suggested branch: `day2/database-rounds-kyc`; the repository currently has no `.git`, so this is a reserved name, not a created branch.

Do not apply proposed migrations to the current local database as part of Day 1. Day 2 handoff requires a reviewed migration diff, baseline/target schema map, both fresh and upgrade test logs, backup/restore evidence, legacy-data exceptions, and a rollback decision. Day 1's booking-isolation regression gate belongs to the engineer/lead and is independent of this unimplemented design.
