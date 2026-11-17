<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectTask;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DemoDataSeeder;

beforeEach(function () {
    $this->seed(AdminUserSeeder::class);
    $this->seed(DemoDataSeeder::class);
});

it('seeds 5 clients, 8 projects and 15 invoices', function () {
    expect(Client::count())->toBe(5)
        ->and(Project::count())->toBe(8)
        ->and(Invoice::count())->toBe(15);
});

it('gives every project tasks and milestones', function () {
    Project::all()->each(function (Project $project) {
        expect($project->tasks()->count())->toBeGreaterThan(0);
    });

    expect(ProjectMilestone::count())->toBeGreaterThan(0);
});

it('keeps every seeded task inside its project date range', function () {
    ProjectTask::with('project')->get()->each(function (ProjectTask $task) {
        expect($task->start_date->toDateString())->toBeGreaterThanOrEqual($task->project->start_date->toDateString())
            ->and($task->end_date->toDateString())->toBeLessThanOrEqual($task->project->end_date->toDateString());
    });
});

it('leaves invoice totals consistent with the stored balance', function () {
    Invoice::all()->each(function (Invoice $invoice) {
        expect(round((float) $invoice->total - (float) $invoice->amount_paid, 2))->toBe(round((float) $invoice->balance, 2));
    });
});

it('spreads payments across several months and includes overdue invoices', function () {
    $months = Payment::query()
        ->selectRaw("DATE_FORMAT(payment_date, '%Y-%m') as ym")
        ->groupBy('ym')
        ->get();

    expect($months->count())->toBeGreaterThanOrEqual(3)
        ->and(Invoice::where('status', 'overdue')->count())->toBeGreaterThan(0);
});

it('does not seed twice', function () {
    $this->seed(DemoDataSeeder::class);

    expect(Client::count())->toBe(5)
        ->and(Invoice::count())->toBe(15);
});
