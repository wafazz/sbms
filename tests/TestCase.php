<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Auth;

abstract class TestCase extends BaseTestCase
{
    /**
     * Feature tests act like the Vue SPA: same-origin requests that carry a
     * session. Sanctum only starts the session for stateful requests, and it
     * detects them from the Origin header.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Origin', 'http://localhost');
    }

    /**
     * Act as a user for the next requests. Clears cached guards first, so a second
     * actingAs() in the same test really switches the user.
     */
    protected function actAs(User $user): static
    {
        Auth::forgetGuards();

        return $this->actingAs($user, 'web');
    }
}
