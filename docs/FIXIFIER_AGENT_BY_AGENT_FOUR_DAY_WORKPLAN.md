# Fixifier Plus — agent-by-agent four-day workplan

23 September 2026 · Companion to `FIXIFIER_FOUR_DAY_INTEGRATION_WORKPLAN.md` · Baseline: `DEVELOPMENT_AUDIT.md`

## Shared mission

Bind the existing audited Laravel app and its current visual designs into one server-authoritative pilot journey: approved technician → booking → valid quote → provider-confirmed payment in sandbox → before evidence → work → after evidence → customer approval or dispute → verified payout/refund outcome. Every agent works against the same active repository and the same versioned state/API contract. A four-day sprint is a release-candidate target, not automatic live-money authorization.

## Ownership and working agreement

| Agent | Owns | Must coordinate before changing |
| --- | --- | --- |
| Lead developer | Repository baseline, product decisions, API/state contract, integration, review, CI/staging, release gates | All migrations, status names, finance wording, merges |
| Expert software engineer | Laravel controllers/services/policies, state transitions, KYC/payment integration, backend tests | Schema with database agent; request/response contracts with UI agent |
| Database developer | Migrations, keys/indexes, data repair/backfill, work/dispute rounds, financial event persistence and migration tests | Domain statuses and transaction boundaries with engineer; deployment with lead |
| UI expert | Existing Blade/JS/HTML integration across four screens, API calls, accessibility and role-based browser UAT | Endpoint contract with engineer; status labels and product claims with lead |

Each agent uses a separate branch or worktree. No agent independently edits another agent's owned files without coordinating. Short daily merge windows: morning contract sync, midday integration, end-of-day demo. Lead resolves conflicts and runs the full suite. Avoid replacing the UI design or treating standalone ZIP/HTML artifacts as proof of integrated functionality.

## Agent 1 — Lead developer

**Outcome:** One coherent, reviewable build with documented go/no-go evidence.

| Day | Tasks | Deliverable / acceptance |
| --- | --- | --- |
| 1 | Check out actual audited repo and commit; compare active routes/models/views to prior ZIP/HTML artifacts. Freeze pilot services/area, roles, status names and the endpoint contract. Record provider, fee, cancellation and review-window decisions or mark them unresolved. Create four workstreams and staging baseline. | Repository inventory; state/API contract; issue tracker with owners; staging database backup; evidence that critical fix has its own gate. |
| 2 | Review engineer/database changes as a unit. Enforce approved-only discovery and booking, real KYC decisions, work-round evidence, booking-specific verification. Merge only after role/ownership review. | One unpaid browser walkthrough with customer, technician and admin; recorded defects; successful migration on existing and fresh data. |
| 3 | Verify sandbox provider capabilities, secret handling, callback model, exact financial language and reconciliation expectations. Ensure provider-independent workflow does not claim money movement. | Provider decision record, sandbox evidence or explicitly demo-only result; accepted dispute/rework contract. |
| 4 | Run cross-role acceptance, security and regression gates; stage release with Laravel `public` web root. Check PHP version/vendor files/storage permissions, queue/log setup, credential hygiene, migration backup/rollback, restore test. For cPanel without terminal, build dependencies elsewhere and use a controlled deployment/migration method. | Versioned release candidate, test and browser evidence, blocker list, rollback plan, written pilot go/no-go. |

**Must reject:** A green API homepage as proof of app completion; fake KYC badges; payment statuses presented as real transfers; unresolved unrelated-booking update defect; unaudited production migrations.

## Agent 2 — Expert software engineer

**Outcome:** Safe Laravel domain actions and provider-aware financial workflow.

| Day | Tasks | Deliverable / acceptance |
| --- | --- | --- |
| 1 | Repair `WorkflowController::approve` and `::dispute`: constrain row lookup by booking ID, lock inside transaction, reload/check owner and state, update only that row, write audit event atomically. Enforce quote expiry on acceptance. Add 2-booking isolation, forbidden-role and duplicate/concurrent decision regression tests. | Booking B unchanged when A is approved/disputed; invalid transition returns conflict and current status; tests pass. |
| 2 | Add technician profile creation/editing, private KYC submission and admin decisions with policy checks; filter bookable discovery and booking assignment to approved/active providers. Enforce before photo before start; after photo for current round before submission; provide booking-scoped evidence/approval/dispute endpoints for UI. | New unapproved technician cannot be booked; other customer cannot access evidence; refresh shows persisted verification action; rework requires new evidence. |
| 3 | Integrate actual chosen provider in sandbox: initiate payment against accepted quote version; verify signed callbacks, provider reference, amount/currency and current state; idempotent retries. Support second dispute after rework and reasoned resolution. Add refund/payout requests and confirmation handlers only when provider/policy permit. | Signed callback tests, duplicate and wrong-amount rejection, delayed callback handling; second dispute succeeds; no status claims external success before confirmation. |
| 4 | Run complete happy/dispute/refund paths, authorization and concurrency cases; fix blockers; add logs/alerts for stuck payments and failures; support staging smoke test. | Passing relevant suite and reproducible API scenarios; documented remaining provider exceptions. |

**Boundaries:** Do not fabricate escrow by toggling a `payments.status` field. Avoid an unrestricted migration or maintenance HTTP route. Keep external calls out of open DB transactions; coordinate outbox/retry design with database agent.

## Agent 3 — Database developer

**Outcome:** Schema that preserves identities, rounds, and financial history without corrupting existing data.

| Day | Tasks | Deliverable / acceptance |
| --- | --- | --- |
| 1 | Inspect all applied Laravel migrations and real MariaDB schema, including installer-history mismatch risk. Design incremental migrations for KYC decisions, work rounds, quote versions and disputes; specify unique keys and indexes. Back up staging and rehearse migrations. | Before/after schema map; additive migration scripts and rollback notes; no premature migration-history insertion. |
| 2 | Implement technician profile creation support; `kyc_documents` with private storage metadata, status and reviewer trail; work-round/evidence association; replace one-dispute-per-booking constraint with one-open-dispute-per-round enforcement compatible with MariaDB. Preserve existing bookings and seeded records without treating seeded KYC as verified. | Fresh install and existing-data upgrade succeed; second dispute can be stored; prior decision stays intact; private document references persist. |
| 3 | Implement payment attempts/provider events with unique references and idempotency keys, payout/refund records, event/audit history and reconciliation exceptions. Document which records are financial facts versus pending intentions. Verify transactional consistency and fees in integer minor units. | Duplicate provider event cannot post twice; booking amount/currency/quote reference recorded; no orphan transition on simulated failure. |
| 4 | Check indexes/query performance on seeded data, migration repeatability, backup/restore, and verification/finance history access. Deliver schema diagram and data dictionary to lead. | Restore succeeds; clean and upgrade paths tested; no unreviewed destructive change. |

**Boundaries:** Schema cannot alone enforce authorization or prove payment. Ask the software engineer to enforce policies and provider checks at transition time. Coordinate audit atomicity in the same transaction as the business update.

## Agent 4 — UI expert

**Outcome:** Current designs display and submit real, role-appropriate state rather than local demos.

| Day | Tasks | Deliverable / acceptance |
| --- | --- | --- |
| 1 | Inventory current landing, marketplace, booking, customer, technician, admin and verification pages. Mark every hard-coded job, seeded status, `localStorage` action, missing API call and unsupported claim. Agree route/API field contract with lead and engineer. | Screen-to-endpoint matrix; prioritized integration list; design preserved. |
| 2 | Connect technician profile/KYC submission, admin review, eligible directory, booking and current-round evidence to APIs. Convert job-verification page to authenticated booking-specific load, approval and dispute mutations. Implement loading, forbidden, empty and conflict states. | Customer refresh retains result; wrong user cannot access job; verified badge reflects actual approval; browser-local state cannot approve. |
| 3 | Render latest quote version and expiry, provider-confirmed versus pending payment, current evidence/dispute round, admin reason and exception statuses. Remove misleading escrow/payout language until externally verified. | Customer, technician and admin see consistent state; second rework round displays its own photos and ruling. |
| 4 | Run responsive three-role walkthrough; check keyboard use and form/error feedback, file upload progress, money formatting in NGN, links and statuses. Fix blockers; give lead a browser UAT checklist with screenshots. | Real API-backed journey works after reload and on a fresh session; no action succeeds by local-only storage. |

**Boundaries:** Do not edit backend state directly from the browser or infer payment success from a redirect. Keep existing visual identity unless a specific workflow needs correction.

## Cross-agent handoffs

| Deadline | Handoff | Producer → receiver |
| --- | --- | --- |
| Day 1 morning | Active-repo baseline, required states and role matrix | Lead → all |
| Day 1 midday | Booking update fix contract; proposed rounds/finance schema | Engineer ↔ database; engineer → UI |
| Day 2 morning | Migrated KYC/work rounds and sample records | Database → engineer/UI |
| Day 2 midday | Authenticated booking/evidence/verification endpoints | Engineer → UI |
| Day 3 morning | Provider event fields, quote reference and idempotency constraints | Database ↔ engineer; engineer → UI |
| Day 3 afternoon | Sandbox confirmations and exception payloads | Engineer → UI/lead |
| Day 4 morning | Integrated build and migration notes | All → lead |
| Day 4 evening | Pilot decision with evidence and outstanding defects | Lead → team/stakeholders |

## Daily gates

- **Day 1:** No unscoped booking updates; unrelated bookings stay unchanged.
- **Day 2:** Only approved/active technicians can be newly booked; customer verification is server-authoritative.
- **Day 3:** Duplicate/invalid provider events cannot produce financial effects; second dispute after rework preserves history.
- **Day 4:** Full staged walkthrough and secure deployment checks pass. If any gate fails, record the blocker and continue fixing it; never label the build production-ready because the calendar ended.

## Scope left beyond this sprint

Corporate accounts and SLAs, subscriptions, parts, warranties, AI matching, native mobile apps, nationwide rollout, advanced wallet custody, and large-scale analytics. Chat, ratings, notifications and paid cancellation are valid earlier product ideas but require a separate scoped decision if they are to be release blockers. Live payments require confirmed provider capability, commercial rules, legal review where applicable, and reconciliation evidence beyond a sandbox demonstration.
