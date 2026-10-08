# GODRAM Connect: notes for contributors

Laravel 13 modular monolith (PHP 8.3+), Blade + Alpine.js + Tailwind v4, built with Vite. SQLite in development and tests, MySQL on WhoGoHost shared hosting (DirectAdmin). No Redis, no long-running workers: queues are `sync`, scheduled work runs from cron.

## Key ideas

- `org_units` is one tree with a materialised `path` (`/1/4/17/`). "Everything under X" is `where path like 'X.path%'`.
- A member's Assembly is their open row in `member_placements`; transfers close one row and open another. Never edit placements in place.
- Roles are assigned to a member at an org unit (`role_assignments`). Ask `App\Services\Access` for permissions and scope; never check role names in controllers or views. In Blade use `can_do('permission', $unit)`.
- Reports are reviewed by the level directly above (`Access::holdsAt` on the parent unit). Higher levels view and comment.
- Every important change goes through `AuditLogger::log`, which chains entries with an HMAC. Do not update or delete `audit_logs` rows.
- Uploaded images are re-encoded to WebP by `ImageStore` and served through permission-checked routes, never from a public folder.
- Phone numbers are stored as +234 numbers; use `App\Support\Phone`.
- Demo rows carry `is_demo`; `godram:clear-demo` removes them.

## Commands

- `php artisan test` (always run before pushing)
- `npm run build`
- `php artisan audit:verify`
- `php artisan db:seed --class=DemoSeeder`

## Conventions

- British English in the interface, plain words, no jargon. Titles in the ministry's own vocabulary (Assembly, District, Region, Coordinator).
- Design tokens live in `resources/css/app.css` (stage, curtain, poster, gold, paper, ink). Reuse the component classes there before writing new styles.
- Mobile first: most users are on Android phones with patchy data.
