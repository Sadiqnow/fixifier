# Fixifier Plus — four-day integration workplan

Date: 23 September 2026. Baseline: DEVELOPMENT_AUDIT.md and prior Fixifier discussions. Goal: demonstrate one safe, server-authoritative transaction from approved technician discovery to customer decision and recorded settlement outcome. Four days are a focused integration sprint, not a guarantee of production launch or a complete implementation of the broader corporate/wallet roadmap.

## Sprint boundaries and prerequisites

- Use the active audited Laravel repository as the only source of application truth. Inventory the HTML/ZIP artifacts and port only needed screens into the active app; do not replace the current UI wholesale.
- Work in a branch and staging database with anonymized or seeded records. Back up before migrations. Stop any real-money use until provider flows, security, and reconciliation pass.
- Appoint one lead developer as merge/release owner; one backend engineer for workflows and payments; one database engineer for migrations and constraints; one UI engineer for portal integration. With fewer developers, preserve sequence rather than assuming parallel throughput.
- Before starting, decide the initial service area/categories, whether phone/email verification is required for pilot, the payment provider and credentials, commission and payout rules, cancellation/refund policy, and customer review window. Unresolved payment policy means the four-day financial result is sandbox-only.
- Agree status contract: technician verification, booking, quote version, work round, dispute round, payment attempt, refund, and payout. Keep database payment state distinct from externally verified money movement.

## Day 1 — repair integrity and establish the contract

| Owner | Work | Evidence required by end of day |
| --- | --- | --- |
| Lead | Confirm audited branch/environment; reconcile actual routes/views/models with standalone designs and API ZIP claims; freeze scope and status contract; assign API/UI contracts. | Route-to-screen map; prioritized issue list; agreed transition table; staging baseline. |
| Backend | Fix `WorkflowController::approve` and `::dispute` to lock and update only the selected booking inside a transaction. Recheck actor and current state after lock. Add conflict handling and quote-expiry validation. | Two-booking isolation regression; unauthorized/invalid-state cases; simultaneous decision case where feasible. |
| Database | Inspect current MariaDB schema and migration history; plan safe additive migrations for work/dispute rounds, quote versions and unique provider references. Remove incompatible `unique(booking_id)` dispute constraint only through reviewed migration. | Migration plan and rollback/backup notes; clean and existing-schema migration checks. |
| UI | Inventory customer, technician, admin, verification pages and identify `localStorage` mock actions and missing API calls. | Screen-to-endpoint checklist showing every simulated action. |

**Gate 1:** Approval/dispute actions cannot modify unrelated bookings. If this fails, no further workflow demo or release.

## Day 2 — bind identity, discovery and job verification

| Owner | Work | Evidence required by end of day |
| --- | --- | --- |
| Backend | Add registration-time technician profile, private KYC submission and admin decision endpoints; enforce approved/active technician eligibility in discovery and booking. Implement work-round evidence rules: before evidence precedes start; new after evidence is required after rework. | Requests by unapproved technicians rejected; before/after order tests; private document/evidence access tests. |
| Database | Add KYC documents and decision history; migration for work/dispute rounds and evidence linkage; indexed eligibility fields. Migrate existing demo states deliberately, without treating seeded approval as verified. | Migrations and sample data validated; old bookings retain readable history. |
| UI | Bind technician profile/KYC and admin review to real APIs; show only eligible providers to customers. Replace verification page sample data/local approval state with booking-scoped evidence and authenticated approve/dispute requests. | Refresh preserves server decision; other users cannot see or decide the booking; error/loading states work. |
| Lead | Review role matrix and endpoint contracts; merge only after end-to-end browser walkthrough of one unpaid scenario. | Recorded walkthrough and defects triaged. |

**Gate 2:** One approved technician can be booked; an unapproved technician cannot. Customer verification is server-authoritative. If KYC scope is too large, run a controlled manual admin verification process backed by an auditable server decision; do not display unverified providers as verified.

## Day 3 — financial integration and dispute completion

| Owner | Work | Evidence required by end of day |
| --- | --- | --- |
| Backend | Integrate provider sandbox checkout/confirmation, signed callbacks, exact booking/quote/amount/currency checks, idempotency, pending/failure states. Implement reasoned dispute decisions, repeat round after rework, and refund/payout requests if provider capability and commercial rules are confirmed. | Duplicate/invalid/late webhook cases; exact amount match; second dispute after rework; provider sandbox transaction record. |
| Database | Add payment attempts, provider events, payout/refund records and unique references; append-only financial/audit trail written atomically with state changes. Include reconciliation exception records. | Migration checks; duplicate reference rejected; ledger totals and events tied to one booking. |
| UI | Show authoritative payment and settlement state; separate pending from confirmed; show dispute history, decision reason and current rework round. Remove unsupported “escrow released” claims. | Customer, technician and admin views agree after refresh. |
| Lead | Validate provider capability and operational semantics with actual sandbox evidence. Decide whether financial scope qualifies for pilot. | Signed provider/settlement decision and known exceptions. |

**Gate 3:** A database status alone cannot mark a charge, payout, or refund successful. If provider credentials, legal/commercial rules or integration time are insufficient, use explicit **sandbox/demo** labels and move live-money release to a later gate; continue the nonfinancial workflow.

## Day 4 — full walkthrough, hardening and release decision

| Owner | Work | Evidence required by end of day |
| --- | --- | --- |
| Whole team | Run customer registration → approved technician selection → booking → quote → confirmed payment (sandbox) → before photo → start → after photo → submit → approval → settlement confirmation; repeat with dispute → rework → second dispute → refund/release. | Recorded scenario results, screenshots/log references and defect list. |
| Backend/QA | Run authorization, independent booking, quote expiry, concurrency, duplicate callback, upload permission and failure recovery tests; fix release blockers. | Automated test results plus manual browser checks. |
| UI | Check responsive customer/technician/admin flows, loading errors, currency/status wording, no simulated actions, no false verification/escrow claims. | Three-role UAT checklist. |
| Lead/operations | Deploy to staging with document root at Laravel `public`; verify PHP version, vendor files, writable directories, migrations, queues and logs. Check secrets/version control, backup/restore and rollback. For cPanel without terminal, prepare tested deployment artifact locally and execute migrations through a controlled mechanism; never expose an unrestricted web migration endpoint. | Staging smoke test; backup/restore evidence; go/no-go record with blockers and owner. |

**Gate 4:** Pilot go only when security, workflow isolation, identity checks and transaction evidence pass. Real-money go requires successful live-provider and reconciliation validation beyond a sandbox demo.

## Definition of done

1. One booking's transition cannot change any other booking, and each actor sees/changes only authorized resources.
2. A newly registered technician cannot be booked until approved; KYC review decision and reason persist.
3. The exact accepted quote version is unexpired and matches the payment attempt; duplicate commands/callbacks cannot produce duplicate effects.
4. Work starts only after current-round before evidence; completion needs current-round after evidence; the customer page reads real server state.
5. A second dispute after rework works without losing the first ruling.
6. Charge, payout and refund are described as confirmed only when verified by provider; exceptions are visible to admin.
7. The staging deployment passes role-based browser walkthrough, automated tests, secure web-root check and restore check.

## Not promised in four days

Corporate SLA/billing, subscriptions, warranties, spare-parts sales, AI matching, native apps, nationwide scaling, full wallet custody, chat, emergency workflows, or production certification of a payment provider. Reviews, cancellation after payment, and notification delivery need explicit scope/ownership if included in a pilot. A four-day sprint may produce a release candidate; production approval depends on gates, not the calendar.

## Daily communication

Morning: blockers/decisions (15 minutes). Midday: merge and endpoint contract review. End of day: demonstrate one concrete scenario, record evidence, move unresolved items into the next day with named owner. Do not mark a feature done from a design mockup, migration or API response alone.
