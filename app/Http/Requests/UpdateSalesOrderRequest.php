<?php

namespace App\Http\Requests;

use App\Actions\SalesOrders\SaveSalesOrder;
use Illuminate\Validation\Rule;

/**
 * Changing a sales order, from the web form or the API (finding Q5).
 * Header fields may be left out. Status may only move along
 * SaveSalesOrder::TRANSITIONS (confirm or cancel).
 */
class UpdateSalesOrderRequest extends StoreSalesOrderRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit sales-orders');
    }

    public function rules(): array
    {
        $rules = parent::rules();

        foreach (['customer_id', 'order_date', 'items'] as $field) {
            $rules[$field] = array_merge(['sometimes'], array_values(array_diff((array) $rules[$field], ['required'])));
        }
        $rules['status'] = ['sometimes', Rule::in(array_merge(...array_values(SaveSalesOrder::TRANSITIONS)))];

        return $rules;
    }
}
