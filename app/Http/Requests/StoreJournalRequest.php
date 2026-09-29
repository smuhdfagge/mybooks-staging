<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A manual journal, from the web form or the API (finding Q5). A
 * description is required (the web left it optional), and each line holds
 * a debit or a credit, not both (the web took both). Blank lines are
 * dropped, as the web did. Totals must balance; the controllers
 * check that and answer in their own way.
 */
class StoreJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create journals');
    }

    /**
     * Blank rows (no debit and no credit) are dropped, as the web form
     * always did, so an empty spare row with an account picked isn't an error.
     */
    protected function prepareForValidation(): void
    {
        $entries = $this->input('entries');
        if (is_array($entries)) {
            $this->merge(['entries' => array_values(array_filter($entries, fn ($e) => ! is_array($e)
                || (float) ($e['debit'] ?? 0) != 0.0 || (float) ($e['credit'] ?? 0) != 0.0))]);
        }
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'journal_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:500'],
            'entries' => ['required', 'array', 'min:2'],
            'entries.*.account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'entries.*.description' => ['nullable', 'string', 'max:255'],
            'entries.*.debit' => ['nullable', 'numeric', 'min:0'],
            'entries.*.credit' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $entries = $this->input('entries');
            if (! is_array($entries) || $validator->errors()->isNotEmpty()) {
                return;
            }

            foreach ($entries as $index => $entry) {
                $debit = (float) ($entry['debit'] ?? 0);
                $credit = (float) ($entry['credit'] ?? 0);

                if ($debit > 0 && $credit > 0) {
                    $validator->errors()->add("entries.{$index}", 'An entry cannot have both debit and credit amounts.');
                } elseif ($debit == 0 && $credit == 0) {
                    $validator->errors()->add("entries.{$index}", 'An entry must have either a debit or credit amount.');
                }
            }
        });
    }
}
