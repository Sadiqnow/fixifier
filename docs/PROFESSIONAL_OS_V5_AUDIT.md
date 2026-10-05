# Professional OS v5: preservation audit and integration register

Audit date: 2026-10-05. Branch: feature/admin-portal. Source reference:
`C:/Users/Fixifier/Downloads/fixifier_html/fixifier_technician_professional_os_v5.html`.
Existing uncommitted profile-save fixes are part of the baseline and retained.

## Architecture

Laravel 12, PHP 8.2+, Blade and browser JavaScript. Customer/technician APIs use
Sanctum tokens and EnsureActiveAccount. The admin portal uses a separate web
session guarded by EnsureAdminSession; it is not a competing technician login.
Canonical business jobs are `bookings`, not Laravel's queue `jobs` table.

`routes/api.php` connects BookingController, WorkflowController,
JourneyController and JourneyPaymentController. Admin decisions are in
Admin/ActionController and DisputeResolution. Journey records booking_updates,
work_rounds and journey_notifications. Existing UI entry points are /portal,
/technician, /job-verification and /admin. Preserve all these contracts.

Booking states remain requested, quoted, confirmed, in_progress,
evidence_submitted, completed, disputed and cancelled. Visit, payment and
settlement states are separate dimensions. Prototype state names must be
presentational projections, not an uncontrolled replacement enum.

## Three-source comparison

| Capability | Current Laravel | Previous journey | v5 | Action |
|---|---|---|---|---|
| Authentication/ownership | Sanctum, active-account checks, participant checks | Token login | Same professional identity | Preserve; no new login system |
| Profile save | Own profile PUT, catalogue validation, pending reverification | Editable form and status | Rich professional profile | Preserve pending fixes; extend fields additively |
| KYC | Private uploads, document decisions, admin review | KYC section | Progressive identity/trade/compliance stack | Extend per-check expiry/provider references; never fake verification |
| Availability | is_available, profile/account active, verified eligibility | Basic availability | Online/busy/location/radius | Add operational status alongside existing flags |
| Matching | Customer selection or admin assignment | Assigned requests | Ranked live opportunities | Add eligible opportunity service, expiry and transactional claim |
| Scheduling | One scheduled_at, visit status, no overlap guard | Schedule action | Recurrence, exceptions, day/week/month | Add shared capacity service; protect legacy schedule entry point |
| Canonical job | bookings with detail API | Details modal/shared journey dialog | Tabbed workspace | Keep booking ID and add technician job URL |
| Diagnosis | Quote diagnosis text only | Quote form | Independent diagnosis and media | Add versioned findings linked to booking/round |
| Quotations | Versioned quotations, JSON items, exact-version acceptance | Full shared journey; older technician form incomplete | Labour/parts/scope/validity | Reuse API; bring all callers to itemized contract |
| Merchant procurement | No merchant role/routes/models found across app/routes/schema/views | None | Requirements, offers, approval, fulfilment | Add verified merchant role/module; customer owns purchase |
| Funding | Paystack test adapter, exact quote/amount checks, durable operations | Customer-only funding | Provider-owned funding | Preserve guarded adapter; live credentials/certification external |
| Evidence | Private uploads, before/after, current-round guards | Upload and authenticated preview | Before/during/after | Extend allowed stages without weakening before/after checks |
| Work rounds | Durable work_rounds, rework preserves evidence | Round-aware actions | Full history | Preserve and expose all rounds |
| Disputes | Customer opens, technician responds, admin resolves | Shared journey | Job-linked support | Preserve roles; expose response and media in workspace |
| Timeline/messages | booking_updates, attachments, participant access | Shared journey dialog | Canonical timeline/inbox | Reuse records; add read/delivery state and scoped merchant access |
| Notifications | Persisted in-app, per-user read | Shared notifications | Resource-linked alerts/reminders | Reuse; add scheduled reminders/channels behind configuration |
| Earnings/payouts | Server fee snapshots, payment operations/destinations | Payment table, shared earnings | Wallet/ledger/reconciliation | Preserve financial truth; no browser balance or fake payouts |
| Ratings | One customer review per completed booking, moderation | Review summary | Dimensions and trends | Extend reviews additively, preserve existing stars |
| Performance | RankingService and admin earnings views | Limited summary | Explainable weighted score | Add configurable evidence-based components and history |
| Professional levels | Absent from app/schema | None | L0-L5 | Add configurable rules, require verified achievements |
| Academy | Absent from app/schema | None | Courses/progress/certificates | Add persisted content, assessments and completions |
| Portfolio/warranty | No dedicated records found | None | Privacy-aware work portfolio/guarantee | Add job-linked consent and claims; no private-media publication |
| Maps | Address/service area only | None | Map/distance/ETA | Add consented coordinates/configurable provider; unknown ETA stays unknown |
| Enterprise | Generic booking/customer relation | Residential examples | Corporate/SLA readiness | Avoid duplicating job records; additive organisation/recurrence metadata |
| UI | Ten-section technician view; shared journey controls | Navy/orange cards | Twelve grouped modules/nav/map/KPIs | Componentize v5 and retain all valid older entry points |

## Conflicts requiring explicit handling

- v5 contains local arrays, pretend evidence filenames, customer approvals,
  provider funding buttons and merchant/sample balance data. Replace with API
  reads/authorized operations; never ship these as technician powers.
- Existing technician quote form posts amount/scope but the current FormRequest
  requires diagnosis, exclusions, duration and line items. Preserve compatibility
  or update the caller; do not remove validation to make the UI appear functional.
- Existing scheduling accepts a date without capacity checks. Every booking,
  assignment and rescheduling entry point must use the same reservation guard.
- `UserRole` currently has only customer/technician/admin. Adding merchant must
  not inherit the unrestricted fallback in BookingController::index.
- Customer approval is not a released payment. PaystackGateway is test-only and
  disabled unless explicitly configured; no production payment claim is valid.
- Finance, identity, SMS and routing provider credentials are external
  dependencies. Keep interfaces and failure states explicit.

## Database and change boundaries

Additive schema only; existing quote/round/payment/audit history must remain.
No application database migration is authorized by a test pass alone: verify a
current outside-webroot database/private-storage backup and restore before an
actual installation upgrade. Isolated test databases may run test migrations.

Preserve existing tests and routes. Use separate services/FormRequests for new
domains. No v5 application replacement or cleanup until matching functionality
is implemented and verified. No deployment, push or live payments performed.

## Delivery status

Audit and comparison documented. Implementation and role-based verification
must be tracked in subsequent delivery entries; this document is not a claim
that the complete Professional OS specification has been delivered.
