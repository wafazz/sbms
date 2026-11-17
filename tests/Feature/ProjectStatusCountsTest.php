<?php

use App\Models\Project;
use App\Models\User;

it('returns a count for every project status, including zero', function () {
    $staff = User::factory()->create();
    Project::factory()->count(2)->create(['status' => 'active']);
    Project::factory()->create(['status' => 'on_hold']);

    $response = $this->actAs($staff)
        ->getJson('/api/v1/projects/status-counts')
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('total', 3);

    $byValue = collect($response->json('data'))->keyBy('value');

    expect($byValue['active']['count'])->toBe(2)
        ->and($byValue['on_hold']['count'])->toBe(1)
        ->and($byValue['cancelled']['count'])->toBe(0)
        ->and($byValue['planning']['label'])->toBe('Planning');
});

it('leaves soft-deleted projects out of the counts', function () {
    $staff = User::factory()->create();
    Project::factory()->create(['status' => 'active'])->delete();

    $this->actAs($staff)
        ->getJson('/api/v1/projects/status-counts')
        ->assertOk()
        ->assertJsonPath('total', 0);
});

it('is not mistaken for a project id', function () {
    $staff = User::factory()->create();

    $this->actAs($staff)
        ->getJson('/api/v1/projects/status-counts')
        ->assertOk()
        ->assertJsonMissingPath('code');
});

it('rejects guests', function () {
    $this->getJson('/api/v1/projects/status-counts')->assertStatus(401);
});
