<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Project;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProjectInvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices) {}

    /** Create an invoice from a project. The client and project are filled in for you. */
    public function store(StoreInvoiceRequest $request, Project $project): JsonResponse
    {
        Gate::authorize('create', Invoice::class);

        $invoice = $this->invoices->create($request->validated(), $request->user(), $project);

        return (new InvoiceResource($invoice))->response()->setStatusCode(201);
    }
}
