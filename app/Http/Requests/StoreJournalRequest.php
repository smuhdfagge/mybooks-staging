<?php

namespace App\Http\Requests;

use App\Http\Middleware\EnsureFeatureEnabled;
use App\Models\Journal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
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

        $rules = [
            'journal_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:500'],
            'entries' => ['required', 'array', 'min:2'],
            'entries.*.account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'entries.*.description' => ['nullable', 'string', 'max:255'],
            'entries.*.debit' => ['nullable', 'numeric', 'min:0'],
            'entries.*.credit' => ['nullable', 'numeric', 'min:0'],
        ];

        // Accruals (S8): post the mirror-image journal on this date. Left out
        // of the rules (so ignored) when the feature is switched off.
        if (EnsureFeatureEnabled::enabled('auto_reversing_journals')) {
            $rules['reverse_on'] = ['nullable', 'date'];
        }

        return $rules;
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

        $validator->after(fn (Validator $validator) => $this->checkReverseOn($validator));
    }

    /**
     * The reverse-on date must be after the journal date (S8). On an update
     * either may be left out, so the journal's stored value counts then.
     */
    private function checkReverseOn(Validator $validator): void
    {
        if (! EnsureFeatureEnabled::enabled('auto_reversing_journals') || $validator->errors()->hasAny(['journal_date', 'reverse_on'])) {
            return;
        }

        $journal = $this->route('journal');
        $journal = $journal instanceof Journal ? $journal : null;
        $reverseOn = $this->has('reverse_on') ? $this->input('reverse_on') : $journal?->reverse_on;
        $journalDate = $this->input('journal_date') ?? $journal?->journal_date;
        if (! $reverseOn || ! $journalDate) {
            return;
        }

        if (Carbon::parse($reverseOn)->startOfDay()->lte(Carbon::parse($journalDate)->startOfDay())) {
            $validator->errors()->add('reverse_on', 'The reverse date must be after the journal date (usually the first day of the next month).');
        }
    }
}
