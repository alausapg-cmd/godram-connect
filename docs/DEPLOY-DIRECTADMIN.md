# Installing GODRAM Connect on WhoGoHost (DirectAdmin)

This guide puts the app on a subdomain such as `connect.yourdomain.org`. It takes about 30 minutes the first time.

## What you need

- WhoGoHost shared hosting with DirectAdmin and SSH access.
- PHP **8.3 or newer** with the GD, PDO MySQL, mbstring, intl, fileinfo and openssl extensions. In DirectAdmin, open **Select PHP version** (or **PHP Version Manager**) and choose 8.3 or 8.4 for the domain.
- The package `godram-connect.zip`. Download it from the repository on GitHub: **Actions** tab, the latest green run on `main`, then **Artifacts**.

## 1. Create the subdomain

DirectAdmin: **Subdomain Management** → add `connect`. This creates a folder such as `~/domains/yourdomain.org/public_html/connect`.

## 2. Create the database

DirectAdmin: **MySQL Management** → **Create new database**. Note the database name, user and password (DirectAdmin prefixes them with your username, for example `abcd_godram`).

## 3. Upload and unpack the app

Keep the app outside `public_html`, so only its `public` folder is reachable from the web.

1. **File Manager** → your home folder (`~`) → upload `godram-connect.zip`.
2. Extract it into a new folder `~/godram-connect`.

Or over SSH:

```bash
cd ~
mkdir godram-connect && cd godram-connect
unzip ~/godram-connect.zip
```

## 4. Point the subdomain at the app

Replace the subdomain's folder with a link to the app's `public` folder (SSH):

```bash
cd ~/domains/yourdomain.org/public_html
mv connect connect-old
ln -s ~/godram-connect/public connect
```

If your plan does not allow links, copy the contents of `~/godram-connect/public` into `public_html/connect`, then edit `public_html/connect/index.php` and change both `__DIR__.'/../` paths to `__DIR__.'/../../../../godram-connect/` (the path from that folder to `~/godram-connect`).

## 5. Configure

```bash
cd ~/godram-connect
cp .env.example .env
nano .env
```

Set at least:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://connect.yourdomain.org

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=abcd_godram
DB_USERNAME=abcd_godram
DB_PASSWORD=your-database-password

MAIL_MAILER=smtp
MAIL_HOST=mail.yourdomain.org
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=no-reply@yourdomain.org
MAIL_PASSWORD=the-mailbox-password
MAIL_FROM_ADDRESS=no-reply@yourdomain.org

GODRAM_DEMO_MODE=false
```

Create the `no-reply@` mailbox in DirectAdmin **E-Mail Accounts** first; it sends password reset links.

Then run (use the PHP 8.3 binary if `php -v` shows an older version, for example `/usr/local/php83/bin/php`):

```bash
php artisan key:generate
php artisan migrate --seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
chmod -R 775 storage bootstrap/cache
```

## 6. Create the first administrator

```bash
php artisan godram:install
```

It asks for your name, phone, email and password, and for the first Region, District and Assembly. Everything else is added from the **Structure** and **Roles** screens once you sign in.

### For the preview instead

To show the committee the app full of sample data, run this instead of `godram:install`:

```bash
php artisan db:seed --class=DemoSeeder --force
```

and set `GODRAM_DEMO_MODE=true` in `.env` (then `php artisan config:cache`). The sign-in page lists the demo accounts; every password is `GodramDemo2026`. When the preview is over, `php artisan godram:clear-demo` removes the sample data.

## 7. Turn on HTTPS

DirectAdmin: **SSL Certificates** → **Free & automatic certificate from Let's Encrypt** → include `connect.yourdomain.org`. Then visit the site with `https://`.

## 8. Add the cron job

DirectAdmin: **Cron Jobs** → every minute (`* * * * *`):

```
cd ~/godram-connect && php artisan schedule:run >> /dev/null 2>&1
```

## Updating to a new version

1. Download the newest package from GitHub Actions.
2. Upload and extract it over `~/godram-connect` (your `.env` file and uploaded photos are not in the package, so they are kept).
3. Run:

```bash
cd ~/godram-connect
php artisan down
php artisan migrate --force
php artisan db:seed --class=AccessSeeder --force   # adds any new permissions; safe to repeat
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

## Backups

- DirectAdmin **Create/Restore Backups** covers files and databases; schedule it weekly.
- The folder that matters most besides the database is `~/godram-connect/storage/app` (uploaded photos and files).

## Checking it is healthy

- `https://connect.yourdomain.org/up` returns a green page.
- `php artisan audit:verify` confirms nobody has altered the audit log.
- Errors are written to `~/godram-connect/storage/logs/laravel.log`.
