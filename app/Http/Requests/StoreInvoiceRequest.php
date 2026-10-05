<?php

namespace App\Http\Requests;

use App\Actions\Invoices\SaveInvoice;
use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    use Concerns\ValidatesSalesLines;

    public function authorize(): bool
    {
        return $this->user()->can('create invoices');
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            ...$this->salesLineRules($tenantId),
            // Shared with the API (Q5). A new invoice starts as draft, sent or
            // unpaid; payments and due dates decide the rest (I3).
            'status' => ['sometimes', Rule::in(SaveInvoice::START_STATUSES)],
            // Where the goods come from or go to (session 12).
            'warehouse_id' => Warehouse::rule($tenantId),
            'sales_order_id' => ['nullable', Rule::exists('sales_orders', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
