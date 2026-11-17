<?php

use App\Models\User;

it('logs in with valid credentials and returns the user without the password', function () {
    User::factory()->admin()->create(['email' => 'admin@test.com']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@test.com',
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('data.email', 'admin@test.com')
        ->assertJsonPath('data.role', 'admin')
        ->assertJsonMissingPath('data.password');
});

it('rejects wrong credentials with a 422 and a field error', function () {
    User::factory()->create(['email' => 'staff@test.com']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'staff@test.com',
        'password' => 'wrong-password',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('validates the login payload', function () {
    $this->postJson('/api/v1/auth/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

it('blocks login for a deactivated account', function () {
    User::factory()->inactive()->create([
        'email' => 'gone@test.com',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'gone@test.com',
        'password' => 'password',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    $this->assertGuest('web');
});

it('returns 401 from me when not authenticated', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertStatus(401)
        ->assertJson(['message' => 'Unauthenticated.']);
});

it('returns the current user from me when authenticated', function () {
    $user = User::factory()->create(['email' => 'me@test.com']);

    $this->actAs($user, 'web')
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'me@test.com');
});

it('logs out an authenticated user', function () {
    $user = User::factory()->create();

    $this->actAs($user, 'web')
        ->postJson('/api/v1/auth/logout')
        ->assertOk()
        ->assertJson(['message' => 'Logged out.']);
});

it('has no public registration endpoint', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Intruder',
        'email' => 'intruder@test.com',
        'password' => 'password',
    ])->assertStatus(404);

    expect(User::where('email', 'intruder@test.com')->exists())->toBeFalse();
});

it('rate limits login after five attempts per email and IP', function () {
    User::factory()->create(['email' => 'limit@test.com']);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'limit@test.com',
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    $this->postJson('/api/v1/auth/login', [
        'email' => 'limit@test.com',
        'password' => 'wrong',
    ])->assertStatus(429)
        ->assertJson(['message' => 'Too many attempts. Please try again later.']);
});

it('treats a same-origin request on a non-default port as a stateful SPA request', function () {
    User::factory()->create(['email' => 'port@test.com']);

    // Sanctum compares host AND port. Regression: without localhost:8000 in
    // SANCTUM_STATEFUL_DOMAINS, the session never started and login returned 500.
    $this->withHeader('Origin', 'http://localhost:8000')
        ->postJson('/api/v1/auth/login', [
            'email' => 'port@test.com',
            'password' => 'password',
        ])
        ->assertOk();
});
