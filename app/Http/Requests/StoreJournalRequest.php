<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create journals');
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'journal_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'entries' => ['required', 'array', 'min:2'],
            'entries.*.account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'entries.*.description' => ['nullable', 'string'],
            'entries.*.debit' => ['nullable', 'numeric', 'min:0'],
            'entries.*.credit' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
