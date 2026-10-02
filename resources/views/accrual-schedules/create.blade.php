<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">New Prepayment or Deferred Income Schedule</h2>
            <a href="{{ route('accrual-schedules.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400">Back</a>
        </div>
    </x-slot>

    @php
        $old = fn ($k, $d = null) => old($k, $d);
    @endphp

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-error-summary />
            <form method="POST" action="{{ route('accrual-schedules.store') }}" class="space-y-6"
                  x-data="{
                      type: @js($old('type', $type)),
                      funding: @js($old('funding', 'bank')),
                      total: @js((float) $old('total_amount', 0)),
                      months: @js((int) $old('months', 12)),
                      accounts: @js($accounts),
                      balanceId: @js((string) $old('balance_account_id', $type === 'deferred_revenue' ? $defaults['deferred_balance'] : $defaults['prepaid_balance'])),
                      defaults: @js($defaults),
                      of(types) { return this.accounts.filter(a => types.includes(a.type)); },
                      setType(t) { this.type = t; this.balanceId = String(t === 'deferred_revenue' ? this.defaults.deferred_balance : this.defaults.prepaid_balance); },
                      monthly() { return this.months > 0 ? Math.round(this.total / this.months * 100) / 100 : 0; },
                  }">
                @csrf
                <x-card class="p-6 space-y-4">
                    <fieldset>
                        <legend class="form-label">What is it?</legend>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="flex items-start gap-2 p-3 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer">
                                <input type="radio" name="type" value="prepaid_expense" :checked="type === 'prepaid_expense'" @change="setType('prepaid_expense')" class="mt-1">
                                <span><span class="font-medium text-gray-900 dark:text-gray-100">Paid in advance</span>
                                    <span class="block text-sm text-gray-500 dark:text-gray-400">e.g. a year's rent or insurance paid up front</span></span>
                            </label>
                            <label class="flex items-start gap-2 p-3 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer">
                                <input type="radio" name="type" value="deferred_revenue" :checked="type === 'deferred_revenue'" @change="setType('deferred_revenue')" class="mt-1">
                                <span><span class="font-medium text-gray-900 dark:text-gray-100">Received in advance</span>
                                    <span class="block text-sm text-gray-500 dark:text-gray-400">e.g. a customer paid for 6 months of service</span></span>
                            </label>
                        </div>
                    </fieldset>
                    <x-field name="description" label="Description" :value="$old('description')" required placeholder="e.g. Shop rent, Jan to Dec 2027" />
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div><x-field name="total_amount" label="Total amount" type="number" step="0.01" min="0.01" x-model.number="total" required /></div>
                        <div><x-field name="start_date" label="First month it covers" type="date" :value="$old('start_date', now()->startOfMonth()->toDateString())" required /></div>
                        <div><x-field name="months" label="Number of months" type="number" min="1" max="120" x-model.number="months" required /></div>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-300">About <span class="font-semibold" x-text="window.formatMoney ? formatMoney(monthly()) : monthly()"></span> a month, released at the end of each month.</p>
                </x-card>

                <x-card class="p-6 space-y-4">
                    <div>
                        <label for="pl_account_id" class="form-label" x-text="type === 'prepaid_expense' ? 'Expense account each month goes to' : 'Income account each month goes to'"></label>
                        <select name="pl_account_id" id="pl_account_id" class="form-control" required>
                            <template x-for="a in of(type === 'prepaid_expense' ? ['expense'] : ['income'])" :key="a.id">
                                <option :value="a.id" x-text="a.account_code + ' ' + a.name" :selected="String(a.id) === @js((string) old('pl_account_id'))"></option>
                            </template>
                        </select>
                        @error('pl_account_id')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="balance_account_id" class="form-label" x-text="type === 'prepaid_expense' ? 'Hold what is not used yet in' : 'Hold what is not earned yet in'"></label>
                        <select name="balance_account_id" id="balance_account_id" class="form-control" x-model="balanceId" required>
                            <template x-for="a in of(type === 'prepaid_expense' ? ['asset'] : ['liability'])" :key="a.id">
                                <option :value="String(a.id)" x-text="a.account_code + ' ' + a.name"></option>
                            </template>
                        </select>
                        @error('balance_account_id')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                </x-card>

                <x-card class="p-6 space-y-4">
                    <fieldset>
                        <legend class="form-label">How was it paid or received?</legend>
                        <div class="space-y-2">
                            @foreach(\App\Models\AccrualSchedule::FUNDING as $value => $label)
                                <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input type="radio" name="funding" value="{{ $value }}" x-model="funding" class="mt-1"> {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div x-show="funding === 'bank'">
                        <x-field name="funding_account_id" label="Bank or cash account" type="select">
                            @foreach($accounts->whereIn('sub_type', ['cash', 'bank']) as $account)
                                <option value="{{ $account->id }}" @selected((string) old('funding_account_id', $defaults['cash']) === (string) $account->id)>{{ $account->account_code }} {{ $account->name }}</option>
                            @endforeach
                        </x-field>
                    </div>
                    <div x-show="funding !== 'existing'">
                        <x-field name="recorded_date" label="Date paid or received" type="date" :value="$old('recorded_date', now()->toDateString())" required />
                    </div>
                    <x-field name="notes" label="Notes" type="textarea" rows="2" :value="$old('notes')" />
                </x-card>

                <div class="flex justify-end"><button class="btn-primary">Save schedule</button></div>
            </form>
        </div>
    </div>
</x-app-layout>
