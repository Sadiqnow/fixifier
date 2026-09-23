# Shared integration contract v1 — Day 1

Owner: Lead Developer. Effective for sprint implementation; changes require lead, backend, database and UI acknowledgement. Existing API remains the compatibility baseline. Sections marked TARGET are specifications, not claims that endpoints or migrations already exist. Workplan read from `docs/FIXIFIER_AGENT_BY_AGENT_FOUR_DAY_WORKPLAN.md` (also located initially in Downloads); audit from `docs/DEVELOPMENT_AUDIT.md`.

## Invariants and actors

- Roles: customer, technician, admin. Authentication is Sanctum bearer token. Never trust a browser-supplied actor, owner, payment status or KYC status.
- Only the booking customer approves completion, accepts quotations or opens a dispute. Only its assigned technician quotes, starts, uploads or submits evidence. Admin resolves disputes; administrative resolution is a distinct action from customer approval.
- Customer and assigned technician can read their booking/evidence; admin can inspect any. Unrelated users cannot read or mutate it.
- A transition must constrain and lock the booking by primary key, then reload/check actor and current state in the transaction. Audit event and database changes commit or roll back together. External payment calls stay outside transactions.
- The first merge gate is two-booking isolation for approval AND dispute: changes to A leave B's fields, payment and dispute unchanged. No other feature merge precedes it.
- All money uses integer minor units; NGN is the currently supported currency. Stored demo authorization/release is not provider confirmation or a transfer.

## Booking states and transitions

Persisted names stay: requested, quoted, confirmed, in_progress, evidence_submitted, completed, disputed, cancelled.

| From → To | Actor | Required condition |
|---|---|---|
| new → requested | customer | Valid request; TARGET assigned provider active and genuinely approved |
| requested → quoted | assigned technician | Valid scope, positive minor-unit amount, future expiry |
| quoted → confirmed | owning customer | Latest quote not expired; TARGET exact quote ID/version |
| confirmed → in_progress | assigned technician | TARGET before evidence in current work round; payment eligibility subject to provider decision |
| in_progress → evidence_submitted | assigned technician | Both before and after evidence in current round (current implementation checks across entire booking) |
| evidence_submitted → completed | owning customer | Current round submitted; record approval, do not infer real payout |
| evidence_submitted → disputed | owning customer | Reason/details; TARGET no open dispute for current round |
| disputed → confirmed | admin, rework decision | TARGET close dispute, create next round, require new before evidence; current code instead returns to in_progress |
| disputed → completed | admin, release decision | Reasoned resolution; payout is a separate financial process |
| disputed → cancelled | admin, refund decision | Reasoned resolution; refund request is separate from confirmed provider refund |

Customer cancellation, automatic acceptance, paid cancellation and review deadlines are NOT authorized by this contract. Their commercial rules remain unresolved.

## Quote/work/dispute rounds — TARGET, not yet migrated

- Quote states: offered, accepted, rejected, expired, superseded. Current quotation table has no status/version column; acceptance is `accepted_at`, expiry is `expires_at`. Do not pretend rejected/superseded endpoints exist.
- Quotes become immutable versions unique by `(booking_id, version)`. Booking points to accepted quote; amount/currency/version are fixed for a payment attempt. Expired acceptance is 409. A price change after acceptance requires a separately agreed change-order policy; disallow until defined.
- Work rounds: unique `(booking_id, round_number)`, starting at 1. States ready, in_progress, submitted, approved, disputed, rework_required. Booking stores current round. Rework closes the old round as rework_required and creates ready round N+1; old photos do not satisfy it.
- Evidence references work_round_id and type before/after. Before upload is permitted in confirmed/ready; start requires before; after requires in_progress. Submission requires both. Submitted evidence is retained and cannot be silently replaced.
- Disputes reference booking and work round, with numbered cases and open/resolved state; decision is rework/release/refund. At most one open dispute per round, database enforced. A new dispute after rework belongs to the new round; previous decisions remain immutable.
- Existing records backfill round 1 without treating seeded verification as real KYC. Database developer owns migration and key design; backend enforces policy and transaction checks. Return `current_work_round`, `work_rounds`, `disputes` and quote version in additive response fields before retiring singular legacy fields.

## Existing endpoint payloads

Base `/api/v1`. Send `Accept: application/json`; JSON except evidence multipart. IDs are database IDs, not display references. Dates are ISO-8601 with timezone; browsers convert local input to ISO UTC. `page` selects 20-record pagination.

| Method/path | Actor | Payload / response |
|---|---|---|
| POST auth/register | guest | name≤120, email unique, optional phone≤30 unique, password+confirmation≥10 with letters/numbers, role customer/technician; 201 `{data:user,token}` |
| POST auth/login | guest | email,password; 200 `{data:user,token}`; current login revokes previous tokens |
| POST auth/logout | authenticated | empty; 200 message |
| GET me | authenticated | `{data:user,technician_profile:object|null}` |
| GET technicians | authenticated | `{data:[{id,name}]}`; currently role-filtered only, eligibility is Day 2 |
| GET bookings | authenticated | role-scoped Laravel paginator `{data,current_page,last_page,total,...}` |
| POST bookings | customer | technician_id nullable existing technician, service_category≤100, description 20–5000, address≤1000, scheduled_at optional future; 201 `{data:booking}` |
| GET bookings/{id} | participants/admin | `{data:booking}` with customer,technician,quotation,evidence,dispute,payment |
| POST bookings/{id}/quotation | assigned technician | amount_minor integer≥10000, currency NGN, scope 10–5000, expires_at future; 201 `{data:quotation}` |
| POST bookings/{id}/quotation/accept | customer owner | empty today; TARGET quote_id/version + expected_work_round; 200 booking |
| POST bookings/{id}/start | assigned technician | empty today; TARGET expected_work_round; 200 booking |
| POST bookings/{id}/evidence | assigned technician | multipart type before/after, photo JPEG/PNG/WebP≤10 MB, note optional≤2000, captured_at≤now; TARGET work_round_id; 201 evidence |
| GET evidence/{id} | participants/admin | authorized private image bytes, not JSON; errors JSON |
| POST bookings/{id}/evidence/submit | assigned technician | empty today; TARGET expected_work_round; 200 booking |
| POST bookings/{id}/approve | customer owner | empty today; TARGET expected_work_round; 200 booking |
| POST bookings/{id}/dispute | customer owner | reason≤120, details 20–5000; TARGET expected_work_round; 201 dispute |
| POST disputes/{id}/resolve | admin | decision release/refund/rework, resolution 20–5000; 200 dispute |
| GET admin/dashboard | admin | section overview/technicians/bookings/evidence/disputes/customers/finance/audit, page; `{summary,records:paginator}` |

Day 2 proposed endpoints, not implemented: `GET/PATCH /technician/profile` (own name/phone/trade/bio/experience/location/starting_price_minor, no KYC self-approval); `POST /technician/kyc-documents` (private file/type); `GET /admin/kyc-documents`; `POST /admin/kyc-documents/{id}/decision` (approved/rejected + reason); `GET /kyc-documents/{id}/file` (owner/admin). Exact field rules must be agreed before UI writes. Booking detail remains the verification data endpoint; add booking-specific web URL `/bookings/{id}/verification` or a validated booking query to the preserved page, with backend authorization on every API call.

## Errors and compatibility

Standard target envelope: `{message, code, errors?, current_status?, current_work_round?}`. `errors` maps fields to string arrays. 401 unauthenticated; 403 forbidden actor; 404 nonexistent resource; 409 invalid/stale transition or expired quote; 422 validation/missing required evidence; 429 throttled; 500 generic unexpected failure with server-side logging. Never expose another user's booking status in a forbidden response.

Current Laravel validation returns `{message,errors}` and most aborts return `{message}`; some legacy unauthorized transitions use 409. Day 1 isolation methods should return 403 for forbidden actor and 409 with current_status for stale state. Global error normalization and machine codes remain Day 2 implementation. UI must tolerate absent optional fields. Do not retry approval/refund on ambiguous network failure without reloading state. TARGET idempotency keys for financial commands; repeated state transitions currently return conflict rather than guaranteed replay success.

Day 1 delivered delta: accept/approve/dispute now enforce 403 ownership before state disclosure and 409 `{message,code,current_status}`. Codes currently implemented there are `invalid_transition`, `quote_expired`, `dispute_round_not_supported`. Quote expiry is now enforced at acceptance. Audit is atomic with those three actions. Existing-dispute conflict preserves history until round migration; it does not implement a second dispute. Other endpoints retain the baseline behavior above.

## Product decision register

Unresolved, owner Product Owner + Lead unless stated: provider and sandbox capabilities (Finance/Backend); platform fee percentage/fixed component, tax and who pays; whether paid cancellation is in pilot and its cutoff/penalties; refund eligibility/full vs partial, fee recovery, retries and responsible operator; customer review window, reminders, escalation and whether any automatic approval is allowed; pilot service categories/area; release and payout timing. No defaults silently chosen. Until decided, live financial operations, automatic release and paid cancellation are blocked. Existing seeded NGN amounts are demonstrations only.
