# Technician journey integration

The supplied `fixifier-technician-journey.html` has been adapted into the existing
`/technician` portal. Its navy/orange palette, sidebar, responsive cards, ten
navigation sections and process-map layout are preserved. The source HTML in
Downloads is unchanged.

The implementation uses Blade and the existing browser JavaScript rather than
introducing React or a Node build dependency. Production assets are included.

## Connected behavior

- Existing technician bearer-token login/logout and assigned-booking pagination.
- Incoming requests, quotations, job-state filters, work evidence and rework.
- Quotation submission with amount in minor units, scope and future expiry.
- Authenticated private evidence previews with round labels and dispute history.
- Before/after uploads, work starts and completion submission retain the API's
  `expected_work_round` guard.
- Profile/KYC status and recorded payment/settlement data come from the API.
- URL hashes preserve the selected section; mobile navigation, modal keyboard
  containment, safe text escaping and error handling are included.

Filters, statistics and payment rows describe the current booking page, not
account-wide totals. Completed jobs do not imply released payments.

## Existing limits retained

The prototype's customer payment simulation, administrator decisions, provider
confirmations, profile saving, KYC upload and accept/decline simulations are not
production API operations. The corresponding sections remain visible with
truthful status/help text and disabled unsupported controls. No new permissions,
database schema or payment functionality has been introduced. The existing job
verification page remains linked. React was optional and is not required here.

## Validation

- Laravel regression suite: 34 tests, 462 assertions passed locally on PHP 8.2.12
  and the isolated SQLite test database.
- Seven Node UI logic tests exercise section rendering/escaping, real-state
  filters, held-versus-released payments, role restrictions, mutation work-round
  guards, quotation minor units and failed pagination.
- JavaScript syntax checks and Blade compilation passed.
- Headless browser preview uses explicitly synthetic local API fixtures. It is
  visual evidence only, not a live authenticated workflow test.
- Live technician/customer/admin end-to-end verification remains pending.

## Manual cPanel update

Use `deployment-packages/technician-journey-update.zip`. Back up the existing
technician view and JavaScript first. Upload this ZIP outside the public document
root and extract into `/home/mtechedg/fixifier`, preserving its relative folders.
It contains exactly these four files:

```text
resources/views/technician.blade.php
public/css/technician.css
public/js/technician.js
public/js/technician-views.js
```

No `.env`, uploads, `vendor`, migrations or database files are included. No
Composer/npm installation or migration is required. Upload all four together.

Use a temporary cron job to clear compiled views after extraction:

```sh
/opt/cpanel/ea-php84/root/usr/bin/php /home/mtechedg/fixifier/artisan view:clear > /home/mtechedg/fixifier-technician-update.txt 2>&1
```

Delete the temporary job once the output confirms success. Sign in through
`/technician/login` and open `/technician`. Check desktop/mobile navigation,
quotes, evidence preview/upload, rework, and a controlled unpaid
customer/technician/admin workflow. Keep the existing backend eligibility and
payment requirements; do not simulate another role from the technician account.

To revert this UI update, restore the previous technician Blade view and script,
then clear compiled views again. The new unused stylesheet/view-renderer can
remain until cleanup; they are not referenced by the old view.

This update is local and packaged, not committed, pushed, merged or deployed.
