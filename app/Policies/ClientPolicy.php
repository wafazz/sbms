<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    /** Any active signed-in user can view clients. */
    public function viewAny(User $actor): bool
    {
        return $actor->is_active;
    }

    public function view(User $actor, Client $client): bool
    {
        return $actor->is_active;
    }

    /** Admins and staff can create and edit clients. */
    public function create(User $actor): bool
    {
        return $actor->is_active;
    }

    public function update(User $actor, Client $client): bool
    {
        return $actor->is_active;
    }

    /** Deleting is admin-only. */
    public function delete(User $actor, Client $client): bool
    {
        return $actor->isAdmin();
    }
}
