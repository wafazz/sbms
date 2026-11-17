<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InvoiceActionController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices) {}

    public function send(Request $request, Invoice $invoice): InvoiceResource
    {
        Gate::authorize('update', $invoice);

        return new InvoiceResource($this->invoices->send($invoice, $request->user())->load(['client', 'project', 'items', 'payments.recordedBy']));
    }

    public function cancel(Request $request, Invoice $invoice): InvoiceResource
    {
        Gate::authorize('update', $invoice);

        return new InvoiceResource($this->invoices->cancel($invoice, $request->user())->load(['client', 'project', 'items', 'payments.recordedBy']));
    }
}
