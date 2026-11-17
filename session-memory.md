# Session Memory - Business Management System (BMS)
> Last updated: 2026-11-17 16:00

## Session Context
- **Project**: Business Management System (BMS)
- **Profile**: `C:\Users\fakrul.hakim\OneDrive - Daythree Digital Berhad\Desktop\CS\Projects\24-business-management-system.md`
- **Status**: all six phases built. Automated checks pass. Browser pass and MySQL 8 verification still open.
- **Focus**: owner review, then the browser pass.

## Current Tasks
- [x] Phase 1 to 6 built
- [x] Tests: 106 Pest, 15 Vitest, production build, Pint clean, live smoke test (login, dashboard, projects, invoice totals, PDF)
- [x] README, docs, Planning.md updated
- [x] CoreSentinel verify: 100/100 on runnable checks (3 unknown: not a git repo)
- [ ] Browser pass: Gantt drag and resize, charts, console errors
- [ ] MySQL 8: migrate, seed, test
- [ ] Owner decisions A1 to A6 (A5 matters most)

## Working Memory
### Active Context
- Dev DB `bms`, test DB `bms_test` (MariaDB 10.4.32, root, no password)
- PHP: `C:\php84\php.exe`. Composer: `C:\php84\php.exe C:\xampp\php\composer.phar`
- Accounts: admin@example.com and staff@example.com (password `password`, dev only)
- Seed: `php artisan migrate:fresh --seed` (demo data: 5 clients, 8 projects, 15 invoices, 3 overdue)

### Decisions Made
- Laravel 13.42, not 11 (owner: latest)
- Invoice money in integer cents (`App\Support\InvoiceCalculator`), mirrored in `resources/js/support/money.js`
- Outstanding balance = sent + overdue only (spec). Owner to confirm (A5).
- Gantt: end dates passed one day past the last day, converted back on change
- Settings saved by POST (multipart). PHP only reads file uploads on POST.
- `CompanySetting::current()` looks up the first row, not id 1.
- Tests act as a user via `actAs()` (clears cached guards). Feature tests send `Origin: http://localhost`.

### Blockers / Open Questions
- Owner: A1 to A6 (see Planning.md section 4)
- MySQL 8 not installed here. Run migrations and tests there before release.
- Browser check not run.

## Recent Changes
| File | Change | Status |
|---|---|---|
| `app/Services/*` | Invoice, payment, project, task, milestone, dashboard, PDF, settings, sequence services | done |
| `app/Http/Controllers/Api/V1/*` | clients, projects, tasks, milestones, timeline, invoices, payments, PDF, settings, dashboard, team | done |
| `resources/js/pages/*` | Clients, Projects (list, form, detail, tabs), Invoices (list, form, detail), Settings, Dashboard, Login | done |
| `resources/js/components/GanttChart.vue` | frappe-gantt wrapper | done (browser check pending) |
| `database/seeders/DemoDataSeeder.php` | demo data via real services | done |
| `README.md`, `docs/documentation.md`, `Planning.md` | docs and traceability | done |

## Session Recap
### What Was Done
- Phase 1 approved. Phases 2 to 6 built in the same session, on owner instruction to finish.
- Found and fixed along the way: stateful domain port, Sanctum guard caching across requests in tests, firstOrCreate default null, resource 201 on newly created models, markOverdue type, seeder variable collision.

### Where We Left Off
- Built and automated-tested. Waiting on: owner review, browser pass, MySQL 8 check, decisions A1 to A6.