<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /** Admins and staff can view invoices. */
    public function viewAny(User $actor): bool
    {
        return $actor->is_active;
    }

    public function view(User $actor, Invoice $invoice): bool
    {
        return $actor->is_active;
    }

    /** Admins and staff can create, edit, send, cancel and record payments. */
    public function create(User $actor): bool
    {
        return $actor->is_active;
    }

    public function update(User $actor, Invoice $invoice): bool
    {
        return $actor->is_active;
    }

    /** Deleting is admin-only, and only for drafts (checked in the service). */
    public function delete(User $actor, Invoice $invoice): bool
    {
        return $actor->isAdmin();
    }
}
