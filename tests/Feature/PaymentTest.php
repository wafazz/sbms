<?php

use App\Models\Activity;
use App\Models\Client;
use App\Models\CompanySetting;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Creates and sends an invoice with a total of 266.06. */
function sentInvoice(User $actor, Client $client, array $overrides = []): string
{
    $id = test()->actAs($actor, 'web')->postJson('/api/v1/invoices', array_merge([
        'client_id' => $client->id,
        'issue_date' => '2026-11-01',
        'due_date' => '2026-12-01',
        'tax_rate' => 6,
        'items' => [
            ['description' => 'Design', 'quantity' => 2, 'unit_price' => 100.50],
            ['description' => 'Hosting', 'quantity' => 1, 'unit_price' => 50],
        ],
    ], $overrides))->json('data.id');

    test()->actAs($actor, 'web')->postJson("/api/v1/invoices/{$id}/send")->assertOk();

    return (string) $id;
}

beforeEach(function () {
    $this->staff = User::factory()->create();
    $this->client = Client::factory()->create();
});

it('moves a sent invoice to partially paid and then paid', function () {
    $id = sentInvoice($this->staff, $this->client);

    $this->actAs($this->staff, 'web')
        ->postJson("/api/v1/invoices/{$id}/payments", [
            'amount' => 100,
            'payment_date' => '2026-11-10',
            'method' => 'bank_transfer',
            'reference' => 'TRX-1',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status.value', 'partially_paid')
        ->assertJsonPath('data.amount_paid', '100.00')
        ->assertJsonPath('data.balance', '166.06');

    $this->actAs($this->staff, 'web')
        ->postJson("/api/v1/invoices/{$id}/payments", [
            'amount' => 166.06,
            'payment_date' => '2026-11-20',
            'method' => 'cash',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status.value', 'paid')
        ->assertJsonPath('data.balance', '0.00');
});

it('rejects a payment larger than the balance', function () {
    $id = sentInvoice($this->staff, $this->client);

    $this->actAs($this->staff, 'web')
        ->postJson("/api/v1/invoices/{$id}/payments", ['amount' => 300, 'payment_date' => '2026-11-10', 'method' => 'cash'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

it('rejects a payment on a draft invoice', function () {
    $draft = test()->actAs($this->staff, 'web')->postJson('/api/v1/invoices', [
        'client_id' => $this->client->id,
        'issue_date' => '2026-11-01',
        'due_date' => '2026-12-01',
        'items' => [['description' => 'X', 'quantity' => 1, 'unit_price' => 10]],
    ])->json('data.id');

    $this->actAs($this->staff, 'web')
        ->postJson("/api/v1/invoices/{$draft}/payments", ['amount' => 5, 'payment_date' => '2026-11-10', 'method' => 'cash'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('rejects a zero or negative payment and an unknown method', function () {
    $id = sentInvoice($this->staff, $this->client);

    $this->actAs($this->staff, 'web')
        ->postJson("/api/v1/invoices/{$id}/payments", ['amount' => 0, 'payment_date' => '2026-11-10', 'method' => 'bitcoin'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount', 'method']);
});

it('logs payments on the project when the invoice belongs to one', function () {
    $project = Project::factory()->create(['client_id' => $this->client->id]);
    $id = test()->actAs($this->staff, 'web')->postJson("/api/v1/projects/{$project->id}/invoices", [
        'issue_date' => '2026-11-01',
        'due_date' => '2026-12-01',
        'items' => [['description' => 'Work', 'quantity' => 1, 'unit_price' => 500]],
    ])->json('data.id');
    $this->actAs($this->staff, 'web')->postJson("/api/v1/invoices/{$id}/send")->assertOk();

    $this->actAs($this->staff, 'web')
        ->postJson("/api/v1/invoices/{$id}/payments", ['amount' => 500, 'payment_date' => '2026-11-10', 'method' => 'card'])
        ->assertCreated();

    expect(Activity::where('project_id', $project->id)->where('action', 'invoice_paid')->exists())->toBeTrue();
});

it('marks unpaid past-due invoices overdue with the daily command and keeps them payable', function () {
    $id = sentInvoice($this->staff, $this->client, ['issue_date' => '2020-01-01', 'due_date' => '2020-01-31']);

    $this->artisan('invoices:mark-overdue')->assertSuccessful();

    $this->actAs($this->staff, 'web')
        ->getJson("/api/v1/invoices/{$id}")
        ->assertJsonPath('data.status.value', 'overdue');

    $this->actAs($this->staff, 'web')
        ->postJson("/api/v1/invoices/{$id}/payments", ['amount' => 266.06, 'payment_date' => '2026-11-10', 'method' => 'bank_transfer'])
        ->assertCreated()
        ->assertJsonPath('data.status.value', 'paid');
});

it('does not mark current or paid invoices overdue', function () {
    sentInvoice($this->staff, $this->client, ['issue_date' => now()->toDateString(), 'due_date' => now()->addDays(10)->toDateString()]);

    $this->artisan('invoices:mark-overdue')->expectsOutput('0 invoice(s) marked overdue.')->assertSuccessful();
});

it('lets staff read company settings but only admins change them', function () {
    $this->actAs($this->staff, 'web')
        ->getJson('/api/v1/settings')
        ->assertOk();

    $this->actAs($this->staff, 'web')
        ->postJson('/api/v1/settings', ['name' => 'Hacked', 'default_tax_rate' => 0])
        ->assertStatus(403);
});

it('lets an admin save company details and a logo used on invoices', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();

    $this->actAs($admin, 'web')
        ->postJson('/api/v1/settings', [
            'name' => 'Daythree Digital Sdn Bhd',
            'address' => '1 Jalan Test, Kuala Lumpur',
            'tax_number' => 'SST-123',
            'default_tax_rate' => 6,
            'bank_name' => 'Maybank',
            'bank_account' => '1234567890',
            'bank_swift' => 'MBBEMYKL',
            'logo' => UploadedFile::fake()->createWithContent('logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==')),
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Daythree Digital Sdn Bhd')
        ->assertJsonPath('data.default_tax_rate', '6.00');

    $settings = CompanySetting::current();
    expect($settings->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($settings->logo_path);
});

it('rejects a logo that is not an image', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();

    $this->actAs($admin, 'web')
        ->postJson('/api/v1/settings', [
            'name' => 'X',
            'default_tax_rate' => 0,
            'logo' => UploadedFile::fake()->create('evil.pdf', 10, 'application/pdf'),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['logo']);
});
