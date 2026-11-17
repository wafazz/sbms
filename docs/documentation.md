# Module / System Documentation: Business Management System (BMS)

> **Status**: Active | **Last Updated**: 2026-11-17 | **Maintainer**: Iris / Fakrul

## 1. Overview & Purpose
<a id="overview"></a>
- Admin system for client projects (with timelines), invoicing, and a dashboard. v1 scope only.
- Out of scope for v1: payroll, CRM, inventory, multi-tenancy, notifications.
- Requirements and status: `Planning.md` (REQ-01 to REQ-26).

## 2. Architecture & File Structure
<a id="architecture"></a>
Monorepo-style Laravel app. The Vue SPA lives in `resources/js` and is served from one Blade shell (`resources/views/app.blade.php`). The API is under `/api/v1`. Business logic lives in `app/Services`. Controllers only validate, authorise and delegate.

| File / Component | Type | Purpose & Responsibility |
|---|---|---|
| `app/Services/InvoiceService.php` | Service | Invoice numbering, server-side totals, locking, send, cancel, delete (drafts), overdue marking |
| `app/Services/PaymentService.php` | Service | Records payments under a row lock, moves status to partially paid or paid |
| `app/Services/ProjectService.php` | Service | Project code (`PRJ-YYYY-NNN`), status and override logging, automatic progress |
| `app/Services/TaskService.php` | Service | Task rules: dates inside project range, parent in same project, done = 100% |
| `app/Services/MilestoneService.php` | Service | Milestone create, complete, reopen, delete, with activity |
| `app/Services/DashboardService.php` | Service | All dashboard figures from aggregate queries, cached 60 s |
| `app/Services/InvoicePdfService.php` | Service | Renders the invoice PDF (dompdf), logo as data URI |
| `app/Services/SettingsService.php` | Service | Company settings and logo upload |
| `app/Services/NumberSequenceService.php` | Service | Locked counters for invoice and project numbers |
| `app/Services/ActivityLogger.php` | Service | Writes project activity entries |
| `app/Support/InvoiceCalculator.php` | Support | Integer-cent money maths, half-up rounding |
| `app/Policies/*.php` | Policy | Role rules: admin-only deletes, users, settings |
| `app/Http/Controllers/Api/V1/*.php` | Controller | Thin: validate, authorise, call a service, return a resource |
| `app/Http/Requests/*.php` | Form Request | Validation for every write |
| `app/Http/Resources/*.php` | Resource | JSON shape. Passwords and internal fields never leave the server |
| `app/Exceptions/ApiExceptionRenderer.php` | Exception renderer | One error shape for every `/api` error |
| `app/Console/Commands/MarkOverdueInvoices.php` | Command | `invoices:mark-overdue`, scheduled daily in `routes/console.php` |
| `resources/js/api/http.js` | Module | Single Axios instance and interceptors (401, 403, 429, 5xx) |
| `resources/js/router/index.js`, `guards.js` | Module | Routes, auth and role guards |
| `resources/js/stores/*.js` | Store | Pinia stores per module |
| `resources/js/support/money.js` | Module | Live invoice preview (mirrors the server) |
| `resources/js/components/GanttChart.vue` | Component | frappe-gantt wrapper: drag, resize, zoom, today line, overdue and status colours |
| `resources/js/pages/*` | Pages | Dashboard, Clients, Projects (list, form, detail with tabs), Invoices (list, form, detail), Settings, Users |

## 3. Interfaces, Endpoints & Data Contracts

Base URL `/api/v1`. Errors share one shape: `{ "message": string, "errors"?: { field: [string] } }`. Only validation errors carry `errors`. Money is returned as a two-decimal string.

<a id="auth"></a>
### Auth
- `GET /sanctum/csrf-cookie` (site root): sets `XSRF-TOKEN`. Called before login.
- `POST /api/v1/auth/login` `{ email, password, remember? }` → `200 { data: User }`. Wrong credentials or a deactivated account → `422` on `email`. Rate limit: 5 attempts per minute per email and IP → `429`.
- `POST /api/v1/auth/logout` → `200`
- `GET /api/v1/auth/me` → `200 { data: User }` or `401`
- No registration endpoint (`404`).

<a id="roles"></a>
### Roles
| Action | admin | staff |
|---|---|---|
| View dashboard, clients, projects, invoices, team, settings | yes | yes |
| Create and edit clients, projects, tasks, milestones | yes | yes |
| Create, edit, send, cancel invoices; record payments | yes | yes |
| Delete clients and projects; delete draft invoices | yes | no (`403`) |
| Users; company settings (change) | yes | no (`403`) |

<a id="users"></a>
### Users (admin only)
- `GET /api/v1/users?search=&page=` (15 per page)
- `POST /api/v1/users` `{ name, email, password (min 8), role, is_active? }` → `201`
- `PUT /api/v1/users/{id}`: an admin cannot demote or deactivate themselves (`422`), and one active admin must always remain.

<a id="clients"></a>
### Clients
- `GET /api/v1/clients?q=&page=&per_page=` (max 100 per page). Search on name, company, email.
- `POST`, `GET {id}`, `PUT {id}`, `DELETE {id}` (delete is admin only, and blocked while the client has projects or invoices: `422`).

<a id="projects"></a>
<a id="project-detail"></a>
### Projects
- `GET /api/v1/projects?q=&status=&priority=&client_id=&sort=&dir=&page=&per_page=`. Sort keys: `code, name, start_date, end_date, progress, budget, created_at`. Other sort values are `422`.
- `POST` (code generated, `PRJ-YYYY-NNN`, sequential per year), `GET {id}` (includes recent activity), `PUT {id}`, `DELETE {id}` (admin, soft delete).
- `end_date` must be on or after `start_date` (`422` on `end_date`).
- Detail page tabs: Overview, Timeline, Tasks, Invoices (`?tab=`).

<a id="tasks"></a>
### Tasks
- `GET|POST /api/v1/projects/{project}/tasks`, `PUT|DELETE /api/v1/projects/{project}/tasks/{task}`
- `PATCH /api/v1/projects/{project}/tasks/{task}/schedule` `{ start_date, end_date }`: used by Gantt drag and resize.
- Rules: task dates must sit inside the project's start and end (`422` on `start_date` or `end_date`). `parent_id` must be a task in the same project and not the task itself. Status `done` sets progress to 100 unless a value is sent. Overdue = not done and `end_date` in the past.

<a id="milestones"></a>
### Milestones
- `GET|POST /api/v1/projects/{project}/milestones`, `PUT|DELETE .../milestones/{id}`
- Completing a milestone sets `completed_at`. Reopening clears it. Both are logged.

<a id="timeline"></a>
### Timeline
- `GET /api/v1/projects/{project}/timeline` → project range, tasks (with overdue flag), milestones, and `today`. One request feeds the whole Gantt chart.
- Gantt (frappe-gantt): drag a bar to move it, drag its edge to resize. Dates save on release. If the server rejects the change, the bar snaps back and the reason is shown. Zoom: Day, Week, Month. Colours: to do (grey), in progress (indigo), done (green), overdue (red), milestones (amber diamond). Frappe draws the today line.
- Internal convention: the Gantt receives end dates one day past the last day, and changes are converted back. The same rule covers one-day and multi-day tasks.

<a id="progress"></a>
### Progress
- Automatic: the average of task progress, rounded. Recalculated after every task change.
- Manual override: send `progress` on `PUT /projects/{id}`. This sets `progress_override`. Send `progress_override: false` to return to automatic.

<a id="activity"></a>
### Activity log
- Written by services on: project created, status changed, progress overridden or recalculated, task added, updated, rescheduled, or deleted, milestone added, completed, reopened, or deleted, invoice created, sent, cancelled, paid, payment recorded.
- Shown on the project Overview and on the dashboard.

<a id="invoices"></a>
### Invoices
- `GET /api/v1/invoices?status=&client_id=&project_id=&from=&to=&q=&sort=&dir=&page=`. Response includes `totals` for the whole filtered set: `count, total, amount_paid, balance`.
- `POST /api/v1/invoices` and `POST /api/v1/projects/{project}/invoices` (client and project filled from the project). Body: `client_id, project_id?, issue_date, due_date (on or after issue), tax_rate?, discount?, currency?, notes?, terms?, items[] { description, quantity, unit_price }`.
- `GET|PUT|DELETE /api/v1/invoices/{id}`. Delete is admin only and drafts only.
- Numbering: `INV-YYYY-NNNN`, sequential per issue year, locked counter.
- Totals (server source of truth): line = round(qty × price); subtotal = sum; taxable = subtotal − discount; tax = round(taxable × rate); total = taxable + tax. Discount larger than subtotal → `422`. Client-sent totals are ignored.
- Lock: once sent, a change to anything except `notes` and `terms` → `422` on `status`.
- `POST /api/v1/invoices/{id}/send`: draft only, needs at least one item.
- `POST /api/v1/invoices/{id}/cancel`: not paid or cancelled; not allowed once a payment exists.

<a id="payments"></a>
### Payments
- `POST /api/v1/invoices/{id}/payments` `{ amount, payment_date, method, reference?, notes? }` → `201` with the updated invoice.
- Allowed on sent, partially paid, or overdue invoices. Amount must be positive and not exceed the balance (`422` on `amount`).
- Status after payment: balance 0 → `paid`; some paid → `partially_paid`.
- Methods: `bank_transfer, cash, card, other`.

<a id="overdue"></a>
### Overdue
- `php artisan invoices:mark-overdue` (scheduled daily at 00:10): sent or partially paid, balance above 0, due date in the past → `overdue`. Overdue invoices stay payable and become `paid` when settled.

<a id="pdf"></a>
### PDF
- `GET /api/v1/invoices/{id}/pdf` → `application/pdf`, inline. Shows company details and logo, client, dates, items, totals, paid and balance, payment details (bank), payments received, terms and notes.

<a id="settings"></a>
### Company settings
- `GET /api/v1/settings` (all signed-in users). `POST /api/v1/settings` (admin, multipart form for the logo: PNG or JPG, up to 2 MB). POST, not PUT, because PHP only reads file uploads on POST.

<a id="dashboard"></a>
### Dashboard
- `GET /api/v1/dashboard` → cards, 12-month revenue (invoiced vs paid), projects by status, deadlines (7 and 14 days, projects and milestones), recent invoices (5), recent activity (5). Cached 60 seconds (`dashboard:summary:v1`).
- Outstanding balance = sent, partially paid and overdue invoices (all money still owed). Decided by owner (A5).

<a id="seeding"></a>
## 4. Configuration, Seeding & Dependencies
- Environment: see `.env.example` and `README.md`. Key: `APP_TIMEZONE=Asia/Kuala_Lumpur`, `SANCTUM_STATEFUL_DOMAINS` (must include host and port), `SESSION_DRIVER=database`.
- Seeders: `AdminUserSeeder` (admin), `DemoDataSeeder` (5 clients, 8 projects, 15 invoices, staff demo user). Both skip themselves in production. The demo seeder is idempotent.
- Key packages: `laravel/sanctum`, `barryvdh/laravel-dompdf`, `pestphp/pest` 4, `vue`, `vue-router`, `pinia`, `axios`, `chart.js`, `vue-chartjs`, `frappe-gantt`, `tailwindcss` 4, `vitest` 4.
- Tables: `users`, `sessions`, `number_sequences`, `clients`, `projects`, `project_tasks`, `project_milestones`, `activities`, `invoices`, `invoice_items`, `payments`, `company_settings`, plus framework tables (`cache`, `jobs`, `personal_access_tokens`).

<a id="security"></a>
## 5. Security
- Authorisation: Policies on every write. Staff cannot reach admin-only actions even by calling the API directly.
- Mass assignment: `$fillable` on every model. System-set fields (`invoice_number`, `code`, `status`, totals) are set with `forceFill` in services.
- SQL: no raw SQL built from user input. Dashboard aggregates use fixed expressions only. Search terms are escaped with `addcslashes`.
- Sessions: cookie-based (Sanctum SPA). CSRF enforced on stateful requests (verified live: `419` without a token). Login rate limited.
- Uploads: logo validated as an image (`image`, `mimes:jpg,jpeg,png`, max 2 MB), stored on the `public` disk under `logos/`.
- Errors: one JSON shape. Stack traces are hidden unless `APP_DEBUG=true`.

<a id="testing"></a>
## 6. Testing
- Backend: Pest 4, 106 tests (feature and unit). Covers auth, roles, users, clients, projects, numbering, task dates, progress, milestones, timeline, team, dashboard (incl. cache), invoices (totals, lock, numbering, status flow, PDF), payments, overdue command, settings and logo, demo seed integrity.
- Frontend: Vitest, 15 tests. Route guards, error helpers, live invoice maths (mirrors the server).
- Formatting: Laravel Pint clean.
- Not yet run: browser console check on every page, and migrations on MySQL 8 (development used MariaDB 10.4).

<a id="frontend"></a>
## 7. Frontend
- One Axios instance with interceptors: 401 → login (except auth calls), 403 and 429 → toast with the server message, 5xx and network errors → generic toast, 422 → left to the form.
- Route guards: `requiresAuth`, `guestOnly`, and `roles` (admin-only pages show an access-denied page to staff).
- Loading skeletons, empty states, and field errors from the API on every form.

<a id="deployment"></a>
## 8. Deployment
- See `README.md`, "Deploy". Summary: `composer install --no-dev`, `npm run build`, `migrate --force`, `storage:link`, config and route caches, Nginx on `public/`, scheduler cron line.

## 9. Change History & Log
| Date | Change Summary | Impacted Files | Author / Ref |
|---|---|---|---|
| 2026-11-17 | Phase 1 Setup: scaffold, Sanctum SPA auth, roles and policies, admin-only users, layout, login page | see section 2 | Iris (`REQ-01` to `REQ-05`, `REQ-22`, `REQ-23`) |
| 2026-11-17 | Fix: stateful domains include the port; login uses the attempted user | `.env.example`, `phpunit.xml`, `AuthController.php` | Iris (`REQ-02`) |
| 2026-11-17 | Phase 2: clients and projects (API, codes, activity, pages) | clients and projects controllers, services, pages | Iris (`REQ-06` to `REQ-08`) |
| 2026-11-17 | Phase 3: tasks, milestones, progress, timeline (Gantt), team list | task and milestone services, timeline controller, `GanttChart.vue` | Iris (`REQ-09` to `REQ-13`) |
| 2026-11-17 | Phase 4: invoicing, payments, status flow, PDF, overdue command, settings | invoice and payment services, `InvoiceCalculator`, PDF view, pages | Iris (`REQ-14` to `REQ-20`) |
| 2026-11-17 | Phase 5: dashboard endpoint (cached), cards, charts, lists | `DashboardService`, `pages/Dashboard.vue` | Iris (`REQ-21`) |
| 2026-11-17 | Phase 6: demo seeder, README, tests, Pint | `DemoDataSeeder`, `README.md` | Iris (`REQ-24` to `REQ-26`) |

## 10. Known Gaps and Open Questions
- MySQL 8 verification pending (`REQ-01`). Development used MariaDB 10.4.32.
- Browser console check on every page not yet run.
- Credit notes are optional and not built. Cancel is the only reversal of a sent invoice.
- Credit notes are optional and not built. Cancel is the only way to reverse a sent invoice.
- Activity log is project-scoped. Non-project invoices are not logged.
