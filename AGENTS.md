# AGENTS.md

## Essentials
- **Framework:** Laravel 12.x (standard skeleton) with Vite for frontend.
- **Key entrypoints:**
  - PHP: Use `php artisan [command]` for all app management, including tests.
  - Frontend: `npm run dev` (Vite).
- **Environment:**
  - `.env` is required. Composer hook autogenerates from `.env.example` if absent.
  - Student/pass sync jobs read from the secondary `middata` DB connection in `config/database.php` (env keys: `MIDDATA_DB_HOST`, `MIDDATA_DB_PORT`, `MIDDATA_DB_DATABASE`, `MIDDATA_DB_USERNAME`, `MIDDATA_DB_PASSWORD`).

## Running Locally
- Run all dev processes in parallel (backend, queue, and Vite):
  ```bash
  composer run dev
  ```
  - This runs: `php artisan serve`, `php artisan queue:listen --tries=1`, `npm run dev` simultaneously.
- Data sync/reconciliation commands used by this project:
  - `php artisan sync:students-from-middata`
  - `php artisan sync:passes-from-middata --days=3`
  - `php artisan sync:reconcile-student-passes`

## Testing
- **Tests use Pest with Laravel integration.**
  - Run with:
    ```bash
    composer test
    # or more directly
    php artisan test
    ```
  - Do NOT call Pest or PHPUnit CLI directly; use `artisan test` for correct Laravel bootstrap and environment.
  - All test config is in `phpunit.xml`:
    - Tests run with SQLite in-memory DB (see envs in `phpunit.xml`)
    - Additional test env features (caching, queue, mailer) are set for fast, isolated test runs.
  - Project-specific feature coverage lives in `tests/Feature/StudentMonitoringTest.php` (students API status/filter behavior and `sync:reconcile-student-passes` output).

## Frontend
- **Build assets** with Vite:
  ```bash
  npm run build
  ```
- **Vite input files** are in `resources/css/app.css` and `resources/js/app.js`.
- **Tailwind** is enabled via Vite plugin in `vite.config.js`.

## Structure & Conventions
- **Standard Laravel layout**: `routes/`, `config/`, `artisan`, `public/`, etc.
- **Core monitoring domain:**
  - `students` and `passes` tables (`database/migrations/2026_04_12_000001_create_students_table.php`, `database/migrations/2026_04_12_000002_create_passes_table.php`) plus monitoring/exclusion fields and sync indexes (`2026_04_12_000003...`, `2026_04_13_000004...`, `2026_04_22_000005...`).
  - Student API endpoints are in `routes/web.php` and handled by `app/Http/Controllers/StudentController.php` (`/students/data`, `/students/filters`, `/students/data/{xgh}`).
  - Sync/reconcile commands are in `app/Console/Commands/` and scheduled in `routes/console.php`.
- **Tests:**
  - Uses Pest; extension and helpers found in `tests/Pest.php`.
  - Custom TestCase scaffolding is present in `tests/TestCase.php`.

## Setup quirks & gotchas
- Always check for a valid `.env` before running any commands.
- If you add new Composer dependencies, run `composer dump-autoload` to refresh classmap for Laravel.
- Devs should NOT add new test commands; use `composer test` (or `php artisan test`) only.
- Scheduled syncs are defined in `routes/console.php` (`sync:passes-from-middata --days=3` then `sync:reconcile-student-passes` on a 2-day cron cadence).
- CI, PR, or `.github` workflow configs are not present—verify any required check/test steps from scripts and configs, not missing infra.

---
**If in doubt, trust the executable configs and scripts over documentation.**
