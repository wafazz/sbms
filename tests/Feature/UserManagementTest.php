<?php

use App\Enums\UserRole;
use App\Models\User;

it('lets an admin list users with pagination', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(3)->create();

    $this->actAs($admin, 'web')
        ->getJson('/api/v1/users')
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonStructure(['data', 'links', 'meta']);
});

it('forbids staff from listing users', function () {
    $staff = User::factory()->create();

    $this->actAs($staff, 'web')
        ->getJson('/api/v1/users')
        ->assertStatus(403);
});

it('returns 401 for guests trying to list users', function () {
    $this->getJson('/api/v1/users')->assertStatus(401);
});

it('forbids staff from creating users and writes nothing', function () {
    $staff = User::factory()->create();

    $this->actAs($staff, 'web')
        ->postJson('/api/v1/users', [
            'name' => 'New Person',
            'email' => 'new@test.com',
            'password' => 'secret123',
            'role' => 'staff',
        ])
        ->assertStatus(403);

    expect(User::where('email', 'new@test.com')->exists())->toBeFalse();
});

it('lets an admin create a user with a hashed password', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actAs($admin, 'web')
        ->postJson('/api/v1/users', [
            'name' => 'New Staff',
            'email' => 'newstaff@test.com',
            'password' => 'secret123',
            'role' => 'staff',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.role', 'staff')
        ->assertJsonMissingPath('data.password');

    $created = User::where('email', 'newstaff@test.com')->firstOrFail();

    expect($created->password)->not->toBe('secret123')
        ->and(password_verify('secret123', $created->password))->toBeTrue()
        ->and($created->role)->toBe(UserRole::Staff);
});

it('rejects a duplicate email and a short password with field errors', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['email' => 'taken@test.com']);

    $this->actAs($admin, 'web')
        ->postJson('/api/v1/users', [
            'name' => 'Dup',
            'email' => 'taken@test.com',
            'password' => 'short',
            'role' => 'staff',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

it('lets an admin deactivate another user, who can then no longer log in', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create(['email' => 'target@test.com']);

    $this->actAs($admin, 'web')
        ->putJson("/api/v1/users/{$target->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'target@test.com',
        'password' => 'password',
    ])->assertStatus(422);
});

it('lets an admin change another user role', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    $this->actAs($admin, 'web')
        ->putJson("/api/v1/users/{$target->id}", ['role' => 'admin'])
        ->assertOk()
        ->assertJsonPath('data.role', 'admin');
});

it('stops an admin from demoting or deactivating themselves', function () {
    $admin = User::factory()->admin()->create();

    $this->actAs($admin, 'web')
        ->putJson("/api/v1/users/{$admin->id}", ['role' => 'staff'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['role']);

    $this->actAs($admin, 'web')
        ->putJson("/api/v1/users/{$admin->id}", ['is_active' => false])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['role']);

    expect($admin->refresh()->role)->toBe(UserRole::Admin)
        ->and($admin->is_active)->toBeTrue();
});

it('forbids staff from updating users', function () {
    $staff = User::factory()->create();
    $target = User::factory()->create();

    $this->actAs($staff, 'web')
        ->putJson("/api/v1/users/{$target->id}", ['role' => 'admin'])
        ->assertStatus(403);

    expect($target->refresh()->role)->toBe(UserRole::Staff);
});
