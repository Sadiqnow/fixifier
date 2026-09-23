# Fixifier Plus — MVP requirements and state transitions

Version 1.0 · 23 September 2026 · Product specification for approval

## 1. MVP goal and boundary

A customer can find an approved technician, request a job, accept a valid quote, pay through a verified payment provider, review completion evidence, and approve completion or raise a dispute. A technician can onboard, quote, work, and receive a recorded payout. An administrator can verify technicians, resolve disputes, and reconcile provider transactions. Release begins with one service area, one currency (NGN), and one payment provider. The provider and commercial rules require a product decision before financial implementation.

The current code is a local prototype, assessed in the 23 September development audit as approximately 45% toward a production MVP. Existing database payment statuses are demo records and do not establish real charging, escrow, refund, or payout. This document defines target behavior, not a claim about current implementation.

### MVP inclusions

| ID | Capability | Required behavior / acceptance |
| --- | --- | --- |
| AUTH-01 | Identity | Customer and technician registration/login; email or phone verification, password reset, expiring sessions/tokens, login throttling; role-based access and suspension. Admin accounts provisioned by an administrator. |
| TECH-01 | Technician profile | Trade/category, location/service radius, bio, indicative price, availability and contact preferences editable by technician. Registration creates a profile. |
| TECH-02 | Verification | Private ID/business documents, consent, submission, administrator review with reason and decision history. Only approved and active technicians appear in bookable discovery. |
| DISC-01 | Discovery | Search/filter by category and service area; show only approved, active, available technicians. Display verified status accurately. |
| BOOK-01 | Request | Customer creates job with category, description, location, preferred schedule and technician; server verifies technician eligibility. Customer sees only own bookings; assigned technician sees only assigned work. |
| QUOTE-01 | Quote | Assigned technician submits itemized price in NGN minor units, scope, exclusions and expiry; customer accepts or rejects. Expired or superseded quotes cannot be accepted. |
| PAY-01 | Payment | Provider checkout/authorization tied to exact booking, quote version and amount; signed webhook verification, unique provider references, retry-safe processing. Booking can advance to work only after confirmed payment under agreed settlement rules. |
| WORK-01 | Execution | Capture before-work evidence before start; technician starts, uploads after-work evidence, and submits completion. Private media with owner/participant/admin access; each rework round needs new after evidence. |
| VERIFY-01 | Customer decision | Booking-specific verification view loads actual evidence and authoritative server status. Customer approves or disputes within a configured review window; refresh retains outcome. |
| DISP-01 | Dispute | Customer supplies reason and optional evidence; admin reviews and records a reasoned release, refund or rework decision. Repeat dispute rounds are supported with independent history. |
| FIN-01 | Settlement | Release or refund only after provider confirmation; calculate platform fee and technician net amounts, record an immutable financial event trail, reconcile provider reports, and flag exceptions. Never label a database-only state as money transferred. |
| OPS-01 | Admin operations | Review technicians, jobs, disputes, payment exceptions and audit timeline; suspend accounts; restrict sensitive actions by role. |
| NOTIF-01 | Notices | In-app/email or SMS notices for quote, payment, assignment, submission, dispute and resolution; failed delivery must not roll back a successful domain transition; retries recorded. |
| AUD-01 | Audit | Append-only, transactionally written actor/action/from/to/timestamp/reason records for material transitions; no client-side authority over state. |
| QA-01 | Validation | Lifecycle, authorization, independent-booking isolation, race, duplicate webhook, repeat-dispute, refund/payout and access tests pass in staging. |
| DEP-01 | Operations | Web root serves only Laravel `public`; credentials outside public/version control, protected media, migrations, backups with restore test, logs/alerts, queue workers and rollback procedure. |

### Explicitly later than MVP

Corporate accounts, subscription tiers, parts marketplace, AI matching, live technician tracking, multi-country/multi-currency support, advanced analytics and native mobile apps. Reviews and rescheduling may follow MVP; a customer can cancel before payment, while later cancellation requires a defined refund policy.

## 2. Actors and invariants

Customer owns the request and makes quote/verification decisions. Technician is assigned to one booking and may quote or submit work only on that booking. Admin verifies technician identity and adjudicates disputes; admin cannot silently impersonate customer approval. Payment provider confirms funds movement. System jobs expire quotes, enforce decision deadlines and reconcile payments; they never assume a payment succeeded merely because an HTTP request was sent.

Every transition is an authenticated command with server-side permission, current-state check, booking-scoped row lock, database transaction, idempotency key where retries are plausible, and audit event in the same transaction. Reject invalid transitions with a conflict response and current server state. No transition may alter another booking. Financial external calls use an outbox/job and provider reference; retries must not duplicate charges, refunds, or payouts. Store amounts in integer kobo and record quote/payment version and currency. Keep status history rather than overwriting dispute outcomes.

## 3. State transitions

### 3.1 Technician verification

| From | Event / actor | Guard | To |
| --- | --- | --- | --- |
| `draft` | Submit profile and documents / technician | Required fields and private uploads present | `pending_review` |
| `pending_review` | Approve / admin | Document checks completed, reason/audit recorded | `approved` |
| `pending_review` | Request changes / admin | Reason supplied | `changes_requested` |
| `pending_review` | Reject / admin | Reason supplied | `rejected` |
| `changes_requested`, `rejected` | Resubmit / technician | New documents/version supplied | `pending_review` |
| `approved` | Suspend / admin | Reason supplied | `suspended` |
| `suspended` | Reinstate / admin | Recheck completed | `approved` |

Only `approved` and available technicians are eligible for new assignments. Suspension stops new jobs; existing jobs require admin reassignment/cancellation with documented financial treatment.

### 3.2 Booking and quote

| From | Event / actor | Guard | To |
| --- | --- | --- | --- |
| `requested` | Accept assignment / technician | Approved, active and available | `awaiting_quote` |
| `requested` | Decline / technician | Request still open | `requested` with declined technician removed; reassign |
| `requested`, `awaiting_quote` | Cancel / customer | No confirmed payment | `cancelled` |
| `awaiting_quote` | Submit quote / technician | Valid positive amount, scope and future expiry | `awaiting_customer` |
| `awaiting_customer` | Revise quote / technician | Prior quote superseded; customer has not accepted | `awaiting_customer` with new quote version |
| `awaiting_customer` | Reject quote / customer | Latest quote still active | `awaiting_quote` |
| `awaiting_customer` | Accept quote / customer | Latest quote unexpired; quote locked to booking | `awaiting_payment` |
| `awaiting_customer` | Expire / system | Quote expiry reached | `awaiting_quote` |
| `awaiting_payment` | Cancel / customer | No charge confirmed; pending provider attempts resolved | `cancelled` |
| `awaiting_payment` | Payment confirmed / verified provider webhook | Correct amount/currency/reference and quote version | `funded` |
| `funded` | Upload before evidence / technician | File private and tied to current work round | `funded` |
| `funded` | Start / technician | Before evidence for current round exists | `in_progress` |
| `in_progress` | Submit completion / technician | After evidence for current round exists | `awaiting_verification` |
| `awaiting_verification` | Approve / customer | Review window open; no open dispute | `release_pending` |
| `awaiting_verification` | Open dispute / customer | Reason supplied; one open dispute per round | `disputed` |
| `awaiting_verification` | Review window expires / system | Policy and notice requirements satisfied | `release_pending` (only if auto-approval is explicitly approved) |
| `disputed` | Order rework / admin | Reason and new work round recorded | `rework_required` |
| `rework_required` | Resume / technician | Fresh before evidence for new round | `in_progress` |
| `disputed` | Order release / admin | Reasoned decision | `release_pending` |
| `disputed` | Order refund / admin | Refund amount and policy validated | `refund_pending` |
| `release_pending` | Provider payout confirmed / webhook or reconciliation | Transfer/reference verified | `completed` |
| `refund_pending` | Provider refund confirmed / webhook or reconciliation | Refund/reference verified | `refunded` |
| `release_pending`, `refund_pending` | Provider failure / system | Failure recorded, retry/reconciliation created | Same pending state with exception flag |

`cancelled`, `completed`, `refunded` are terminal for booking workflow; corrections use explicit financial adjustments and audit events, never rewrite history. Paid cancellation and partial refunds need a signed-off policy before implementation. Do not auto-approve by default without a defined review period and customer notification.

### 3.3 Payment attempt, settlement and dispute record

| Aggregate | States and legal progression |
| --- | --- |
| Payment attempt | `created` → `pending_provider` → `confirmed` or `failed`; `pending_provider` → `expired`; delayed success after a local timeout goes to reconciliation, never silently discarded. A new attempt uses a new reference while retaining all attempts. |
| Payout | `not_due` → `release_pending` → `processing` → `paid`; processing failure → `release_pending` plus exception/retry. Never show `paid` on request acceptance alone. |
| Refund | `not_due` → `refund_pending` → `processing` → `refunded`; processing failure → `refund_pending` plus exception/retry. |
| Dispute round | `open` → `under_review` → `resolved_rework`, `resolved_release`, or `resolved_refund`; each decision is immutable. Rework creates a new work round and permits a new dispute after the next submission. |

Use separate payment, payout, refund and dispute aggregates. A single booking status is a customer-facing summary, not the sole source of financial truth. Provider callbacks must verify signature, reference, booking, amount, currency, prior state and uniqueness before changing an aggregate.

## 4. Definition of done and implementation order

1. Repair the critical unscoped booking update in `WorkflowController::approve` and `::dispute`; add an isolation regression test with two bookings. Move checks inside booking-scoped transactions; lock dispute resolution and enforce quote expiry.
2. Implement the booking state machine, work rounds, repeat disputes, audit events and transition/concurrency tests. Replace the standalone verification page's localStorage decisions with authenticated booking endpoints while preserving its visual design.
3. Complete profile onboarding, private KYC upload/review, approved-only discovery and assignment eligibility. Add cancellation before payment and quote revision/expiry behavior.
4. Choose provider and settlement policy; implement real checkout, verified callbacks, financial events, payouts, refunds and reconciliation. Test duplicates, delayed callbacks and provider failures in sandbox.
5. Add notifications, admin exception handling, staging deployment, backup/restore drill and full role-based acceptance checks. Only enable real money after provider and operational gates pass.

### Acceptance scenarios for MVP sign-off

- An unverified or suspended technician cannot be newly booked; approved technician can be discovered and booked.
- Customer A cannot view or change customer B's request, evidence, quote, dispute or payment; an update to A never changes B.
- An expired/superseded quote and duplicate/concurrent acceptance are rejected without inconsistent state.
- Work cannot start without before evidence; submission cannot occur without fresh after evidence for the current round.
- Verification refresh displays the same server outcome; only owner approves/disputes; repeat dispute after rework preserves the first ruling.
- Duplicate, invalid-signature, wrong-amount and late provider callbacks do not create duplicate financial effects.
- Booking reaches `completed` only after verified payout; `refunded` only after verified refund; reconciliation reports unresolved exceptions.
- Backup restore and secure staging checks succeed before go-live.

## 5. Decisions to sign off

| Decision | Proposed MVP default |
| --- | --- |
| Launch area and services | One city/service area and a small managed service-category list. |
| Payment provider and custody model | Select a provider whose actual authorization, split/hold, refund and payout capabilities meet legal/commercial review; do not call ordinary ledger statuses escrow. |
| Fee and settlement timing | Publish platform fee formula, who pays provider fees, technician net amount and payout trigger before coding provider flows. |
| Customer review window | Specify duration, reminders and whether timeout causes manual admin review or authorized auto-approval; default to manual review. |
| Cancellation/refund rules | Define deadlines, partial work, provider fees, payment already captured and appeal handling; default to admin-handled paid cancellations. |
| KYC requirement | Approval required before public discovery or new assignment. |
