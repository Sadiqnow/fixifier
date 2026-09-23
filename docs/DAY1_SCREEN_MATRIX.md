# Day 1 screen integration inventory

Date: 23 September 2026. Source inspection of `routes/web.php`, `routes/api.php`, all six Blade views, and all three files in `public/js`. This records current implementation, not browser acceptance or production readiness. No application behavior was changed by this inventory.

All API paths below have prefix `/api/v1`. All except registration/login require Sanctum bearer authentication. Browser clients keep the bearer token in `sessionStorage` under `fixifier-token`. The web routes render public HTML shells; server authorization belongs to the APIs. A public shell does not expose protected API records by itself.

## Screen matrix

| Screen / routes | Active view and script | Real API reads | Real API writes | Remaining limitations |
|---|---|---|---|---|
| Landing `/` | `landing.blade.php`, inline script | None | None | Named login, booking and technician registration links work at source level; search carries `service` query to booking. Static marketing claims exceed implementation. `/privacy` and `/terms` have no routes. |
| Login and registration `/login`, `/register`, `/technician/login`, `/technician/register` | `marketplace.blade.php`, `marketplace.js` | `GET /me` | `POST /auth/login`, `/auth/register`, `/auth/logout` | Role redirects lead to admin or technician dashboards; customer stays in marketplace. No password-reset or email-verification screen. |
| Customer overview/bookings `/portal` | `marketplace.blade.php`, `marketplace.js` | `GET /me`, `/bookings?page=`, `/bookings/{id}`, `/evidence/{id}` | `POST /bookings/{id}/quotation/accept`, `/bookings/{id}/approve`, `/bookings/{id}/dispute` | Approval and dispute are real API operations inside booking details, unlike standalone verification. Counts cover the current page only. Critical approval/dispute isolation must pass the first merge gate. |
| Customer booking `/book` and Book a service tab | Same marketplace files | `GET /technicians` | `POST /bookings` | Real booking request, technician choice, description, address, schedule, category. No managed category taxonomy, availability calendar or KYC eligibility enforcement. |
| Customer profile and payments tabs | Same marketplace files | Profile from `/me`; payments embedded in `/bookings` | None | Profile is read-only. Payment records are database states, not checkout, funds held, refunds or payouts. |
| Technician overview/requests/jobs/evidence `/technician` | `technician.blade.php`, `technician.js` | `GET /me`, `/bookings?page=`, `/bookings/{id}`, `/evidence/{id}` | `POST /bookings/{id}/quotation`, `/start`, `/evidence`, `/evidence/submit`; `POST /auth/logout` | Assigned-booking workflow is API backed. No current work-round selection or evidence freshness requirement after rework. Starting a job does not require before evidence. |
| Technician earnings/profile/KYC tabs | Same technician files | Profile from `/me`; payments embedded in `/bookings` | None | Explicit read-only profile/KYC notice; earnings are recorded states and current-page totals. Service location and starting-price fields are not displayed. |
| Admin overview/technicians/bookings/evidence/disputes/customers/finance/audit `/admin` | `admin.blade.php`, `admin.js` | `GET /admin/dashboard?section={section}&page=`, `/bookings/{id}`, `/evidence/{id}` | `POST /disputes/{id}/resolve`; `POST /auth/logout` | KYC status and finance records are read-only. Resolution changes recorded states, with no payment provider transfer. Admin summary uses server-wide aggregates. No account management or KYC decision action. |
| Standalone verification `/job-verification` | `job-verification.blade.php`, inline script | **None** | **None** | Public fixed sample job, no booking ID, token, fetch or participant binding. All three dashboards link here without a booking identifier. |
| Laravel welcome view | `welcome.blade.php` | None | None | Present on disk; no active web route renders it. |

Marketplace JavaScript still contains technician and admin action branches, but normal sign-in redirects those roles to dedicated dashboards. Do not count this duplicate client code as separate shipped screens.

## Standalone verification simulation

- Uses `localStorage` key `fixifier_plus_verification_v1`, shared for the browser origin rather than a user/booking. Values use `review`, `approved`, `disputed`; these are not backend booking states.
- Approval mutates browser state only. Dispute creates the fixed reference `DSP-0148` locally. Refresh restores that browser-local state; another customer in the same browser can see the same simulation.
- Evidence consists of illustrative SVG placeholders. Supporting-photo input makes an object-URL preview only; no upload occurs.
- Job reference, technician, location, fee, completion note and timeline are hard-coded sample content. Payment settlement labels are simulated and the page explicitly says no funds move.
- Dispute description currently accepts 15 characters; the real marketplace dispute UI/API contract requires 20. The supporting-photo control has no corresponding dispute attachment endpoint.

## Claims requiring product/content alignment

The landing page advertises verified local professionals, identity/skills review, protected payments and payment release after approval. Active KYC submission/review, skills verification, ratings and payment processing do not substantiate those claims. Its FAQ directs technicians to complete a professional profile and submit verification information, but current screens cannot do that. Preserve styling while correcting claims or explicitly describing preview functionality before a public launch. Existing verification and finance screens already disclose simulation; retain truthful disclosures until provider-backed behavior exists.

## Day 2 UI handoff and ownership

Proposed branch: `ui/day2-verification`. **Not created:** this workspace has no `.git` repository. Branch creation and independent worktrees require a real repository baseline first; branch names alone do not isolate edits in a shared directory.

UI owner exclusively edits `resources/views/job-verification.blade.php`, a new `public/js/job-verification.js`, and booking-specific verification links in the three dashboard views/scripts. Lead owns `routes/web.php` and coordinates route parameters; backend owner owns API controllers/resources/schema; QA owns regression and browser tests. Agree integration points before overlapping changes to dashboard scripts. This inventory file is owned by the lead's Day 1 documentation integration.

Priority handoff after booking isolation gate:

1. Preserve supplied layout, CSS, spacing and responsive behavior. Bind a selected booking to the verification screen using the shared contract; a general navigation link should first select an authorized booking, never guess a booking ID.
2. Load `/me`, `/bookings/{id}` and private `/evidence/{id}` with bearer credentials. Render stored facts, empty states and unavailable-image errors; never use demo data as fallback for failed authorization.
3. Customer owner can approve/dispute only in eligible server states; assigned technician and admin have read-only customer-review controls. Admin resolution remains a separate authorized action. API role/state checks remain authoritative.
4. Replace local approval/dispute persistence with API requests, disabled pending buttons, validation messages and refresh from server after success. Reload must show server state. Do not offer dispute supporting-photo submission until its contract/storage exists.
5. Separate work/evidence rounds and dispute history only after backend schema/API migration lands. Do not fabricate a timeline from demo timestamps: use actual timestamps/events exposed by the contract.
6. Show payment state as recorded information; no assertion of paid/refunded/transferred funds without provider confirmation. Review-window countdown is blocked on an explicit product decision.

Acceptance handoff: customer A sees booking A; unrelated customer B cannot read/approve/dispute A; assigned technician cannot approve their own work; unauthenticated use prompts login; successful actions persist after refresh; 401/403/404/409/422 and network failures render actionable messages; current round evidence displays correctly; layouts match supplied design at desktop and mobile sizes. Existing PHP response/API tests do not establish browser acceptance; execute these checks in the Day 2 QA scope.
