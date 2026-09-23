# Day 1 review, decisions and Day 2 handoff

Lead sources: DEVELOPMENT_AUDIT.md and FIXIFIER_AGENT_BY_AGENT_FOUR_DAY_WORKPLAN.md. Repository and Downloads workplan copies have identical SHA-256 hashes. Contract authority: SHARED_CONTRACT_V1.md. Inventory/ownership: DAY1_BASELINE_AND_OWNERSHIP.md. Screen map: DAY1_SCREEN_MATRIX.md. Schema design: DAY1_DATABASE_PLAN.md.

## Status

Overall Day 1 remains **partially complete**, even after the isolation gate passes: no Git checkout/commit or separate branches exist; staging backup/restore and MariaDB concurrent-connection tests have not been performed. No live deployment, migration or money movement was performed. Required first merge gate results are recorded below after independent lead execution.

## Baseline evidence

- Active routes exported to DAY1_ROUTES.json; source-file SHA-256 manifest in DAY1_SOURCE_MANIFEST.json (post-fix handoff snapshot, not a Git commit).
- Five migrations applied. Local MariaDB 10.4.32, 15 application/infrastructure tables, 44 bookings, 5 disputes. Read-only inspection confirmed disputes still has a unique booking_id index. No schema change was made today.
- Baseline suite before new regression coverage: 8 passing tests, 96 assertions, recorded in development audit and prior audit run.
- Screen results: customer/technician/admin data and supported actions call real APIs. Standalone verification has no fetch/API calls and uses a global localStorage demo key; dashboard links omit booking ID.

## Engineer fix review

**Gate 0 PASS — independently rerun by Lead.** `php artisan test --filter=BookingIsolationTest --no-ansi`: 7 passed, 55 assertions. `php artisan test --no-ansi`: 15 passed, 151 assertions. Raw outputs: DAY1_ISOLATION_TEST_OUTPUT.txt and DAY1_FULL_TEST_OUTPUT.txt. Coverage includes full B attribute/payment snapshots unchanged for both A decisions, forbidden roles/other customer, repeated and competing sequential decisions, expiry boundary, stale route binding, and approval/dispute rollback on audit failure. This is not a simultaneous multi-connection MariaDB test. Code review accepted for the narrow local fix; no Git merge is claimed.

Engineer-owned files: WorkflowController.php and BookingIsolationTest.php. Lead reviewed the code and assertions independently.

Accepted design: approve/dispute/accept call a shared helper inside DB::transaction; helper uses Booking::query()->whereKey(bound ID)->lockForUpdate()->firstOrFail(), then checks freshly loaded customer ownership/role and expected state. Only the loaded instance is updated. Audit writes now occur in the same transaction for those three operations. Quote acceptance locks its quote and rejects expires_at <= now. Forbidden actor gets 403; stale state gets 409 with current_status and code after authorization. An existing dispute gets a deliberate 409 instead of a unique-key error until rounds are implemented.

The payment released flag retains legacy demo behavior; it is not provider-confirmed payout. Resolve, start, quote and evidence submission still require broader transaction/round hardening on Day 2. No claim of complete financial or concurrency correctness is made.

Engineer red evidence: initial regression run reported 5 failures / 10 assertions, including B changing to completed/disputed when A changed, expired quote accepted, audit failure not rolling back and incorrect forbidden response. Lead did not rerun destructive old behavior against live data.

## Issues and owners

| ID | Priority | Owner | Exit condition |
|---|---|---|---|
| D1-01 | Gate 0 | Engineer + Lead | Approval and dispute isolate A/B, duplicate/forbidden/rollback tests pass |
| D1-02 | Blocker | Lead / repository administrator | Recover Git source/remote and safe baseline commit; create reserved branches/worktrees |
| D1-03 | Blocker | Lead + DB | Identify staging, backup database/private files and verify restore before upgrade |
| D2-01 | High | DB + Engineer | Work rounds/quote versions/dispute history with fresh and existing-data migration evidence |
| D2-02 | High | Engineer + UI | Real KYC and active/approved-only assignment; seeded statuses cannot grant genuine approval |
| D2-03 | High | UI + Engineer | Booking-specific verification, current-round evidence, server-persisted review and forbidden/conflict states |
| D2-04 | High | Engineer | MariaDB multi-connection competing approval/dispute test; exactly one outcome and one audit event |
| D2-05 | High | DB | Installer cannot mark unapplied alterations as migrated |
| D3-01 | Blocker for finance | Product + Lead + Engineer | Provider/policy decisions and sandbox evidence; no invented real escrow |

## Decisions needed

| Decision | Owner | Impact while unresolved |
|---|---|---|
| Payment provider and capabilities: authorization, capture, split settlement, payout, refunds, signed callbacks | Product/Finance + Engineer | No real payment flow; compare capabilities before choosing architecture |
| Fees: percentage/fixed, rounding, tax, customer vs technician burden, when earned | Product/Finance | No fee calculation or net-earnings claim |
| Paid cancellation: pilot scope, cutoff, actor rights, penalty/partial charge | Product | No paid cancellation endpoint |
| Refunds: full/partial, fee recovery, evidence/approval rules, retries and operator | Product/Finance | Admin decision must not claim provider refund success |
| Review window: duration, reminders, escalation, automatic approval allowed or not | Product | No timeout-triggered approval/release |
| Pilot geography/services and genuine KYC approval criteria | Product + Lead | Demo categories/locations do not freeze pilot scope |

## Day 2 handoffs

1. DB prepares reviewed additive migrations and fixtures for current work rounds, quote versions, KYC documents/decisions and dispute rounds. Preserve IDs/history, test new and upgraded schemas, rehearse MariaDB constraints. Do not deploy until staging restore is evidenced.
2. Engineer implements policy-backed onboarding, eligible directory/assignment, before-photo-before-start and after-photo/current-round submission. Lock parent booking first for transitions, add atomic audit and round-aware conflict handling. No external calls inside DB transactions.
3. UI keeps current designs, replaces standalone verification localStorage authority with booking detail and review API calls, links each booking ID, renders real rounds/KYC and refresh-persistent states. Do not add unsupported payment claims or simulate customer approval from technician view.
4. Lead integrates DB/backend as one reviewed compatibility change, then UI. Run isolated MariaDB concurrency tests plus SQLite suite and an unpaid three-role browser walkthrough. Reserve branch/file ownership remains in effect until real Git isolation is available.

Day 2 acceptance: unapproved/inactive technician cannot be newly booked; unrelated customer cannot read/review evidence; rework requires fresh current-round evidence; repeated dispute preserves old decision; refresh and fresh sessions show server state; existing data survives upgrade. Payment/provider work remains explicitly sandbox/demo until decisions and confirmations exist.
