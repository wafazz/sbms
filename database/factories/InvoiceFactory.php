<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Creates a bare invoice row. Use InvoiceService::create() when you need items and totals.
 *
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issue = fake()->dateTimeBetween('-3 months', 'now');

        return [
            'invoice_number' => 'INV-TEST-'.fake()->unique()->numerify('####'),
            'client_id' => Client::factory(),
            'project_id' => null,
            'issue_date' => $issue,
            'due_date' => (clone $issue)->modify('+30 days'),
            'status' => 'draft',
            'currency' => 'MYR',
            'subtotal' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'discount' => 0,
            'total' => 0,
            'amount_paid' => 0,
            'balance' => 0,
        ];
    }
}
