<?php

use App\Enums\UserRole;
use App\Models\User;

it('reports admin status from the role', function () {
    expect(User::factory()->admin()->make()->isAdmin())->toBeTrue()
        ->and(User::factory()->make()->isAdmin())->toBeFalse();
});

it('has human-readable role labels', function () {
    expect(UserRole::Admin->label())->toBe('Admin')
        ->and(UserRole::Staff->label())->toBe('Staff');
});
