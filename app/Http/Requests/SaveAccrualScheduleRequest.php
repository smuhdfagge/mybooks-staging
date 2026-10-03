<?php

namespace App\Http\Requests;

use App\Models\AccrualSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating or changing a prepaid expense / deferred revenue schedule (S9).
 * The account types and the linked document are checked by
 * App\Actions\AccrualSchedules\SaveAccrualSchedule.
 */
class SaveAccrualScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->isMethod('post') ? 'create accrual-schedules' : 'edit accrual-schedules');
    }

    /** The form sends the linked document as "bill:12". */
    protected function prepareForValidation(): void
    {
        $source = (string) $this->input('source', '');
        [$type, $id] = str_contains($source, ':') ? explode(':', $source, 2) : [null, null];
        $this->merge(['source_type' => $type ?: null, 'source_id' => $id ?: null]);
    }

    public function rules(): array
    {
        $account = Rule::exists('chart_of_accounts', 'id')->where('tenant_id', auth()->user()->tenant_id);

        return [
            'type' => ['required', Rule::in(array_keys(AccrualSchedule::TYPES))],
            'description' => ['required', 'string', 'max:255'],
            'total_amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999'],
            'start_month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])(-\d{2})?$/'],
            'months' => ['required', 'integer', 'min:1', 'max:'.AccrualSchedule::MAX_MONTHS],
            'balance_account_id' => ['nullable', $account],
            'pl_account_id' => ['required', $account],
            'source_type' => ['nullable', Rule::in(array_keys(AccrualSchedule::SOURCES))],
            'source_id' => ['nullable', 'integer'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'start_month.regex' => 'Choose the first month the payment covers.',
            'months.max' => 'A schedule can run for at most :max months.',
            'pl_account_id.required' => $this->input('type') === AccrualSchedule::TYPE_DEFERRED
                ? 'Choose the income account each month goes to.'
                : 'Choose the expense account each month goes to.',
        ];
    }

    public function attributes(): array
    {
        return ['start_month' => 'first month', 'total_amount' => 'total amount', 'months' => 'number of months'];
    }
}
