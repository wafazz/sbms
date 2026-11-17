<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->is_active;
    }

    public function view(User $actor, Project $project): bool
    {
        return $actor->is_active;
    }

    /** Admins and staff can create and edit projects, tasks and milestones. */
    public function create(User $actor): bool
    {
        return $actor->is_active;
    }

    public function update(User $actor, Project $project): bool
    {
        return $actor->is_active;
    }

    /** Deleting projects is admin-only. */
    public function delete(User $actor, Project $project): bool
    {
        return $actor->isAdmin();
    }
}
