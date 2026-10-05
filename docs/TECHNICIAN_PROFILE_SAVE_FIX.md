# Technician profile save fix

The profile editor already called `PUT /api/v1/technician/profile`, but rejected
requests escaped its submit handler without a visible error. A description
shorter than the server's 20-character minimum could therefore appear to do
nothing. Database saves also filtered out null values, preventing a technician
from clearing the indicative price or availability notes.

The fix displays server/network errors in the open form, prevents duplicate
submissions, matches browser validation to the API, and renders the saved
database response. Nullable values now clear correctly. Changes remain scoped
to the authenticated technician; administrative verification fields cannot be
submitted. A suspended profile remains suspended when its trade/area changes.

No schema migration was added or applied. The existing journey migration
`2026_10_01_000001_complete_technician_journey` provides `skills`,
`indicative_price_minor`, and `availability_notes`. If these columns are absent,
the API now returns a clear storage-upgrade error instead of silently dropping
those fields. Do not claim successful persistence on an incomplete schema.

## Deploy to the existing journey installation

Back up these three live files and extract
`deployment-packages/technician-profile-save-fix.zip` into
`/home/mtechedg/fixifier`:

```text
app/Http/Controllers/Api/V1/JourneyController.php
public/js/technician-views.js
resources/views/technician.blade.php
```

This is a patch for the current journey installation, with its existing
`PUT /api/v1/technician/profile` route and supporting classes. It is not a
standalone installation of the entire journey feature.

Clear compiled views with the established temporary cPanel cron method:

```sh
/opt/cpanel/ea-php84/root/usr/bin/php /home/mtechedg/fixifier/artisan view:clear > /home/mtechedg/fixifier-profile-fix.txt 2>&1
```

Remove the cron job once it succeeds. Hard-refresh `/technician`, edit a profile
and save. Refresh again and confirm every edited value remains. A failed save
now shows its error inside the form. If the storage-upgrade error appears,
inspect the live migration status and verify a database/private-files backup
restore before applying the existing migration; it changes more than profiles.
Do not run a blind database upgrade or replace `.env`.

## Verification

The API regression test saves all profile fields and reads them back through a
separate `/me` request; it checks clearing nullable values, ownership, protected
verification status, invalid-input rejection and customer access denial. UI
tests exercise successful submission and failed submission with retry enabled.
Tests use isolated local data. Live database persistence still requires the
deployment and manual refresh check described above.
