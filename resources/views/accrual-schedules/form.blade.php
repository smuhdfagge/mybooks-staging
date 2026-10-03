<x-app-layout>
    @php
        $editing = $schedule->exists;
        $type = old('type', $schedule->type);
        $startMonth = old('start_month', $schedule->start_date?->format('Y-m'));
        $source = old('source', $schedule->source_type ? $schedule->source_type.':'.$schedule->source_id : '');
        $back = $editing ? route('accrual-schedules.show', $schedule) : route('accrual-schedules.index');
    @endphp
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ $editing ? 'Edit schedule '.$schedule->schedule_number : 'New prepaid or deferred schedule' }}</h2>
            <a href="{{ $back }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Back</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-error-summary />
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">The schedule doesn't record the payment itself: record that as usual (a bill, expense, invoice or journal) into the prepaid or deferred revenue account. The schedule then moves an equal share to the expense or income account at the end of each month. Months already over are released as soon as you save.</p>

            <form method="POST" action="{{ $editing ? route('accrual-schedules.update', $schedule) : route('accrual-schedules.store') }}" class="space-y-6"
                  x-data="{
                      type: @js($type),
                      total: @js((string) old('total_amount', $schedule->total_amount !== null ? (float) $schedule->total_amount : '')),
                      months: @js((string) old('months', $schedule->months)),
                      start: @js((string) $startMonth),
                      today: @js(today()->toDateString()),
                      get rows() {
                          const n = parseInt(this.months, 10);
                          const totalMinor = Math.round(parseFloat(this.total) * 100);
                          const m = /^(\d{4})-(\d{2})/.exec(this.start || '');
                          if (!m || !(n >= 1 && n <= {{ \App\Models\AccrualSchedule::MAX_MONTHS }}) || !(totalMinor > 0)) return [];
                          // Same split as the server (Money::allocate): equal shares in whole kobo, the last month takes the difference.
                          const each = Math.round(totalMinor / n);
                          const rows = [];
                          for (let i = 1; i <= n; i++) {
                              const end = new Date(Date.UTC(+m[1], +m[2] - 1 + i, 0));
                              const iso = end.toISOString().slice(0, 10);
                              rows.push({
                                  i,
                                  month: end.toLocaleDateString('en-GB', { month: 'long', year: 'numeric', timeZone: 'UTC' }),
                                  date: end.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' }),
                                  amount: (i < n ? each : totalMinor - each * (n - 1)) / 100,
                                  past: iso <= this.today,
                              });
                          }
                          return rows;
                      },
                      get pastCount() { return this.rows.filter(r => r.past).length; },
                      money(v) { return window.formatMoney ? window.formatMoney(v) : v.toFixed(2); },
                  }">
                @csrf
                @if($editing) @method('PUT') @endif

                <x-card class="p-4 sm:p-6 space-y-4">
                    <fieldset>
                        <legend class="form-label">What is it?</legend>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="flex items-start gap-2 p-3 rounded-lg border cursor-pointer" :class="type === 'prepaid_expense' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-gray-700'">
                                <input type="radio" name="type" value="prepaid_expense" x-model="type" class="mt-1">
                                <span><span class="font-medium text-gray-900 dark:text-gray-100">Prepaid expense</span>
                                    <span class="block text-sm text-gray-500 dark:text-gray-400">You paid in advance, e.g. a year's rent or insurance. Each month goes to an expense.</span></span>
                            </label>
                            <label class="flex items-start gap-2 p-3 rounded-lg border cursor-pointer" :class="type === 'deferred_revenue' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-gray-700'">
                                <input type="radio" name="type" value="deferred_revenue" x-model="type" class="mt-1">
                                <span><span class="font-medium text-gray-900 dark:text-gray-100">Deferred revenue</span>
                                    <span class="block text-sm text-gray-500 dark:text-gray-400">A customer paid you in advance, e.g. for 6 months of service. Each month becomes income.</span></span>
                            </label>
                        </div>
                        @error('type')<p class="form-error">{{ $message }}</p>@enderror
                    </fieldset>

                    <div>
                        <x-field name="description" label="Description" :value="old('description', $schedule->description)" required maxlength="255" placeholder="e.g. Shop rent, January to December" />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div><x-field name="total_amount" label="Total amount" type="number" step="0.01" min="0.01" inputmode="decimal" x-model="total" required /></div>
                        <div><x-field name="start_month" label="First month it covers" type="month" x-model="start" placeholder="YYYY-MM" required /></div>
                        <div><x-field name="months" label="Number of months" type="number" min="1" max="{{ \App\Models\AccrualSchedule::MAX_MONTHS }}" inputmode="numeric" x-model="months" required /></div>
                    </div>
                </x-card>

                <x-card class="p-4 sm:p-6 space-y-4">
                    @foreach($accountOptions as $t => $opts)
                        @php($isPrepaid = $t === \App\Models\AccrualSchedule::TYPE_PREPAID)
                        <template x-if="type === @js($t)">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <x-searchable-select name="balance_account_id" id="balance_account_id_{{ $t }}"
                                        :label="$isPrepaid ? 'Prepaid account (holds what is not used yet)' : 'Deferred revenue account (holds what is not earned yet)'"
                                        :options="$opts['balance']"
                                        :value="(string) ($type === $t ? old('balance_account_id', $schedule->balance_account_id ?? $defaults[$t]) : $defaults[$t])"
                                        :placeholder="$isPrepaid ? 'Prepaid Expenses (added if missing)' : 'Deferred Revenue (added if missing)'"
                                        :has-error="$errors->has('balance_account_id')" />
                                    @error('balance_account_id')<p id="balance_account_id_{{ $t }}-error" class="form-error">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <x-searchable-select name="pl_account_id" id="pl_account_id_{{ $t }}"
                                        :label="$isPrepaid ? 'Expense account each month goes to' : 'Income account each month goes to'"
                                        :options="$opts['pl']"
                                        :value="(string) ($type === $t ? old('pl_account_id', $schedule->pl_account_id) : '')"
                                        :placeholder="$isPrepaid ? 'Choose an expense account' : 'Choose an income account'"
                                        :has-error="$errors->has('pl_account_id')" />
                                    @error('pl_account_id')<p id="pl_account_id_{{ $t }}-error" class="form-error">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </template>
                    @endforeach

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach($sources as $t => $opts)
                            <template x-if="type === @js($t)">
                                <div>
                                    <x-searchable-select name="source" id="source_{{ $t }}"
                                        :label="$t === \App\Models\AccrualSchedule::TYPE_PREPAID ? 'Bill or expense it was paid with (optional)' : 'Invoice it was billed on (optional)'"
                                        :options="$opts" :value="$type === $t ? (string) $source : ''"
                                        placeholder="None" :has-error="$errors->has('source')" />
                                    @error('source')<p id="source_{{ $t }}-error" class="form-error">{{ $message }}</p>@enderror
                                </div>
                            </template>
                        @endforeach
                        <div>
                            <x-field name="reference" label="Reference (optional)" :value="old('reference', $schedule->reference)" maxlength="100" placeholder="e.g. lease agreement, receipt number" />
                        </div>
                    </div>
                    <div>
                        <x-field name="notes" label="Notes (optional)" type="textarea" rows="2" :value="old('notes', $schedule->notes)" />
                    </div>
                </x-card>

                <x-card class="p-4 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Month by month</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-show="rows.length === 0">Enter the total, the first month and the number of months to see the plan.</p>
                    <div x-show="rows.length > 0" x-cloak>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            <span x-text="rows.length"></span> month(s) of about <span class="font-semibold" x-text="money(rows[0]?.amount ?? 0)"></span>,
                            each posted on the last day of its month:
                            <span x-text="type === 'prepaid_expense' ? 'Dr the expense, Cr the prepaid account.' : 'Dr the deferred revenue account, Cr the income.'"></span>
                            <span x-show="pastCount > 0" class="block text-amber-700 dark:text-amber-300"><span x-text="pastCount"></span> month(s) are already over and will be released as soon as you save.</span>
                        </p>
                        <div class="mt-3 overflow-x-auto max-h-96">
                            <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                                    <tr><th class="py-2 pr-3">#</th><th class="py-2 pr-3">Month</th><th class="py-2 pr-3">Posted on</th><th class="py-2 text-right">Amount</th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-900 dark:text-gray-100">
                                    <template x-for="r in rows" :key="r.i">
                                        <tr>
                                            <td class="py-2 pr-3" x-text="r.i"></td>
                                            <td class="py-2 pr-3 whitespace-nowrap" x-text="r.month"></td>
                                            <td class="py-2 pr-3 whitespace-nowrap"><span x-text="r.date"></span> <span x-show="r.past" class="text-xs text-amber-700 dark:text-amber-300">(released on save)</span></td>
                                            <td class="py-2 text-right whitespace-nowrap" x-text="money(r.amount)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot class="font-semibold text-gray-900 dark:text-gray-100">
                                    <tr><td colspan="3" class="py-2 pr-3">Total</td><td class="py-2 text-right whitespace-nowrap" x-text="money(rows.reduce((s, r) => s + Math.round(r.amount * 100), 0) / 100)"></td></tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </x-card>

                <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-3">
                    <a href="{{ $back }}" class="text-sm text-center text-gray-600 dark:text-gray-400 hover:underline">Cancel</a>
                    <button type="submit" class="btn-primary justify-center">{{ $editing ? 'Save changes' : 'Save schedule' }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
