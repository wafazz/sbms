<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\MilestoneService;
use App\Services\PaymentService;
use App\Services\ProjectService;
use App\Services\TaskService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Realistic demo data: 5 clients, 8 projects with tasks and milestones, and 15 invoices with payments
 * across several months. Everything goes through the real services, so totals, statuses and activity
 * match what the app produces. Dev and demo only.
 */
class DemoDataSeeder extends Seeder
{
    public function run(ProjectService $projects, TaskService $tasks, MilestoneService $milestones, InvoiceService $invoices, PaymentService $paymentService): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DemoDataSeeder skipped in production.');

            return;
        }

        if (Client::query()->where('company', 'Acme Trading Sdn Bhd')->exists()) {
            $this->command?->info('Demo data already present. Skipping.');

            return;
        }

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $staff = $this->demoStaff();
        $today = CarbonImmutable::now()->startOfDay();

        // 5 clients
        $clients = collect([
            ['name' => 'Aisyah Rahman', 'company' => 'Acme Trading Sdn Bhd', 'email' => 'accounts@acme-trading.test', 'phone' => '+60 3-2100 1100', 'address' => "Level 12, Menara Acme\nJalan Ampang, 50450 Kuala Lumpur"],
            ['name' => 'Daniel Tan', 'company' => 'Nimbus Retail', 'email' => 'finance@nimbus.test', 'phone' => '+60 3-7988 2200', 'address' => "No. 8, Jalan SS7/2\n47301 Petaling Jaya"],
            ['name' => 'Farah Hussin', 'company' => 'Bintang Logistics', 'email' => 'ap@bintang.test', 'phone' => '+60 7-336 4400', 'address' => "Lot 45, Kawasan Perindustrian Tebrau\n81100 Johor Bahru"],
            ['name' => 'Siti Rahman', 'company' => 'Siti Rahman Studio', 'email' => 'hello@sitirahman.test', 'phone' => '+60 12-345 6789', 'address' => "23, Jalan Kemuning\n40460 Shah Alam"],
            ['name' => 'Lim Wei Ming', 'company' => 'Harbor Foods', 'email' => 'accounts@harborfoods.test', 'phone' => '+60 4-640 7700', 'address' => "Persiaran Harbour 3\n11900 Bayan Lepas, Penang"],
        ])->map(fn (array $c) => Client::create($c))->keyBy('company');

        // 8 projects. Statuses and dates are relative to today so the demo always looks current.
        $specs = [
            ['client' => 'Acme Trading Sdn Bhd', 'name' => 'Website Revamp', 'status' => 'active', 'priority' => 'high', 'from' => -60, 'to' => 45, 'budget' => 42000,
                'tasks' => [['Discovery & research', 'done', 100], ['UX and visual design', 'in_progress', 60], ['Front-end build', 'todo', 0], ['QA and launch', 'todo', 0]],
                'milestones' => [['Design sign-off', 0.35], ['Go-live', 1.0]]],
            ['client' => 'Nimbus Retail', 'name' => 'Mobile App MVP', 'status' => 'planning', 'priority' => 'medium', 'from' => 5, 'to' => 120, 'budget' => 85000,
                'tasks' => [['Requirements', 'todo', 0], ['Prototype', 'todo', 0], ['Build', 'todo', 0]],
                'milestones' => [['Prototype approved', 0.3]]],
            ['client' => 'Bintang Logistics', 'name' => 'ERP Integration', 'status' => 'active', 'priority' => 'high', 'from' => -90, 'to' => -5, 'budget' => 96000,
                'tasks' => [['Data mapping', 'done', 100], ['Module configuration', 'done', 100], ['Warehouse module go-live', 'in_progress', 70], ['Training', 'todo', 0]],
                'milestones' => [['Pilot sign-off', 0.6]]],
            ['client' => 'Siti Rahman Studio', 'name' => 'Brand Identity', 'status' => 'completed', 'priority' => 'low', 'from' => -120, 'to' => -30, 'budget' => 18000,
                'tasks' => [['Logo concepts', 'done', 100], ['Brand guidelines', 'done', 100], ['Asset handover', 'done', 100]],
                'milestones' => [['Handover', 0.95]]],
            ['client' => 'Harbor Foods', 'name' => 'Annual Maintenance Contract', 'status' => 'on_hold', 'priority' => 'medium', 'from' => -40, 'to' => 60, 'budget' => 36000,
                'tasks' => [['Site survey', 'done', 100], ['Equipment service', 'todo', 0]],
                'milestones' => [['Renewal review', 0.8]]],
            ['client' => 'Nimbus Retail', 'name' => 'Data Warehouse', 'status' => 'active', 'priority' => 'high', 'from' => -30, 'to' => 90, 'budget' => 64000,
                'tasks' => [['Source inventory', 'done', 100], ['Pipeline build', 'in_progress', 40], ['Dashboards', 'todo', 0]],
                'milestones' => [['Pilot data loaded', 0.4]]],
            ['client' => 'Harbor Foods', 'name' => 'Office Fit-out', 'status' => 'cancelled', 'priority' => 'low', 'from' => -50, 'to' => -10, 'budget' => 22000,
                'tasks' => [['Layout plan', 'done', 100], ['Procurement', 'todo', 0]],
                'milestones' => []],
            ['client' => 'Bintang Logistics', 'name' => 'CRM Customisation', 'status' => 'completed', 'priority' => 'medium', 'from' => -150, 'to' => -20, 'budget' => 30000,
                'tasks' => [['Workflow design', 'done', 100], ['Custom objects', 'done', 100], ['Rollout', 'done', 100]],
                'milestones' => [['Rollout complete', 0.97]]],
        ];

        $projectsByName = [];
        foreach ($specs as $spec) {
            $start = $today->addDays($spec['from']);
            $end = $today->addDays($spec['to']);

            $project = $projects->create([
                'client_id' => $clients[$spec['client']]->id,
                'name' => $spec['name'],
                'description' => "Demo project: {$spec['name']}.",
                'status' => $spec['status'],
                'priority' => $spec['priority'],
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'budget' => $spec['budget'],
            ], $admin);

            $projectsByName[$spec['name']] = $project;

            // Split the project span evenly across the tasks, so every task sits inside the range.
            $days = max(1, $start->diffInDays($end));
            $n = count($spec['tasks']);
            foreach ($spec['tasks'] as $i => [$title, $status, $progress]) {
                $taskStart = $start->addDays((int) floor($i * $days / $n));
                $taskEnd = $start->addDays((int) floor(($i + 1) * $days / $n) - 1);
                $tasks->create($project, [
                    'title' => $title,
                    'start_date' => $taskStart->toDateString(),
                    'end_date' => ($taskEnd->lessThan($taskStart) ? $taskStart : $taskEnd)->toDateString(),
                    'status' => $status,
                    'progress' => $progress,
                    'assigned_to' => $i % 2 === 0 ? $admin->id : $staff->id,
                    'sort_order' => $i,
                ], $admin);
            }

            foreach ($spec['milestones'] as [$title, $fraction]) {
                $milestones->create($project, [
                    'title' => $title,
                    'due_date' => $start->addDays((int) round($days * $fraction))->toDateString(),
                ], $admin);
            }
        }

        // 15 invoices across several months.
        $invoiceSpecs = [
            // [client, project, days ago issued, items, payments as [days ago paid, share of total], send?, cancel?]
            ['Acme Trading Sdn Bhd', 'Website Revamp', -100, [['UX workshop', 1, 3500], ['UX design', 40, 120]], [[-80, 1.0]]],
            ['Acme Trading Sdn Bhd', 'Website Revamp', -70, [['Front-end build', 80, 95]], [[-55, 0.5]]],
            ['Nimbus Retail', 'Data Warehouse', -65, [['Data pipeline setup', 1, 9800]], [[-40, 1.0]]],
            ['Bintang Logistics', 'ERP Integration', -95, [['ERP module licence', 1, 12000], ['Implementation', 60, 150]], [[-70, 0.3]]],
            ['Bintang Logistics', 'CRM Customisation', -140, [['Custom workflows', 25, 180]], [[-110, 1.0]]],
            ['Siti Rahman Studio', 'Brand Identity', -115, [['Logo suite', 1, 4500], ['Brand guidelines', 1, 2800]], [[-100, 1.0]]],
            ['Harbor Foods', 'Annual Maintenance Contract', -35, [['Monthly maintenance', 3, 1200]], []],
            ['Harbor Foods', 'Annual Maintenance Contract', -10, [['Monthly maintenance', 3, 1200]], []],
            ['Harbor Foods', 'Office Fit-out', -45, [['Fit-out phase 1', 1, 8600]], [], 'cancel'],
            ['Nimbus Retail', 'Mobile App MVP', -5, [['MVP discovery', 1, 2000]], [], 'draft'],
            ['Acme Trading Sdn Bhd', null, -20, [['Hosting, Q4', 3, 450]], [[-8, 0.25]]],
            ['Bintang Logistics', 'ERP Integration', -20, [['Support retainer', 1, 1500]], []],
            ['Siti Rahman Studio', null, -85, [['Photo shoot', 1, 1800]], [[-60, 1.0]]],
            ['Nimbus Retail', 'Data Warehouse', -15, [['Warehouse consulting', 20, 160]], [[-5, 0.5]]],
            ['Harbor Foods', null, -60, [['Design consult', 4, 250]], [[-55, 1.0]]],
        ];

        foreach ($invoiceSpecs as $spec) {
            [$clientCompany, $projectName, $issueOffset, $items, $payments] = $spec;
            $flag = $spec[5] ?? null;

            $issue = $today->addDays($issueOffset);
            $project = $projectName ? $projectsByName[$projectName] : null;
            $client = $clients[$clientCompany];

            $invoice = $invoices->create([
                'client_id' => $client->id,
                'issue_date' => $issue->toDateString(),
                'due_date' => $issue->addDays(30)->toDateString(),
                'tax_rate' => 6,
                'discount' => $issueOffset === -5 ? 100 : 0,
                'notes' => $project ? "Re: {$project->code} {$project->name}" : null,
                'terms' => 'Payment within 30 days. Bank transfer preferred.',
                'items' => array_map(fn ($i) => ['description' => $i[0], 'quantity' => $i[1], 'unit_price' => $i[2]], $items),
            ], $admin, $project);

            if ($flag === 'draft') {
                continue;
            }

            $invoices->send($invoice, $admin);

            if ($flag === 'cancel') {
                $invoices->cancel($invoice, $admin);

                continue;
            }

            foreach ($payments as [$daysAgo, $share]) {
                $amount = round((float) $invoice->fresh()->total * $share, 2);
                $paymentService->record($invoice->fresh(), [
                    'amount' => $amount,
                    'payment_date' => $today->addDays($daysAgo)->toDateString(),
                    'method' => PaymentMethod::BankTransfer,
                    'reference' => 'TRX-'.str_pad((string) $invoice->id, 4, '0', STR_PAD_LEFT),
                ], $admin);
            }
        }

        // Anything still unpaid and past its due date becomes overdue, as the daily job would do.
        $invoices->markOverdue($today);

        $this->command?->info(sprintf(
            'Demo data: %d clients, %d projects, %d invoices (%d overdue).',
            Client::count(),
            Project::count(),
            Invoice::count(),
            Invoice::where('status', 'overdue')->count(),
        ));
    }

    private function demoStaff(): User
    {
        return User::query()->updateOrCreate(
            ['email' => 'staff@example.com'],
            ['name' => 'Demo Staff', 'password' => 'password', 'role' => 'staff', 'is_active' => true],
        );
    }
}
