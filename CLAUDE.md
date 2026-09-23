# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

HPY Marine is a Laravel 12 (PHP 8.2+) crewing/fleet app for a manning agency, with Blade views, Tailwind 4 and Vite. It sits in front of **ERP HPY** (a Frappe/ERPNext instance, `ERPNEXT_URL`). `docs/erpnext-integration.md` is the authoritative write-up of the integration. Read it before touching doctypes, syncing, profitability or accounting.

## Commands

```bash
composer setup                       # install, .env, key, migrate, npm build
composer dev                         # serve + queue:listen + pail + vite together
php -S 127.0.0.1:8899 server.php     # built-in server (router short-circuits real files under public/)

composer test                        # config:clear + php artisan test
php artisan test --filter=VesselCrudTest
php artisan test tests/Feature/PrincipalTest.php
vendor/bin/pint                      # code style
```

Tests run on in-memory SQLite with array cache/session (see `phpunit.xml`). ERP HPY is never called for real: tests use `Http::fake()` and set up a fake ERP session with `withSession([ErpnextClient::SESSION_KEY => [...], ErpnextClient::COMPANY_KEY => ...])`.

ERP-side setup and sample data come from artisan commands (`erp:sync-*`, `erp:seed-*`, `erpnext:sync Principal --pending`). The docs file lists them. `erp:seed-vessel-profitability` posts **submitted** GL documents that can only be cancelled, so never run it casually against a live instance.

## Architecture

**There are no local users.** Login goes to ERP HPY (`LoginController` → `ErpnextClient::attemptLogin`). The ERP `sid`, user and roles are stored in the Laravel session, and `App\Support\ErpUser` reads them back. Route middleware `erpnext` requires that session, and `erpnext.company` requires a company to be picked (`/company`). Authorization uses gates defined in `AppServiceProvider`, not model policies bound to a user. Each `{subject}.{ability}` gate delegates to `CrewCandidatePolicy` or `PrincipalPolicy`, which check ERP role names.

**`ErpnextClient` (singleton)** handles all ERP REST calls. It picks auth in this order: API key pair (`ERPNEXT_API_KEY/SECRET`, a single service identity, so ERP's per-user permissions stop applying), then the user's session `sid` (a 401 clears it and throws `ErpnextSessionExpired`, sending the user back to login), then a service-account username/password with a cached `sid` (used by jobs and commands). Child tables can only be queried when the parent doctype is named (`listChildren`).

**Where the data lives:**
- **ERP HPY is the system of record** for crew (the `Employee` doctype plus `custom_*` fields), vessels, certificates/documents, profitability (Projects linked by `Project.vessel`) and accounting. These are read and written live, with no queue. `CrewRepositoryInterface` switches between `ErpnextCrewRepository` and `EloquentCrewRepository` (local placeholder) depending on `ErpnextClient::isConfigured()` (`ERPNEXT_ENABLED` plus credentials). Both return `Crew` models, so the views don't need to know which is in use.
- **Local DB** holds the Candidate Pool, the recruitment pipeline (applications), crew assignments and principals. Principals are pushed to ERP on save and record their `erpnext_sync_status/_name/_hash/_error`. Failed pushes are retried with `erpnext:sync`. Observers (`CrewCandidateObserver`, `RecruitmentObserver`, `PrincipalObserver`) fill in uuid, running codes, `company` and created_by/updated_by, and mark rows `pending` again after an edit.
- Local-only crew data does not travel with a deploy (SQLite is gitignored). Use `php artisan crew:export-local` to write `database/exports/local-crew-data.json` (rows keyed by uuid), and `LocalCrewDataSeeder` to load it back in.

**Company scoping applies everywhere.** Local models use the `BelongsToCompany` trait and go through `->ofCompany()`, which also keeps rows whose `company` is null. ERP queries add a `["company","=",…]` filter. The current company is `ErpnextClient::company()` (the session value, falling back to `ERPNEXT_COMPANY`).

**Accounting** runs ERP's own query reports (`frappe.desk.query_report.run`), and `Services/Erpnext/FinancialReports` normalizes their inconsistent column and row shapes. Never recompute ledger figures locally. **Vessel Profitability** (`Services/Erpnext/VesselProfitability`) adds up submitted invoice and journal lines in company currency. The keyword → component mapping lives in `config/profitability.php`.

**Files:** ERP attachments are private. Always link to them through `route('erp.file', ['path' => $fileUrl])`, which proxies the file using the server's ERP credentials. Brand images are served at `/brand/{file}` rather than as direct `public/` paths, because the built-in dev server mishandles those URLs.

**Front end:** Blade + Tailwind v4 through Vite (`resources/css/app.css`, `resources/js/app.js`). There is no Vue or React. Every page includes `layouts/partials/head.blade.php` (fonts, `@vite`). Colors are `@theme` tokens in `app.css` (`brand`, `brand-d`, `navy`, `panel`, `canvas`, `line`, `muted`), so re-theme there, not in views. `app.css` also restores the v3 defaults the views were written for (border color, button cursor). Behaviour (Lucide icons, the mobile sidebar drawer, tabs, Motion animations) lives in `resources/js/ui.js` and is driven by `data-*` hooks. See the `motion` skill. Run `npm run build` after changing CSS/JS. Tests call `withoutVite()`, so they don't need a build.

User-facing wording calls the ERP "ERP HPY". Some config and env comments are in Indonesian.
