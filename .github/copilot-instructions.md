## MoonTrader — Copilot / AI Contributor Quick Guide

This file gives focused, repository-specific guidance so an AI coding assistant can be immediately productive. Keep recommendations short and concrete. Reference files are listed where behaviours are defined.

1) Project type & quick start
- Laravel 12 PHP app (PHP ^8.2) with a Vite+Tailwind frontend and Livewire components. Key manifests:
  - `composer.json` (root) — composer scripts: `setup`, `dev`, `test` (use these for canonical workflows)
  - `package.json` (root) — front-end scripts: `dev`, `build`, plus `scripts/mcp-postgres.sh` for MCP DB setup
  - `artisan` — Laravel CLI entrypoint

  Example commands (run from repo root):
  - Full setup: `composer run setup` (installs deps, copies .env, runs migrations, builds assets)
  - Start local dev (server + queue + logs + vite): `composer run dev`
  - Frontend only: `npm run dev`
  - Build assets: `npm run build`
  - Run tests: `composer run test` (delegates to `php artisan test` / Pest)

2) Architecture & where to change behavior
- App code under `app/` follows a light domain layering:
  - `app/Models` — Eloquent models
  - `app/Actions`, `app/Services` — business logic / orchestrators (prefer adding new domain logic here)
  - `app/Helpers`, `app/Traits` — reusable helpers and traits used across services
  - `app/Policies` — authorization rules (Spatie permissions are in use; check `config/permission.php`)
  - Routes: `routes/web.php`, `routes/api.php`, `routes/console.php`


3) Testing & CI expectations
- Tests use Pest and PHPUnit. See `phpunit.xml` — test environment uses an in-memory SQLite DB by default (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) and `QUEUE_CONNECTION=sync`. Use `composer run test` or `php artisan test` to run the test suite.

4) Common packages & integration points to be aware of
- Spatie: `laravel-permission`, `laravel-query-builder`, `laravel-activitylog` — permission, query patterns and activity logging live in many models and controllers.
- Livewire & Jetstream are used for interactive UI components and auth flows.
- DOMPDF for PDF generation (`barryvdh/laravel-dompdf`).
- Frontend: Vite + Tailwind (see `vite.config.js`, `tailwind.config.js`).

5) Project-specific conventions
- Business logic should live in `app/Actions` or `app/Services` rather than placing complex logic directly in controllers or Livewire components.
- Helpers / small utilities live in `app/Helpers` and reusable behaviour in `app/Traits`.
- Permission checks tend to use policies under `app/Policies` and Spatie role/permission methods on models.

6) When modifying DB or migrations
- There is a `database/` folder with migrations and seeders. Local quick setup expects an SQLite DB by default (see composer `post-create-project-cmd`). For MCP/Postgres development, check `scripts/mcp-postgres.sh` and `docs/MCP_SETUP.md`.

7) Frontend specifics
- Livewire components (server-driven UI) interoperate with Vite-built assets. For iteration, use `npm run dev`; for integrated dev environment that also runs PHP server & background workers use `composer run dev`.


9) Helpful file examples to read when implementing features
- `app/Services` and `app/Actions` for domain flow examples
- `app/Policies/*` for authorization patterns
- `routes/api.php` and `app/Http/Controllers` (or Livewire components) for API and UI patterns

10) Avoid assumptions
- Do not change core accounting logic without cross-checking the docs. Tests frequently assert accounting invariants — run the test suite after changes.

If anything here is unclear or you want me to include additional examples (for a specific feature, Service class, or a sample PR message), say which area and I'll extend the file.
