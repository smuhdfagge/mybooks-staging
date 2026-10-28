<?php

namespace App\Http\Requests;

use App\Actions\Bills\SaveBill;
use App\Models\BillItem;
use App\Models\Warehouse;
use App\Services\Accounting\VatTreatment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create bills');
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'vendor_id' => ['required', Rule::exists('vendors', 'id')->where('tenant_id', $tenantId)],
            'purchase_order_id' => ['nullable', Rule::exists('purchase_orders', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'bill_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:bill_date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.vat_treatment' => ['nullable', 'in:'.implode(',', VatTreatment::ALL)],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            // Expense or asset accounts only; empty posts to Miscellaneous Expense (MB-14).
            'items.*.account_id' => ['nullable', Rule::in(BillItem::postableAccounts($tenantId)->pluck('id')->all())],
            // Shared with the API (Q5).
            'vendor_bill_number' => ['nullable', 'string', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            // Where the goods come from or go to (session 12).
            'warehouse_id' => Warehouse::rule($tenantId),
            'status' => ['sometimes', Rule::in(SaveBill::START_STATUSES)],
        ];
    }
}
