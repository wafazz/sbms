<?php

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    $this->staff = User::factory()->create();
    $this->client = Client::factory()->create();
});

function invoiceRow(Client $client, array $overrides = []): Invoice
{
    return Invoice::factory()->create(array_merge([
        'client_id' => $client->id,
        'issue_date' => now()->startOfMonth()->toDateString(),
        'due_date' => now()->addDays(20)->toDateString(),
        'status' => 'sent',
        'total' => 1000,
        'balance' => 1000,
    ], $overrides));
}

it('returns the six summary cards with correct values', function () {
    Project::factory()->create(['status' => 'active', 'end_date' => now()->addMonths(2)]);
    Project::factory()->create(['status' => 'planning', 'end_date' => now()->subDays(3)]); // overdue
    Project::factory()->create(['status' => 'completed', 'end_date' => now()->subDays(3)]); // not overdue

    $paid = invoiceRow($this->client, ['status' => 'partially_paid', 'total' => 500, 'balance' => 200]);
    invoiceRow($this->client, ['status' => 'overdue', 'total' => 300, 'balance' => 300, 'issue_date' => now()->subMonths(2)->toDateString()]);
    invoiceRow($this->client, ['status' => 'draft', 'total' => 999, 'balance' => 999]);

    Payment::create(['invoice_id' => $paid->id, 'amount' => 300, 'payment_date' => now()->startOfMonth()->toDateString(), 'method' => 'cash']);

    $this->actAs($this->staff)
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.cards.active_projects', 1)
        ->assertJsonPath('data.cards.overdue_projects', 1)
        // Invoiced this month: only the partially paid one (500). Draft and last month's invoice are excluded.
        ->assertJsonPath('data.cards.invoiced_this_month', '500.00')
        ->assertJsonPath('data.cards.paid_this_month', '300.00')
        // Outstanding: sent + partially paid + overdue (300 overdue + 200 partially paid).
        ->assertJsonPath('data.cards.outstanding_balance', '500.00')
        ->assertJsonPath('data.cards.overdue_invoices', 1);
});

it('returns twelve months of revenue, oldest first, with zeros for empty months', function () {
    invoiceRow($this->client, ['issue_date' => now()->subMonths(11)->startOfMonth()->toDateString(), 'total' => 750, 'balance' => 750]);

    $response = $this->actAs($this->staff)->getJson('/api/v1/dashboard')->assertOk();

    expect($response->json('data.revenue.labels'))->toHaveCount(12)
        ->and($response->json('data.revenue.invoiced.0'))->toBe('750.00')
        ->and($response->json('data.revenue.invoiced.11'))->toBe('0.00');
});

it('counts projects by status for the donut, including empty statuses', function () {
    Project::factory()->count(2)->create(['status' => 'active']);

    $statuses = collect($this->actAs($this->staff)->getJson('/api/v1/dashboard')->json('data.projects_by_status'))->keyBy('status');

    expect($statuses['active']['count'])->toBe(2)
        ->and($statuses['cancelled']['count'])->toBe(0)
        ->and($statuses->count())->toBe(5);
});

it('lists project and milestone deadlines in the next 7 and 14 days', function () {
    $soon = Project::factory()->create(['status' => 'active', 'end_date' => now()->addDays(3), 'start_date' => now()->subDays(30)]);
    Project::factory()->create(['status' => 'active', 'end_date' => now()->addDays(10), 'start_date' => now()->subDays(30)]);
    Project::factory()->create(['status' => 'active', 'end_date' => now()->addDays(40), 'start_date' => now()->subDays(30)]);
    ProjectMilestone::factory()->create(['project_id' => $soon->id, 'title' => 'Sign-off', 'due_date' => now()->addDays(5), 'is_completed' => false]);
    ProjectMilestone::factory()->create(['project_id' => $soon->id, 'title' => 'Done already', 'due_date' => now()->addDays(2), 'is_completed' => true]);

    $deadlines = $this->actAs($this->staff)->getJson('/api/v1/dashboard')->json('data.deadlines');

    expect(collect($deadlines['within_7_days'])->pluck('title')->all())
        ->toContain($soon->name)
        ->toContain('Sign-off')
        ->not->toContain('Done already');
    expect(count($deadlines['within_14_days']))->toBe(3);
});

it('returns the last five invoices and the last five activity entries', function () {
    for ($i = 0; $i < 6; $i++) {
        invoiceRow($this->client);
    }

    $data = $this->actAs($this->staff)->getJson('/api/v1/dashboard')->json('data');

    expect($data['recent_invoices'])->toHaveCount(5)
        ->and($data['recent_activity'])->toBeArray()
        ->and(count($data['recent_activity']))->toBeLessThanOrEqual(5);
});

it('caches the dashboard for 60 seconds', function () {
    $first = $this->actAs($this->staff)->getJson('/api/v1/dashboard')->json('data.cards.active_projects');

    Project::factory()->create(['status' => 'active']);

    $second = $this->actAs($this->staff)->getJson('/api/v1/dashboard')->json('data.cards.active_projects');
    expect($second)->toBe($first);

    Cache::forget(DashboardService::CACHE_KEY);
    $third = $this->actAs($this->staff)->getJson('/api/v1/dashboard')->json('data.cards.active_projects');
    expect($third)->toBe($first + 1);
});

it('does not show the dashboard to guests', function () {
    $this->getJson('/api/v1/dashboard')->assertStatus(401);
});

it('uses a status enum value for invoiced figures', function () {
    expect(InvoiceStatus::Sent->value)->toBe('sent');
});
