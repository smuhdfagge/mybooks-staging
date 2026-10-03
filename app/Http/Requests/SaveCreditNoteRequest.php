<?php

namespace App\Http\Requests;

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating or changing a draft customer credit note. Line rules are the
 * invoice's (ValidatesSalesLines), VAT treatment included.
 */
class SaveCreditNoteRequest extends FormRequest
{
    use Concerns\ValidatesSalesLines;

    public function authorize(): bool
    {
        return $this->user()->can($this->isMethod('post') ? 'create invoices' : 'edit invoices');
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'invoice_id' => ['nullable', Rule::exists('invoices', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'credit_note_date' => ['required', 'date'],
            'reason' => ['nullable', Rule::in(array_keys(CreditNote::REASONS))],
            'restock' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Create only: save as a draft or open (post) straight away.
            'status' => ['nullable', Rule::in(CreditNoteStatus::startValues())],
            ...$this->salesLineRules($tenantId),
        ];
    }
}
