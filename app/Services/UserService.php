<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        return User::create($data);
    }

    /**
     * Update a user account. Enforces two rules the policy cannot express:
     * an admin cannot demote or deactivate themselves, and the system must
     * always keep at least one active admin.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $target, array $data, User $actor): User
    {
        return DB::transaction(function () use ($target, $data, $actor) {
            $losesAdmin = $this->losesAdminAccess($target, $data);

            if ($losesAdmin && $actor->is($target)) {
                throw ValidationException::withMessages([
                    'role' => ['You cannot remove your own admin access or deactivate your own account.'],
                ]);
            }

            if ($losesAdmin && $this->activeAdminCount() <= 1) {
                throw ValidationException::withMessages([
                    'role' => ['At least one active admin is required.'],
                ]);
            }

            $target->update($data);

            return $target->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function losesAdminAccess(User $target, array $data): bool
    {
        if (! $target->isAdmin() || ! $target->is_active) {
            return false;
        }

        $newRole = isset($data['role'])
            ? UserRole::from($data['role'] instanceof UserRole ? $data['role']->value : $data['role'])
            : $target->role;
        $newActive = $data['is_active'] ?? $target->is_active;

        return $newRole !== UserRole::Admin || ! $newActive;
    }

    private function activeAdminCount(): int
    {
        return User::query()
            ->where('role', UserRole::Admin->value)
            ->where('is_active', true)
            ->lockForUpdate()
            ->count();
    }
}
