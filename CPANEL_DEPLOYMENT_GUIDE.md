# Fixifier Plus — cPanel Deployment

## Hosting values

- Domain: `api.mtechedge.net`
- Document root: `/home/mtechedg/fixifier/public`
- PHP: 8.4
- Database: `mtechedg_fixifier`
- Database user: `mtechedg_fixifier`

## 1. Upload and extract

In cPanel File Manager, open `/home/mtechedg`, upload the ZIP and extract it.
The extracted application directory must be `/home/mtechedg/fixifier`.

Confirm that these paths exist:

- `/home/mtechedg/fixifier/artisan`
- `/home/mtechedg/fixifier/vendor/autoload.php`
- `/home/mtechedg/fixifier/public/index.php`

## 2. Configure the environment

Enable **Show Hidden Files** in File Manager. Open `/home/mtechedg/fixifier/.env`
and replace only this value with the private database password created in cPanel:

```env
DB_PASSWORD=CHANGE_THIS_TO_YOUR_DATABASE_PASSWORD
```

Do not add spaces around `=` and do not expose this file publicly.

## 3. Import the database schema

Open cPanel **phpMyAdmin**, select `mtechedg_fixifier`, choose **Import**, and
upload `database/fixifier_mysql.sql`. Confirm that the import finishes without
errors.

## 4. Permissions

In File Manager, set these directories to permission `0755` or `0775` if the
server requires group write access:

- `storage`
- `bootstrap/cache`

Never set application directories to `0777` unless the hosting provider
explicitly requires it.

## 5. Verify

Visit:

- `https://api.mtechedge.net/`
- `https://api.mtechedge.net/up`

The first URL should return a JSON service response. The health URL should
return HTTP 200. Test registration with `POST /api/v1/auth/register`.

## Security notes

- `APP_DEBUG` is disabled.
- Job evidence is stored in private application storage.
- Never place `.env` inside `public/`.
- Replace the database password immediately if it is accidentally shared.
