<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A stock transfer from the web form or the API (session 13). */
class SaveStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('adjust inventory');
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'transfer_date' => ['required', 'date'],
            'from_warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'to_warehouse_id' => ['required', 'integer', 'different:from_warehouse_id', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['nullable', 'integer', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            // What to do after saving: keep as a draft, ship only, or ship and receive now.
            'action' => ['nullable', Rule::in(['draft', 'ship', 'transfer_now'])],
        ];
    }

    public function messages(): array
    {
        return [
            'to_warehouse_id.different' => 'Choose a different warehouse to send the goods to.',
            'from_warehouse_id.exists' => 'Choose one of your own warehouses to send from.',
            'to_warehouse_id.exists' => 'Choose one of your own warehouses to send to.',
            'items.*.item_id.exists' => 'Choose one of your own items.',
        ];
    }
}
