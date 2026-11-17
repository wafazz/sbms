<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;

beforeEach(fn () => $this->withoutVite());

it('serves the SPA shell for the root and for client-side routes', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('<div id="app"></div>', false);

    $this->get('/projects/12/timeline')
        ->assertOk()
        ->assertSee('<div id="app"></div>', false);
});

it('returns a JSON 404 for unknown API routes, not the SPA shell', function () {
    $this->getJson('/api/v1/does-not-exist')
        ->assertStatus(404)
        ->assertJson(['message' => 'Resource not found.']);
});

it('seeds one admin account and is safe to run twice', function () {
    $this->seed(AdminUserSeeder::class);
    $this->seed(AdminUserSeeder::class);

    expect(User::where('email', 'admin@example.com')->count())->toBe(1)
        ->and(User::where('email', 'admin@example.com')->first()->isAdmin())->toBeTrue();
});
