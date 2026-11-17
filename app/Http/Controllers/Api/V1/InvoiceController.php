<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Support\InvoiceCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    private const SORTABLE = ['invoice_number', 'issue_date', 'due_date', 'total', 'balance'];

    public function __construct(private readonly InvoiceService $invoices) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Invoice::class);

        $request->validate([
            'status' => ['nullable', Rule::enum(InvoiceStatus::class)],
            'client_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:50'],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $base = Invoice::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->integer('project_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('issue_date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('issue_date', '<=', $request->query('to')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $like = '%'.addcslashes(trim((string) $request->query('q')), '%_\\').'%';
                $q->where('invoice_number', 'like', $like);
            });

        // Totals for the whole filtered set, not just this page.
        $sums = (clone $base)->reorder()->toBase()->selectRaw(
            'COUNT(*) as count, COALESCE(SUM(total),0) as total, COALESCE(SUM(amount_paid),0) as paid, COALESCE(SUM(balance),0) as balance'
        )->first();

        $sort = $request->query('sort', 'issue_date');
        $dir = $request->query('dir', 'desc');

        $invoices = (clone $base)
            ->with(['client:id,name,company', 'project:id,code,name'])
            ->orderBy($sort, $dir)
            ->orderBy('id', 'desc')
            ->paginate(min(max((int) $request->query('per_page', 15), 1), 100));

        return InvoiceResource::collection($invoices)->additional([
            'totals' => [
                'count' => (int) $sums->count,
                'total' => InvoiceCalculator::format((int) round((float) $sums->total * 100)),
                'amount_paid' => InvoiceCalculator::format((int) round((float) $sums->paid * 100)),
                'balance' => InvoiceCalculator::format((int) round((float) $sums->balance * 100)),
            ],
        ]);
    }

    /**
     * Invoice count per status, for the sidebar submenu badges. Every status is returned, even at zero.
     */
    public function statusCounts(): JsonResponse
    {
        Gate::authorize('viewAny', Invoice::class);

        $counts = Invoice::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statuses = collect(InvoiceStatus::cases())->map(fn (InvoiceStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
            'count' => (int) ($counts[$status->value] ?? 0),
        ])->values();

        return response()->json([
            'data' => $statuses,
            'total' => (int) $counts->sum(),
        ]);
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        Gate::authorize('create', Invoice::class);

        $invoice = $this->invoices->create($request->validated(), $request->user());

        return (new InvoiceResource($invoice))->response()->setStatusCode(201);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        Gate::authorize('view', $invoice);

        return new InvoiceResource($invoice->load(['client', 'project', 'items', 'payments.recordedBy']));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): InvoiceResource
    {
        Gate::authorize('update', $invoice);

        $invoice = $this->invoices->update($invoice, $request->validated(), $request->user());

        return new InvoiceResource($invoice->load(['client', 'project', 'items', 'payments.recordedBy']));
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        Gate::authorize('delete', $invoice);

        $this->invoices->delete($invoice);

        return response()->json(['message' => 'Invoice deleted.']);
    }
}
