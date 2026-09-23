# UI Expert Day 1: screen checklist and integration agreement

Read DEVELOPMENT_AUDIT.md, FIXIFIER_AGENT_BY_AGENT_FOUR_DAY_WORKPLAN.md, SHARED_CONTRACT_V1.md and ENGINEER_DAY1_HANDOFF.md. Reviewed active routes, views, three public JavaScript clients and standalone verification script. This is a source/HTTP audit, not a browser interaction or visual-regression pass. No Blade/CSS/JS behavior or design was changed.

## Screen-to-endpoint checklist

API prefix `/api/v1`; authenticated calls use bearer token from sessionStorage. HTML shells are public; server APIs enforce record access. “Live” below means connected to the real API, not that seeded data or recorded financial states represent real transactions.

| Screen / route | Files | Reads | Actions / checklist |
|---|---|---|---|
| Landing `/` | landing.blade.php inline JS | None | Named Login → /login; Book/search → /book with service query; technician join → /technician/register. Section anchors/mobile menu are client interactions. Privacy/Terms are broken (404). |
| Login/register `/login`, `/register`, `/technician/login`, `/technician/register` | marketplace.blade.php + marketplace.js | GET me | POST auth/login/register/logout. Admin/technician redirect to dedicated dashboards; customer stays here. No password reset/email verification journey. |
| Booking `/book` / customer book tab | marketplace files | GET technicians | POST bookings. Category is text, location/date entered by customer. Eligible-provider filtering is not implemented server-side. Booking intent/service survive login on the current page. |
| Customer overview and bookings `/portal` | marketplace files | GET me, bookings?page, bookings/{id}, evidence/{id} as authorized blobs | Accept quote → POST bookings/{id}/quotation/accept; review modal → POST approve/dispute. Current-page summary only. These decisions are server-backed. |
| Customer payments/profile tabs | marketplace files | me and booking.payment | Read-only profile and recorded payment rows. No checkout, edit, cancel or review/rating API actions. |
| Technician overview/requests/jobs `/technician` | technician.blade.php + technician.js | GET me, bookings?page, bookings/{id} | POST quotation/start. Own assigned records only. Quote scope, integer-minor amount conversion and expiry sent to API. No decline/reassignment flow. |
| Technician evidence tab/modal | technician files | GET bookings/{id}, evidence/{id} | POST evidence multipart, evidence/submit. Before/after types supported; server currently permits uploads after start and checks all booking evidence rather than current round. |
| Technician earnings/profile/KYC | technician files | me.technician_profile and booking.payment | Read-only, unavailable editing/KYC explicitly noted. Newly added service_location/starting_price_minor are not rendered. Recorded release is not bank payout. |
| Admin overview/KYC/bookings/evidence/disputes/customers/finance/audit `/admin` | admin.blade.php + admin.js | GET admin/dashboard?section=&page=, bookings/{id}, evidence/{id} | POST disputes/{id}/resolve with rework/refund/release + resolution. All other admin sections read-only. No KYC approve/reject or account suspension buttons wired. |
| Standalone verification `/job-verification` | job-verification.blade.php inline JS | NONE | Tabs/modals work locally. Confirm approval and submit dispute write localStorage only; supporting photo only previews. No booking identity, auth load, server decision or persisted upload. |
| welcome.blade.php | unused starter view | None | No active application route renders it. |

## Actual HTTP navigation evidence

Checked through `http://localhost/fixifier/public`: `/`, `/login`, `/book?service=electrical`, `/technician/register`, `/portal`, `/technician`, `/admin`, `/job-verification` return 200. `/privacy` and `/terms` return 404. This verifies routing/rendering only, not JS execution or authorization under each account.

Dashboard Job Verification links all target the generic page with no booking ID. They navigate successfully but are disconnected from the job the user is working on. Replace them with an authorized booking picker or booking-specific links, never a guessed/sample booking.

## Samples, storage and unsupported claims

- Verification uses origin-wide `fixifier_plus_verification_v1` localStorage, states review/approved/disputed and fixed dispute reference DSP-0148. Different accounts on the same browser share that local simulation. No real approval can rely on it.
- Verification contains fixed job/technician/address/amount, illustrative evidence and a hard-coded history. Timeline updates are browser-local, not audit events. Supporting photo has no POST request. Its dispute minlength is 15 versus the API's 20.
- Verification correctly discloses no actual funds move; keep truthful payment disclosures after integration. Its “Eligible” settlement label must not become “paid” just because approval succeeded.
- Live dashboards display seeded demo accounts/bookings and SVG evidence placeholders from the database. API backing does not turn those into verified identities or genuine job photographs. Technician profile seeded verified labels need review provenance before a bookable badge can claim genuine KYC.
- Landing advertises verified professionals, identity/skills review, protected payments, a sample technician rating and completed jobs. KYC review, skills certification, ratings and real payment custody are not integrated. FAQ promises profile/verification submission unavailable in active UI. Lead must approve truthful preview wording before public release.
- sessionStorage contains authentication tokens in real clients; that is distinct from using localStorage as business-decision authority. No other live client business decisions were found stored in localStorage.
- Original technician design's editable profile, readiness fields, simulated customer acceptance/approval and richer filters are not current active functionality. Current admin design preserves its shell but omits draft demo KYC mutations/search controls. Do not count omitted draft buttons as shipped features.

## Request/response agreement

UI accepts the published Lead contract and engineer handoff as the existing implementation baseline. No newly proposed endpoint is treated as agreed/shipped without Lead/backend confirmation. No action was wired today.

| Integration | Existing usable contract | Required follow-up |
|---|---|---|
| Verification load | GET me → data user; GET bookings/{id} → data booking with customer,technician,quotation,evidence,dispute,payment; private images GET evidence/{id} | Lead selects booking-specific URL; backend adds explicit current round/history fields and timestamps for timeline. Do not expose raw storage paths. |
| Approval | POST bookings/{id}/approve, empty body currently; 200 data booking | Owner customer, evidence_submitted only; round/version precondition awaits implementation. Refresh detail after success. |
| Dispute | POST bookings/{id}/dispute JSON reason≤120, details 20–5000; 201 data dispute | Supporting attachment upload/association has no contract. Hide/disable unsupported submit intent with clear text pending API; never claim preview uploaded. |
| Quote acceptance | POST quotation/accept; 200 data booking, 409 quote_expired | Display actual expiry. Latest version/quote identity requires backend/schema upgrade. |
| Technician upload | multipart type before/after, photo JPEG/PNG/WebP≤10 MB, note≤2000, captured_at≤now | Current-round field and before-photo-before-start need backend change; use authoritative eligibility, not UI inference. |
| Admin resolution | decision release/refund/rework and resolution 20–5000; 200 data dispute | Display recorded decision separately from provider money movement. New rounds after rework pending. |

Errors: 401 → login preserving a validated same-app return/booking destination; 403 → forbidden without sample fallback; 404 → missing job; 409 → show message/current_status and reload, never report success or auto-repeat; 422 → field errors; 429 → retry guidance; network/500 → retain draft, show recoverable failure, reload authoritative state before retrying decisions. Existing clients largely show a toast and do not yet implement all of these states. Fix modal error handlers that try to write into a removed form after a successful mutation followed by a failed refresh; distinguish mutation outcome from refresh failure.

## Prioritized Day 2 gaps and dependencies

1. P0: booking-specific server-authoritative verification with no browser-local approval/dispute authority. Dependency: Lead route decision; existing isolation gate passed (engineer follow-up 9 tests/164 assertions), current API available; work-round restrictions require backend rollout.
2. P0: KYC/profile submission and admin review. Dependency: profile create/edit endpoint, protected document API, review decisions/provenance, explicit eligibility and permissions from Engineer/DB. Keep unsupported actions unavailable until these exist.
3. P1: current-round evidence, quote version and dispute history rendering. Dependency: additive response schema, rework returning confirmed/ready, fresh before/after requirements and conflict handling. Preserve prior-round photos and decisions visibly.
4. P1: truthful financial/KYC labels and missing Privacy/Terms routes/content. Dependency: approved copy and policy content from Lead; do not invent legal policies, fees or review deadlines.
5. P1: common loading/empty/forbidden/conflict states, safe post-submit refresh, preserved return path, upload progress. Dependency: consistent backend envelopes and max-size rules. Add keyboard focus/return and dialog focus trapping, then mobile UAT.
6. P2: location/starting-price display, global stats, search/filter completeness, cancellation/ratings/notifications only after scope and APIs exist.

## Files owned and coordination boundaries

Reserved branch `ui/day2-verification` (not created; baseline has no Git checkout). UI owns resources/views/landing.blade.php, marketplace.blade.php, technician.blade.php, admin.blade.php, job-verification.blade.php; public/js/marketplace.js, technician.js, admin.js and future job-verification.js; relevant public/css and browser UI tests. Coordinate one change window per shared script. Do not change migrations, models, controllers, or routes: Lead owns route integration, Engineer owns API behavior, Database Developer owns reviewed schema changes. This handoff and DAY1_SCREEN_MATRIX.md are the UI documentation deliverables.

Preserve layout, color, typography, spacing, responsive structure, evidence comparison and modal presentation. Change data sources, eligibility, feedback and unsupported text only through the contract. Source designs are in Downloads/fixifier_html; a separate customer-dashboard HTML exists but is not routed. Do not silently replace the current marketplace with that artifact.

## Acceptance checklist for Day 2

- [ ] Customer sees own selected booking and real before/after evidence; other customer gets forbidden.
- [ ] Assigned technician/admin cannot invoke customer approval; admin resolution stays separate.
- [ ] Approval/dispute survive reload and fresh sign-in; localStorage deletion has no effect on server state.
- [ ] Stale/expired/conflicting request shows real error and refreshed state; no false success after network failure.
- [ ] Rework shows new current-round requirements plus immutable history.
- [ ] No supporting photo is described as uploaded without persisted server record.
- [ ] Payment labels distinguish demo, pending request, confirmed result and actual provider transfer.
- [ ] Keyboard/mobile/layout comparison and three-role browser walkthrough captured after implementation.

These acceptance items are pending; HTTP smoke checks and source inspection alone do not satisfy browser UAT.
