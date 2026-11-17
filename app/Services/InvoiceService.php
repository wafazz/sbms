<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Project;
use App\Models\User;
use App\Support\InvoiceCalculator;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated input, including "items"
     */
    public function create(array $data, User $actor, ?Project $project = null): Invoice
    {
        return DB::transaction(function () use ($data, $actor, $project) {
            $items = $data['items'];
            unset($data['items']);

            if ($project) {
                $data['project_id'] = $project->id;
                $data['client_id'] = $project->client_id;
            }

            $year = Carbon::parse($data['issue_date'])->format('Y');
            $number = sprintf('INV-%s-%04d', $year, $this->sequences->next("invoice:{$year}"));

            $settings = CompanySetting::current();

            $invoice = (new Invoice)->forceFill([
                ...$data,
                'invoice_number' => $number,
                'status' => InvoiceStatus::Draft,
                'currency' => $data['currency'] ?? 'MYR',
                'tax_rate' => $data['tax_rate'] ?? $settings->default_tax_rate,
                'discount' => $data['discount'] ?? 0,
                'amount_paid' => 0,
                'balance' => 0,
                'created_by' => $actor->id,
            ]);
            $invoice->save();

            $this->applyItemsAndTotals($invoice, $items);

            if ($invoice->project_id) {
                $this->activity->log($invoice->project, $actor, 'invoice_created', "Invoice {$invoice->invoice_number} created.");
            }

            return $this->fresh($invoice);
        });
    }

    /**
     * Draft invoices can be edited in full. Once sent, only notes and terms can change.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Invoice $invoice, array $data, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $changing = array_diff(array_keys($data), ['notes', 'terms']);

            if ($invoice->status->isLocked() && $changing !== []) {
                throw ValidationException::withMessages([
                    'status' => ['This invoice is locked. Only notes and terms can change. Cancel it or issue a credit instead.'],
                ]);
            }

            $items = $data['items'] ?? null;
            unset($data['items']);

            $invoice->update($data);

            if ($items !== null) {
                $this->applyItemsAndTotals($invoice, $items);
            } elseif (array_intersect(array_keys($data), ['tax_rate', 'discount']) !== []) {
                $this->applyItemsAndTotals($invoice, $invoice->items->map(fn ($i) => [
                    'description' => $i->description,
                    'quantity' => $i->quantity,
                    'unit_price' => $i->unit_price,
                ])->all());
            }

            return $this->fresh($invoice);
        });
    }

    public function send(Invoice $invoice, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor) {
            if ($invoice->status !== InvoiceStatus::Draft) {
                throw ValidationException::withMessages(['status' => ['Only draft invoices can be sent.']]);
            }

            if ($invoice->items()->count() === 0) {
                throw ValidationException::withMessages(['items' => ['Add at least one line item before sending.']]);
            }

            $invoice->forceFill(['status' => InvoiceStatus::Sent])->save();

            if ($invoice->project_id) {
                $this->activity->log($invoice->project, $actor, 'invoice_sent', "Invoice {$invoice->invoice_number} sent.");
            }

            return $this->fresh($invoice);
        });
    }

    public function cancel(Invoice $invoice, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor) {
            if (in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Cancelled], true)) {
                throw ValidationException::withMessages(['status' => ['This invoice cannot be cancelled.']]);
            }

            if ((float) $invoice->amount_paid > 0) {
                throw ValidationException::withMessages([
                    'status' => ['Payments are recorded on this invoice. Issue a credit instead of cancelling.'],
                ]);
            }

            $invoice->forceFill(['status' => InvoiceStatus::Cancelled])->save();

            if ($invoice->project_id) {
                $this->activity->log($invoice->project, $actor, 'invoice_cancelled', "Invoice {$invoice->invoice_number} cancelled.");
            }

            return $this->fresh($invoice);
        });
    }

    /** Only drafts can be deleted. Everything else is cancelled. */
    public function delete(Invoice $invoice): void
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => ['Only draft invoices can be deleted. Cancel the invoice instead.'],
            ]);
        }

        $invoice->delete();
    }

    /**
     * Daily job: unpaid invoices past their due date become overdue.
     */
    public function markOverdue(?CarbonInterface $today = null): int
    {
        $today ??= now()->startOfDay();

        return Invoice::query()
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::PartiallyPaid->value])
            ->where('balance', '>', 0)
            ->whereDate('due_date', '<', $today->toDateString())
            ->update(['status' => InvoiceStatus::Overdue->value, 'updated_at' => now()]);
    }

    /**
     * Replace the line items and recalculate every total from the server rules.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function applyItemsAndTotals(Invoice $invoice, array $items): void
    {
        $discount = $invoice->discount ?? 0;
        $calc = InvoiceCalculator::calculate($items, $discount, $invoice->tax_rate);

        if (InvoiceCalculator::toCents($discount) > InvoiceCalculator::toCents($calc['subtotal'])) {
            throw ValidationException::withMessages(['discount' => ['The discount cannot be more than the subtotal.']]);
        }

        $invoice->items()->delete();

        foreach ($items as $index => $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'line_total' => $calc['lines'][$index],
                'sort_order' => $index,
            ]);
        }

        $paid = (string) $invoice->amount_paid;
        $balance = InvoiceCalculator::format(InvoiceCalculator::toCents($calc['total']) - InvoiceCalculator::toCents($paid));

        $invoice->forceFill([
            'subtotal' => $calc['subtotal'],
            'discount' => $calc['discount'],
            'tax_rate' => $calc['tax_rate'],
            'tax_amount' => $calc['tax_amount'],
            'total' => $calc['total'],
            'balance' => $balance,
        ])->save();
    }

    private function fresh(Invoice $invoice): Invoice
    {
        return $invoice->refresh()->load(['client', 'project', 'items']);
    }
}
