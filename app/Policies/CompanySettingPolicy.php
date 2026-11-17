<?php

namespace App\Policies;

use App\Models\CompanySetting;
use App\Models\User;

class CompanySettingPolicy
{
    /** Everyone signed in can read the company details (used on invoices). */
    public function view(User $actor): bool
    {
        return $actor->is_active;
    }

    /** Only admins change company details, bank info and the logo. */
    public function update(User $actor, CompanySetting $settings): bool
    {
        return $actor->isAdmin();
    }
}
