<?php

use App\Models\Client;
use App\Models\Project;
use App\Models\User;

it('lets staff create a client', function () {
    $staff = User::factory()->create();

    $this->actAs($staff, 'web')
        ->postJson('/api/v1/clients', [
            'name' => 'Acme Sdn Bhd',
            'email' => 'hello@acme.test',
            'company' => 'Acme',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Acme Sdn Bhd');

    expect(Client::where('name', 'Acme Sdn Bhd')->exists())->toBeTrue();
});

it('validates client input', function () {
    $staff = User::factory()->create();

    $this->actAs($staff, 'web')
        ->postJson('/api/v1/clients', ['email' => 'not-an-email'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email']);
});

it('searches clients by name or company', function () {
    $staff = User::factory()->create();
    Client::factory()->create(['name' => 'Siti Rahman', 'company' => 'Rahman Trading']);
    Client::factory()->create(['name' => 'John Lee', 'company' => 'Lee Design']);

    $this->actAs($staff, 'web')
        ->getJson('/api/v1/clients?q=Rahman')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Siti Rahman');
});

it('forbids staff from deleting a client', function () {
    $staff = User::factory()->create();
    $client = Client::factory()->create();

    $this->actAs($staff, 'web')
        ->deleteJson("/api/v1/clients/{$client->id}")
        ->assertStatus(403);

    expect($client->fresh())->not->toBeNull();
});

it('lets an admin soft delete a client with no projects', function () {
    $admin = User::factory()->admin()->create();
    $client = Client::factory()->create();

    $this->actAs($admin, 'web')
        ->deleteJson("/api/v1/clients/{$client->id}")
        ->assertOk();

    $this->assertSoftDeleted('clients', ['id' => $client->id]);
});

it('blocks deleting a client that still has projects', function () {
    $admin = User::factory()->admin()->create();
    $client = Client::factory()->create();
    Project::factory()->create(['client_id' => $client->id]);

    $this->actAs($admin, 'web')
        ->deleteJson("/api/v1/clients/{$client->id}")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['client']);

    expect($client->fresh())->not->toBeNull();
});

it('rejects guests', function () {
    $this->getJson('/api/v1/clients')->assertStatus(401);
});
