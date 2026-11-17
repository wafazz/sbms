# Project Plan: Business Management System (BMS)

> **Status**: All six phases built. Automated checks pass. Open items: MySQL 8 verification, browser console check, owner review. | **Last Updated**: 2026-11-17 | **Tier**: T2 Full | **Maintainer**: Iris

---

## 0. Confirmed Stack & Decisions

| Area | Choice | Note |
|---|---|---|
| Backend | Laravel 13.42 (PHP 8.3+), REST API under `/api/v1` | Latest, per owner. Spec had 11, which is out of security support. |
| Auth | Laravel Sanctum SPA cookie auth, `database` session driver | No public registration |
| Frontend | Vue 3 (`<script setup>`), Vite 7, Vue Router 4, Pinia 3, Axios, Tailwind CSS 4 | `resources/js` |
| Charts | Chart.js 4 via vue-chartjs 5 | Dashboard |
| Gantt | frappe-gantt 1.2 | Drag, resize, zoom, today line |
| Database | MySQL 8 target (InnoDB, utf8mb4). Dev DB: MariaDB 10.4.32 | Verify on MySQL 8 |
| PDF | barryvdh/laravel-dompdf 3 | |
| Tests | Pest 4 (PHPUnit 12) backend, Vitest 4 frontend | |
| Formatting | Laravel Pint | Clean |

### Assumptions (owner to confirm)
- **A1 User management:** built, admin only. CONFIRMED by owner.
- **A2 Activity log:** built as an `activities` table, project-scoped. CONFIRMED.
- **A3 Invoice numbering:** built with a locked `number_sequences` table, per year. CONFIRMED.
- **A4 Project code:** built the same way, `PRJ-YYYY-NNN`. CONFIRMED.
- **A5 Outstanding balance:** DECIDED. Sent, partially paid and overdue invoices (all money still owed). Changed from the literal spec wording.
- **A6 Deletes:** CONFIRMED. Clients and projects are admin only. Invoices: drafts only, admin only. Everything else is cancelled.

---

## 1. Requirements & Traceability Matrix

Status key: `Planned` | `In-Progress` | `Verified` (automated tests pass) | `Done` (verified and reviewed by owner).

| Req ID | Feature / Objective | Implementation Files | Test Files | Documentation | Status |
|---|---|---|---|---|---|
| `REQ-01` | Scaffold: Laravel 13, Vue, Vite, Tailwind, Sanctum, MySQL config, layout shell | `composer.json`, `package.json`, `vite.config.js`, `config/database.php`, `resources/js/app.js`, `resources/views/app.blade.php` | `tests/Feature/AppShellTest.php`, production build | `docs/documentation.md#architecture` | `In-Progress` (MySQL 8 check pending) |
| `REQ-02` | Login, logout, me, no registration, login rate limit | `app/Http/Controllers/Api/V1/AuthController.php`, `MeController.php`, `app/Http/Requests/LoginRequest.php`, `app/Providers/AppServiceProvider.php`, `routes/api.php` | `tests/Feature/AuthTest.php` | `docs/documentation.md#auth` | `Verified` (live smoke test too) |
| `REQ-03` | Roles `admin` and `staff`, enforced by Policies | `app/Enums/UserRole.php`, `app/Policies/*.php` | `tests/Feature/UserManagementTest.php`, `tests/Feature/ClientTest.php`, `tests/Feature/ProjectTest.php`, `tests/Feature/InvoiceTest.php` | `docs/documentation.md#roles` | `Verified` |
| `REQ-04` | Seeded admin `admin@example.com` / `password` (dev only) | `database/seeders/AdminUserSeeder.php` | `tests/Feature/AppShellTest.php` | `docs/documentation.md#seeding` | `Verified` |
| `REQ-05` | User management, admin only (A1) | `UserController.php`, `UserService.php`, `UserPolicy.php`, `pages/Users/Index.vue` | `tests/Feature/UserManagementTest.php` | `docs/documentation.md#users` | `Verified` |
| `REQ-06` | Clients CRUD, soft delete, search, pagination | `ClientController.php`, `Client.php`, `ClientPolicy.php`, `pages/Clients/Index.vue` | `tests/Feature/ClientTest.php` | `docs/documentation.md#clients` | `Verified` |
| `REQ-07` | Projects CRUD, code `PRJ-YYYY-NNN`, date validation, list with search, filters, sort, pagination | `ProjectController.php`, `ProjectService.php`, `Project.php`, `ProjectPolicy.php`, `pages/Projects/Index.vue`, `pages/Projects/Form.vue` | `tests/Feature/ProjectTest.php` | `docs/documentation.md#projects` | `Verified` |
| `REQ-08` | Project detail page with tabs: Overview, Timeline, Tasks, Invoices | `pages/Projects/Show.vue`, `tabs/OverviewTab.vue`, `tabs/TimelineTab.vue`, `tabs/TasksTab.vue`, `tabs/InvoicesTab.vue` | `tests/Feature/TimelineTest.php` (API); browser check pending | `docs/documentation.md#project-detail` | `Verified` (browser check pending) |
| `REQ-09` | Tasks API: parent, sort, status, progress, assignee. Dates must sit inside project range | `ProjectTaskController.php`, `TaskService.php`, `ProjectTask.php`, `StoreTaskRequest.php` | `tests/Feature/TaskDateValidationTest.php` | `docs/documentation.md#tasks` | `Verified` |
| `REQ-10` | Milestones: due date, completion, activity | `ProjectMilestoneController.php`, `MilestoneService.php`, `ProjectMilestone.php` | `tests/Feature/TimelineTest.php` | `docs/documentation.md#milestones` | `Verified` |
| `REQ-11` | Gantt timeline: bars, milestones, today line, progress fill, zoom, status colours, overdue red, drag and resize saved via API | `components/GanttChart.vue`, `resources/css/app.css`, `ProjectTimelineController.php`, `pages/Projects/tabs/TimelineTab.vue` | `tests/Feature/TaskDateValidationTest.php`, `tests/Feature/TimelineTest.php`; drag UI check pending | `docs/documentation.md#timeline` | `Verified` (drag and today line need a browser check) |
| `REQ-12` | Project progress = average of tasks, manual override | `ProjectService.php` (`recalculateProgress`) | `tests/Feature/TimelineTest.php` | `docs/documentation.md#progress` | `Verified` |
| `REQ-13` | Activity log on important changes, shown on Overview | `Activity.php`, `ActivityLogger.php`, `tabs/OverviewTab.vue` | `tests/Feature/ProjectTest.php`, `tests/Feature/PaymentTest.php` | `docs/documentation.md#activity` | `Verified` |
| `REQ-14` | Invoices: `INV-YYYY-NNNN`, items, server totals (source of truth), live UI preview, lock after send, cancel instead of delete | `InvoiceController.php`, `InvoiceService.php`, `InvoiceCalculator.php`, `NumberSequenceService.php`, `pages/Invoices/Form.vue`, `resources/js/support/money.js` | `tests/Feature/InvoiceTest.php`, `tests/Unit/InvoiceCalculatorTest.php`, `resources/js/__tests__/money.spec.js` | `docs/documentation.md#invoices` | `Verified` |
| `REQ-15` | Invoice list: filters (status, client, project, dates), search by number, totals row | `InvoiceController.php` (index), `pages/Invoices/Index.vue` | `tests/Feature/InvoiceTest.php` | `docs/documentation.md#invoices` | `Verified` |
| `REQ-16` | Create invoice from project (pre-fills client and project) | `ProjectInvoiceController.php`, `pages/Invoices/Form.vue` | `tests/Feature/InvoiceTest.php` | `docs/documentation.md#invoices` | `Verified` |
| `REQ-17` | Payments: status to partially paid or paid, balance updates, transaction and lock | `PaymentController.php`, `PaymentService.php`, `Payment.php`, `pages/Invoices/Show.vue` | `tests/Feature/PaymentTest.php` | `docs/documentation.md#payments` | `Verified` |
| `REQ-18` | `invoices:mark-overdue` daily command, scheduled | `app/Console/Commands/MarkOverdueInvoices.php`, `routes/console.php`, `InvoiceService::markOverdue` | `tests/Feature/PaymentTest.php` | `docs/documentation.md#overdue` | `Verified` |
| `REQ-19` | Invoice PDF with company details, client, items, totals, payment info | `InvoicePdfController.php`, `InvoicePdfService.php`, `resources/views/pdf/invoice.blade.php` | `tests/Feature/InvoiceTest.php` (PDF) | `docs/documentation.md#pdf` | `Verified` (visual check pending) |
| `REQ-20` | Company settings: name, address, logo, tax number, default tax rate, bank details | `SettingsController.php`, `SettingsService.php`, `CompanySetting.php`, `CompanySettingPolicy.php`, `pages/Settings/Index.vue` | `tests/Feature/PaymentTest.php` | `docs/documentation.md#settings` | `Verified` |
| `REQ-21` | Dashboard: 6 cards, 12-month chart, projects by status donut, deadlines 7 and 14 days, recent invoices and activity. Single endpoint, aggregate queries, cached 60 s | `DashboardController.php`, `DashboardService.php`, `pages/Dashboard.vue` | `tests/Feature/DashboardTest.php` | `docs/documentation.md#dashboard` | `Verified` (charts need a browser check) |
| `REQ-22` | Frontend shell: sidebar, top bar, Pinia stores, Axios interceptors, guards, skeletons, empty states, form errors, reusable components | `layouts/AppLayout.vue`, `api/http.js`, `api/errors.js`, `router/*.js`, `stores/*.js`, `components/*.vue` | `resources/js/__tests__/*.spec.js` (15 tests) | `docs/documentation.md#frontend` | `Verified` |
| `REQ-23` | Security and quality: `$fillable`, CSRF and stateful session, no raw SQL, secrets in `.env`, Pint, consistent JSON errors | all `app/` files, `.env.example`, `bootstrap/app.php` | all Feature tests; live CSRF check (`419` without token) | `docs/documentation.md#security` | `Verified` (review pending) |
| `REQ-24` | Backend feature tests: auth, project CRUD, task date validation, invoice numbering, totals, payment transitions, roles | `tests/Feature/*`, `tests/Unit/*` | the tests (106 backend, 15 frontend) | `docs/documentation.md#testing` | `Verified` |
| `REQ-25` | Seeders and factories: 5 clients, 8 projects with tasks and milestones, 15 invoices with payments across months | `database/factories/*`, `database/seeders/DemoDataSeeder.php` | `tests/Feature/DemoDataSeedTest.php` | `docs/documentation.md#seeding` | `Verified` |
| `REQ-26` | README: setup, env vars, scheduler cron line, default login, test, deploy | `README.md` | n/a (manual review) | `README.md`, `docs/documentation.md#deployment` | `Verified` |

---

## 2. Phase Breakdown

### Phase 1: Setup (`REQ-01` to `REQ-05`, `REQ-22`, `REQ-23`)
- [x] Scaffold Laravel 13 with Sanctum, Vite, Vue 3, Pinia, Vue Router, Axios, Tailwind
- [x] MySQL config. Dev DB is MariaDB 10.4.32. MySQL 8 check pending.
- [x] Migrations: users (role, is_active), sessions
- [x] Auth: login, logout, me, rate limit, no registration
- [x] Roles and Policies
- [x] Admin-only user management (A1)
- [x] Layout shell, route guards, shared components
- [x] Seeded admin
- [x] Login page: split card with the reference illustration

### Phase 2: Clients and Projects (`REQ-06`, `REQ-07`, `REQ-08` partial)
- [x] Migrations: clients, projects, number_sequences
- [x] Client CRUD API and pages
- [x] Project CRUD API, code generation, date validation, list with search, filter, sort, paginate
- [x] Project form, detail page with tabs

### Phase 3: Timeline (`REQ-08`, `REQ-09` to `REQ-13`)
- [x] Migrations: project_tasks, project_milestones, activities
- [x] Task and milestone APIs with date validation inside project range
- [x] Progress: automatic average, manual override, return to automatic
- [x] Activity logging on important changes, shown on Overview
- [x] Gantt tab: bars, milestones, today line, progress fill, zoom, status colours, overdue red, drag and resize saved via API
- [ ] Browser check of drag and resize (needs a browser session)

### Phase 4: Invoicing (`REQ-14` to `REQ-20`)
- [x] Migrations: company_settings, invoices, invoice_items, payments
- [x] InvoiceService (numbering, server totals, lock) and PaymentService (status transitions, transaction and lock)
- [x] Invoice list (filters, search, totals row), form with live preview, create from project
- [x] Record payments
- [x] `invoices:mark-overdue` command and daily schedule
- [x] PDF template with company settings and logo
- [x] Settings page with logo upload

### Phase 5: Dashboard (`REQ-21`)
- [x] DashboardService: aggregate queries, cached 60 s
- [x] Single `GET /api/v1/dashboard` endpoint
- [x] Six cards, revenue chart, donut, deadlines, recent invoices, recent activity

### Phase 6: Polish (`REQ-24` to `REQ-26`)
- [x] DemoDataSeeder: 5 clients, 8 projects, tasks and milestones, 15 invoices with payments across months
- [x] Full test pass, Pint clean, production build, live smoke test
- [x] README
- [x] Final review of this matrix (statuses set from test evidence)
- [ ] `coresentinel verify`: see the result in the session notes

---

## 3. Definition of Done (from spec)
- [x] `composer install && npm install && php artisan migrate --seed && npm run dev` gives a working app (verified on the dev DB)
- [x] All automated tests pass (106 backend, 15 frontend)
- [ ] No console errors in the browser (browser check not yet run)
- [x] Every feature works end to end with demo data (verified through the API; UI checked by build only)
- [x] README explains run, test, and deploy

---

## 4. Open Questions
1. A1 to A4 and A6 confirmed. A5 decided (partially paid included). Credit notes: optional, not built.
2. MySQL 8: run `migrate --seed` and the test suite on MySQL 8 before release.
3. Browser pass: open each page once with `npm run dev` and `php artisan serve` to check the Gantt drag, the charts, and the console.
4. Credit notes: optional, deferred. Cancel is the only reversal for now.
5. Deploy target: generic VPS with Nginx and PHP-FPM is assumed (see README).

---

## 5. Change Log

| Date | Change | Author / Ref |
|---|---|---|
| 2026-11-17 | Initial plan: 26 requirements, folder structure, ERD, 6 phases | Iris (`REQ-01` to `REQ-26`) |
| 2026-11-17 | Phase 1 built and verified; login page redesigned with the reference illustration | Iris |
| 2026-11-17 | Phases 2 to 6 built. Statuses set from test evidence. Open items listed in section 4. | Iris |
