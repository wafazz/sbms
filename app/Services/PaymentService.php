<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\InvoiceCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * Record a payment and move the invoice to partially_paid or paid.
     * The invoice row is locked, so two payments cannot overpay it together.
     *
     * @param  array<string, mixed>  $data
     */
    public function record(Invoice $invoice, array $data, User $actor): Payment
    {
        return DB::transaction(function () use ($invoice, $data, $actor) {
            /** @var Invoice $locked */
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->acceptsPayments()) {
                throw ValidationException::withMessages([
                    'status' => ['Payments can only be recorded on sent, partially paid or overdue invoices.'],
                ]);
            }

            $amountCents = InvoiceCalculator::toCents($data['amount']);
            $balanceCents = $this->balanceCents($locked);

            if ($amountCents <= 0) {
                throw ValidationException::withMessages(['amount' => ['The amount must be greater than zero.']]);
            }

            if ($amountCents > $balanceCents) {
                throw ValidationException::withMessages([
                    'amount' => ['The amount is more than the outstanding balance of '.InvoiceCalculator::format($balanceCents).'.'],
                ]);
            }

            $payment = $locked->payments()->create([
                ...$data,
                'recorded_by' => $actor->id,
            ]);

            $this->recalculate($locked);

            if ($locked->project_id) {
                $locked->load('project');
                $this->activity->log(
                    $locked->project,
                    $actor,
                    $locked->status === InvoiceStatus::Paid ? 'invoice_paid' : 'payment_recorded',
                    "Payment of {$locked->currency} ".InvoiceCalculator::format($amountCents)." recorded on {$locked->invoice_number}.",
                );
            }

            return $payment;
        });
    }

    /**
     * Recompute amount_paid, balance and status from the payments on record.
     */
    public function recalculate(Invoice $invoice): void
    {
        $paidCents = InvoiceCalculator::toCents((string) $invoice->payments()->sum('amount'));
        $totalCents = InvoiceCalculator::toCents((string) $invoice->total);
        $balanceCents = max(0, $totalCents - $paidCents);

        $status = match (true) {
            $balanceCents === 0 => InvoiceStatus::Paid,
            $paidCents > 0 => InvoiceStatus::PartiallyPaid,
            default => $invoice->status === InvoiceStatus::Overdue ? InvoiceStatus::Overdue : InvoiceStatus::Sent,
        };

        $invoice->forceFill([
            'amount_paid' => InvoiceCalculator::format($paidCents),
            'balance' => InvoiceCalculator::format($balanceCents),
            'status' => $status,
        ])->save();
    }

    private function balanceCents(Invoice $invoice): int
    {
        $paid = InvoiceCalculator::toCents((string) $invoice->payments()->sum('amount'));

        return InvoiceCalculator::toCents((string) $invoice->total) - $paid;
    }
}
