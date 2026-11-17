<?php

use App\Models\User;

it('lists only active team members with id and name', function () {
    $staff = User::factory()->create(['name' => 'Active Person']);
    User::factory()->inactive()->create(['name' => 'Left Company', 'email' => 'left@test.com']);

    $this->actAs($staff, 'web')
        ->getJson('/api/v1/team')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Active Person')
        ->assertJsonMissingPath('data.0.email');
});

it('does not expose the team to guests', function () {
    $this->getJson('/api/v1/team')->assertStatus(401);
});
