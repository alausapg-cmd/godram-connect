# GODRAM Connect

The digital home of the GOFAMINT Drama & Film Ministry (GODRAM): one place for members, Assemblies, Districts and Regions to keep records, report ministry activity, share announcements, and (in later phases) watch, learn and earn certificates.

## What is in Phase 1

- **Organisation tree**: National, Regions, Districts and Assemblies, managed by administrators.
- **Members**: one record per person with a Member ID (`GDM-000123`) and a unique phone number, placement history, skills, status changes, self sign-up with Assembly approval, transfers between Assemblies, duplicate warnings and CSV export.
- **Roles and permissions**: Assembly, District, Regional and National Coordinators, plus administrators. Everyone sees their own branch and nothing outside it.
- **Activity reports**: draft with autosave, submit, review by the level directly above, return for correction, approve, publish as a public highlight. Photos are private until published.
- **Announcements**: aimed at the public, a branch of the tree or a role. Public, national and role-wide notices are approved before they go out.
- **Dashboards** for each level, and a public site with the ministry's history and archive.
- **Audit log** with a tamper-evident hash chain (`php artisan audit:verify`).
- Installable on phones as an app, with an offline page.

## Running it locally

Requires PHP 8.3+, Composer and Node 22.

```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan db:seed --class=DemoSeeder   # optional sample data
php artisan serve
```

With the demo data, set `GODRAM_DEMO_MODE=true` in `.env`; the sign-in page then lists the demo accounts. Every demo password is `GodramDemo2026`. Remove the demo data with `php artisan godram:clear-demo`.

For a real installation without demo data, run `php artisan godram:install` to create the first administrator and the first Region, District and Assembly.

## Tests

```bash
php artisan test
```

The suite covers the acceptance scenarios from the brief (register a member, see them at District and Region level, transfer them, see the change), the report workflow, announcement targeting, sign-in rules and the audit chain.

## Hosting

See [docs/DEPLOY-DIRECTADMIN.md](docs/DEPLOY-DIRECTADMIN.md) for WhoGoHost (DirectAdmin) shared hosting. Every push to `main` also builds a ready-to-upload zip in GitHub Actions.
