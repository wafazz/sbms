<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecordPaymentRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    /** Records a payment and returns the updated invoice. */
    public function store(RecordPaymentRequest $request, Invoice $invoice): JsonResponse
    {
        Gate::authorize('update', $invoice);

        $this->payments->record($invoice, $request->validated(), $request->user());

        return (new InvoiceResource($invoice->refresh()->load(['client', 'project', 'items', 'payments.recordedBy'])))
            ->response()
            ->setStatusCode(201);
    }
}
