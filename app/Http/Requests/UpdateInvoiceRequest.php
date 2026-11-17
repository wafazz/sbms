<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends StoreInvoiceRequest
{
    /**
     * Every field is optional on update. Locking rules for sent invoices are in InvoiceService.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['sometimes', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'project_id' => ['sometimes', 'nullable', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
            'issue_date' => ['sometimes', 'date'],
            'due_date' => ['sometimes', 'date'],
            'currency' => ['sometimes', 'string', 'size:3', 'alpha'],
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'discount' => ['sometimes', 'numeric', 'min:0', 'max:999999999999.99'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'terms' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'items' => ['sometimes', 'array', 'min:1', 'max:200'],
            'items.*.description' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.01', 'max:999999.99'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0', 'max:999999999999.99'],
        ];
    }
}
