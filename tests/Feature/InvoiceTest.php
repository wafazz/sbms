<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;

function invoicePayload(Client $client, array $overrides = []): array
{
    return array_merge([
        'client_id' => $client->id,
        'issue_date' => '2026-11-01',
        'due_date' => '2026-12-01',
        'tax_rate' => 6,
        'discount' => 0,
        'items' => [
            ['description' => 'Design', 'quantity' => 2, 'unit_price' => 100.50],
            ['description' => 'Hosting', 'quantity' => 1, 'unit_price' => 50],
        ],
    ], $overrides);
}

beforeEach(function () {
    $this->staff = User::factory()->create();
    $this->client = Client::factory()->create();
});

it('numbers invoices sequentially per year', function () {
    $first = $this->actAs($this->staff, 'web')->postJson('/api/v1/invoices', invoicePayload($this->client))->assertCreated();
    $second = $this->actAs($this->staff, 'web')->postJson('/api/v1/invoices', invoicePayload($this->client))->assertCreated();

    expect($first->json('data.invoice_number'))->toBe('INV-2026-0001')
        ->and($second->json('data.invoice_number'))->toBe('INV-2026-0002');
});

it('restarts the number sequence for a new year', function () {
    $this->actAs($this->staff, 'web')->postJson('/api/v1/invoices', invoicePayload($this->client));

    $next = $this->actAs($this->staff, 'web')
        ->postJson('/api/v1/invoices', invoicePayload($this->client, ['issue_date' => '2027-01-05', 'due_date' => '2027-02-05']))
        ->assertCreated();

    expect($next->json('data.invoice_number'))->toBe('INV-2027-0001');
});

it('calculates totals on the server and ignores totals sent by the client', function () {
    $response = $this->actAs($this->staff, 'web')
        ->postJson('/api/v1/invoices', invoicePayload($this->client, [
            'total' => 1,
            'subtotal' => 1,
            'balance' => 1,
        ]))
        ->assertCreated();

    // lines: 2 x 100.50 = 201.00, 1 x 50 = 50.00 → subtotal 251.00, tax 6% = 15.06, total 266.06
    expect($response->json('data.subtotal'))->toBe('251.00')
        ->and($response->json('data.tax_amount'))->toBe('15.06')
        ->and($response->json('data.total'))->toBe('266.06')
        ->and($response->json('data.balance'))->toBe('266.06')
        ->and($response->json('data.items.0.line_total'))->toBe('201.00');
});

it('recalculates totals when a draft is edited', function () {
    $invoice = Invoice::factory()->create(['client_id' => $this->client->id]);
    $created = $this->actAs($this->staff, 'web')->postJson('/api/v1/invoices', invoicePayload($this->client))->json('data.id');

    $this->actAs($this->staff, 'web')
        ->putJson("/api/v1/invoices/{$created}", ['items' => [['description' => 'Only', 'quantity' => 1, 'unit_price' => 100]]])
        ->assertOk()
        ->assertJsonPath('data.subtotal', '100.00')
        ->assertJsonPath('data.total', '106.00');

    expect($invoice->exists)->toBeTrue();
});

it('rejects a discount larger than the subtotal', function () {
    $this->actAs($this->staff, 'web')
        ->postJson('/api/v1/invoices', invoicePayload($this->client, ['discount' => 999]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['discount']);
});

it('requires at least one line item and a due date on or after the issue date', function () {
    $this->actAs($this->staff, 'web')
        ->postJson('/api/v1/invoices', invoicePayload($this->client, ['items' => [], 'due_date' => '2026-10-01']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items', 'due_date']);
});

it('locks a sent invoice against item edits but allows notes', function () {
    $id = $this->actAs($this->staff, 'web')->postJson('/api/v1/invoices', invoicePayload($this->client))->json('data.id');
    $this->actAs($this->staff, 'web')->postJson("/api/v1/invoices/{$id}/send")->assertOk()->assertJsonPath('data.status.value', 'sent');

    $this->actAs($this->staff, 'web')
        ->putJson("/api/v1/invoices/{$id}", ['items' => [['description' => 'Changed', 'quantity' => 1, 'unit_price' => 1]]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    $this->actAs($this->staff, 'web')
        ->putJson("/api/v1/invoices/{$id}", ['notes' => 'Thank you for your business.'])
        ->assertOk()
        ->assertJsonPath('data.notes', 'Thank you for your business.')
        ->assertJsonPath('data.is_locked', true);
});

it('cannot send an invoice twice or send one with no items', function () {
    $id = $this->actAs($this->staff, 'web')->postJson('/api/v1/invoices', invoicePayload($this->client))->json('data.id');
    $this->actAs($this->staff, 'web')->postJson("/api/v1/invoices/{$id}/send")->assertOk();
    $this->actAs($this->staff, 'web')->postJson("/api/v1/invoices/{$id}/send")->assertStatus(422);
});

it('lets staff cancel an invoice but not delete a sent one, and only admins delete drafts', function () {
    $draft = $this->actAs($this->staff, 'web')->postJson('/api/v1/invoices', invoicePayload($this->client))->json('data.id');

    $this->actAs($this->staff, 'web')->deleteJson("/api/v1/invoices/{$draft}")->assertStatus(403);

    $admin = User::factory()->admin()->create();
    $this->actAs($admin, 'web')->deleteJson("/api/v1/invoices/{$draft}")->assertOk();
    $this->assertSoftDeleted('invoices', ['id' => $draft]);

    $sent = $this->actAs($this->staff, 'web')->postJson('/api/v1/invoices', invoicePayload($this->client))->json('data.id');
    $this->actAs($this->staff, 'web')->postJson("/api/v1/invoices/{$sent}/send")->assertOk();
    $this->actAs($admin, 'web')->deleteJson("/api/v1/invoices/{$sent}")->assertStatus(422);

    $this->actAs($this->staff, 'web')->postJson("/api/v1/invoices/{$sent}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status.value', 'cancelled');
});

it('creates an invoice from a project with the client and project filled in', function () {
    $project = Project::factory()->create(['client_id' => $this->client->id]);

    $this->actAs($this->staff, 'web')
        ->postJson("/api/v1/projects/{$project->id}/invoices", invoicePayload($this->client, ['client_id' => null]))
        ->assertCreated()
        ->assertJsonPath('data.project.id', $project->id)
        ->assertJsonPath('data.client.id', $this->client->id);
});

it('filters the invoice list by status and returns totals for the filtered set', function () {
    $this->actAs($this->staff, 'web')->postJson('/api/v1/invoices', invoicePayload($this->client));
    $id = $this->actAs($this->staff, 'web')->postJson('/api/v1/invoices', invoicePayload($this->client))->json('data.id');
    $this->actAs($this->staff, 'web')->postJson("/api/v1/invoices/{$id}/send");

    $this->actAs($this->staff, 'web')
        ->getJson('/api/v1/invoices?status=sent')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('totals.count', 1)
        ->assertJsonPath('totals.total', '266.06')
        ->assertJsonPath('totals.balance', '266.06');
});

it('searches invoices by number', function () {
    $this->actAs($this->staff, 'web')->postJson('/api/v1/invoices', invoicePayload($this->client));

    $this->actAs($this->staff, 'web')
        ->getJson('/api/v1/invoices?q=0001')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('blocks deleting a client that has invoices', function () {
    $admin = User::factory()->admin()->create();
    $this->actAs($this->staff, 'web')->postJson('/api/v1/invoices', invoicePayload($this->client));

    $this->actAs($admin, 'web')->deleteJson("/api/v1/clients/{$this->client->id}")->assertStatus(422);
});

it('serves the invoice as a PDF', function () {
    $id = $this->actAs($this->staff, 'web')->postJson('/api/v1/invoices', invoicePayload($this->client))->json('data.id');

    $response = $this->actAs($this->staff, 'web')->get("/api/v1/invoices/{$id}/pdf")->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});
