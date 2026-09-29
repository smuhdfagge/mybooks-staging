<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ __('Payroll Liabilities') }}</h2>
            <a href="{{ route('payroll.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">&larr; Back to Payroll</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="p-4 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300">{{ session('success') }}</div>
            @endif

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">What payroll owes</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Balances today. Approving a payroll adds to these; recording a payment below takes it off.</p>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Account</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Owed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($accounts as $account)
                                <tr>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $account->account_code }} - {{ $account->name }}</td>
                                    <td class="px-4 py-2 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ number_format($account->current_balance, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-3">Accrued Salaries is net pay owed to staff; it clears when you mark payroll as paid.</p>
            </div>

            @can('edit payroll')
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Record a payment to the tax office or a fund</h3>
                <form method="POST" action="{{ route('payroll.liabilities.remit') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @csrf
                    <div>
                        <label for="account_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Paying</label>
                        <select id="account_code" name="account_code" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200" required>
                            @foreach($accounts as $account)
                                @continue($account->account_code === \App\Services\AccountCodeService::resolve(auth()->user()->tenant_id, 'accrued_salaries'))
                                <option value="{{ $account->account_code }}" @selected(old('account_code') === $account->account_code)>{{ $account->name }} ({{ number_format($account->current_balance, 2) }} owed)</option>
                            @endforeach
                        </select>
                        @error('account_code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount</label>
                        <input id="amount" type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount') }}" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200" required>
                        @error('amount')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date paid</label>
                        <input id="date" type="date" name="date" value="{{ old('date', now()->toDateString()) }}" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200" required>
                        @error('date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="payment_method" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Paid by</label>
                        <select id="payment_method" name="payment_method" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                            @foreach($methods as $value => $label)
                                <option value="{{ $value }}" @selected(old('payment_method', 'bank_transfer') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="reference" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reference (receipt or remittance number)</label>
                        <input id="reference" type="text" name="reference" maxlength="100" value="{{ old('reference') }}" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">Record payment</button>
                    </div>
                </form>
            </div>
            @endcan
        </div>
    </div>
</x-app-layout>
