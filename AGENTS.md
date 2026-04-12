# AGENTS.md

## Essentials
- **Framework:** Laravel 12.x (standard skeleton) with Vite for frontend.
- **Key entrypoints:**
  - PHP: Use `php artisan [command]` for all app management, including tests.
  - Frontend: `npm run dev` (Vite).
- **Environment:**
  - `.env` is required. Composer hook autogenerates from `.env.example` if absent.

## Running Locally
- Run all dev processes in parallel (backend, queue, and Vite):
  ```bash
  composer run dev
  ```
  - This runs: `php artisan serve`, `php artisan queue:listen --tries=1`, `npm run dev` simultaneously.

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

## Frontend
- **Build assets** with Vite:
  ```bash
  npm run build
  ```
- **Vite input files** are in `resources/css/app.css` and `resources/js/app.js`.
- **Tailwind** is enabled via Vite plugin in `vite.config.js`.

## Structure & Conventions
- **Standard Laravel layout**: `routes/`, `config/`, `artisan`, `public/`, etc.
- **Tests:**
  - Uses Pest; extension and helpers found in `tests/Pest.php`.
  - Custom TestCase scaffolding is present in `tests/TestCase.php`.

## Setup quirks & gotchas
- Always check for a valid `.env` before running any commands.
- If you add new Composer dependencies, run `composer dump-autoload` to refresh classmap for Laravel.
- Devs should NOT add new test commands; use `composer test` (or `php artisan test`) only.
- CI, PR, or `.github` workflow configs are not present—verify any required check/test steps from scripts and configs, not missing infra.

---
**If in doubt, trust the executable configs and scripts over documentation.**

