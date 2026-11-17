<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;

it('returns a count for every invoice status, including zero', function () {
    $staff = User::factory()->create();
    $client = Client::factory()->create();
    Invoice::factory()->count(2)->create(['client_id' => $client->id, 'status' => 'sent']);
    Invoice::factory()->create(['client_id' => $client->id, 'status' => 'overdue']);

    $response = $this->actAs($staff)
        ->getJson('/api/v1/invoices/status-counts')
        ->assertOk()
        ->assertJsonCount(6, 'data')
        ->assertJsonPath('total', 3);

    $byValue = collect($response->json('data'))->keyBy('value');

    expect($byValue['sent']['count'])->toBe(2)
        ->and($byValue['overdue']['count'])->toBe(1)
        ->and($byValue['paid']['count'])->toBe(0)
        ->and($byValue['partially_paid']['label'])->toBe('Partially paid');
});

it('leaves soft-deleted invoices out of the counts', function () {
    $staff = User::factory()->create();
    $client = Client::factory()->create();
    Invoice::factory()->create(['client_id' => $client->id, 'status' => 'draft'])->delete();

    $this->actAs($staff)
        ->getJson('/api/v1/invoices/status-counts')
        ->assertOk()
        ->assertJsonPath('total', 0);
});

it('is not mistaken for an invoice id', function () {
    $staff = User::factory()->create();

    $this->actAs($staff)
        ->getJson('/api/v1/invoices/status-counts')
        ->assertOk()
        ->assertJsonMissingPath('invoice_number');
});

it('rejects guests', function () {
    $this->getJson('/api/v1/invoices/status-counts')->assertStatus(401);
});
