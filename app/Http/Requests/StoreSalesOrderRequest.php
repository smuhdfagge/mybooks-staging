<?php

namespace App\Http\Requests;

use App\Actions\SalesOrders\SaveSalesOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating a sales order, from the web form or the API (finding Q5).
 * Line rules are the invoice's, so tax rates and discounts are checked the
 * same way on both.
 */
class StoreSalesOrderRequest extends FormRequest
{
    use Concerns\ValidatesSalesLines;

    public function authorize(): bool
    {
        return $this->user()->can('create sales-orders');
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            ...$this->salesLineRules($tenantId),
            // A new order is draft or confirmed; invoicing and delivery move it on.
            'status' => ['sometimes', Rule::in(SaveSalesOrder::START_STATUSES)],
        ];
    }
}
