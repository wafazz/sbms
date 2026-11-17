<?php

namespace App\Console\Commands;

use App\Services\InvoiceService;
use Illuminate\Console\Command;

class MarkOverdueInvoices extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Mark unpaid invoices past their due date as overdue';

    public function handle(InvoiceService $invoices): int
    {
        $count = $invoices->markOverdue();

        $this->info("{$count} invoice(s) marked overdue.");

        return self::SUCCESS;
    }
}
