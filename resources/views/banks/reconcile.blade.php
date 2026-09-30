<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $bank->name }} - Bank Reconciliation
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Book Balance: {{ $bank->currency }} {{ number_format($bank->current_balance, 2) }}
                </p>
            </div>
            <a href="{{ route('banks.show', $bank) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Account
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- The message itself is shown by the layout (U9); this adds the figure. --}}
            @if(session('error') && session('reconciliation_difference'))
                <div class="mb-4 bg-red-50 dark:bg-red-900/50 border-l-4 border-red-400 p-4 rounded">
                    <p class="text-sm text-red-700 dark:text-red-300">
                        Difference: {{ $bank->currency }} {{ number_format(session('reconciliation_difference'), 2) }}
                    </p>
                </div>
            @endif

            {{-- Summary Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Reconciled Balance</div>
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $bank->currency }} {{ number_format($summary['reconciled_balance'], 2) }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Book Balance</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $bank->currency }} {{ number_format($summary['book_balance'], 2) }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Difference</div>
                    <div class="text-2xl font-bold {{ $summary['difference'] == 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $bank->currency }} {{ number_format($summary['difference'], 2) }}
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Unreconciled Items</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $summary['unreconciled_count'] }}</div>
                    @if($summary['last_reconciled_date'])
                        <div class="text-xs text-gray-400 mt-1">Last: {{ $summary['last_reconciled_date']->format('M d, Y') }}</div>
                    @endif
                </div>
            </div>

            {{-- Date Filter --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-4">
                    <form method="GET" action="{{ route('banks.reconcile', $bank) }}" class="flex flex-wrap items-end gap-4">
                        <div>
                            <label for="from_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">From Date</label>
                            <input type="date" name="from_date" id="from_date" value="{{ $fromDate }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="to_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">To Date</label>
                            <input type="date" name="to_date" id="to_date" value="{{ $toDate }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition ease-in-out duration-150">
                            Filter
                        </button>
                    </form>
                </div>
            </div>

            {{-- Reconciliation Form --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg" x-data="reconciliationForm()">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Unreconciled Transactions</h3>

                    @if($unreconciledTransactions->count() > 0)
                        <form method="POST" action="{{ route('banks.reconcile.process', $bank) }}">
                            @csrf

                            <div class="mb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="statement_balance" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Bank Statement Closing Balance</label>
                                    <input type="number" step="0.01" name="statement_balance" id="statement_balance" required
                                           x-model="statementBalance"
                                           class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                           placeholder="Enter closing balance from bank statement">
                                </div>
                                <div>
                                    <label for="statement_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Statement Date</label>
                                    <input type="date" name="statement_date" id="statement_date" required
                                           value="{{ now()->format('Y-m-d') }}"
                                           class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                </div>
                            </div>

                            {{-- Running totals --}}
                            <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-300">Selected deposits:</span>
                                    <span class="font-medium text-green-600 dark:text-green-400" x-text="'{{ $bank->currency }} ' + selectedDeposits.toFixed(2)"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-300">Selected withdrawals:</span>
                                    <span class="font-medium text-red-600 dark:text-red-400" x-text="'{{ $bank->currency }} ' + selectedWithdrawals.toFixed(2)"></span>
                                </div>
                                <div class="flex justify-between border-t border-gray-200 dark:border-gray-600 mt-2 pt-2">
                                    <span class="text-gray-600 dark:text-gray-300">Selected count:</span>
                                    <span class="font-medium" x-text="selectedCount"></span>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-left">
                                                <input type="checkbox" x-on:change="toggleAll($event.target.checked)"
                                                       class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                            </th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Reference</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Payee</th>
                                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Deposit</th>
                                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Withdrawal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($unreconciledTransactions as $txn)
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="px-4 py-3">
                                                    <input type="checkbox" name="transaction_ids[]" value="{{ $txn->id }}"
                                                           x-on:change="updateTotals()"
                                                           data-amount="{{ $txn->amount }}"
                                                           data-inflow="{{ $txn->isInflow() ? '1' : '0' }}"
                                                           class="txn-checkbox rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                    {{ $txn->date->format('M d, Y') }}
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    @if($txn->isInflow())
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                                                            {{ ucfirst(str_replace('_', ' ', $txn->type)) }}
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">
                                                            {{ ucfirst(str_replace('_', ' ', $txn->type)) }}
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                    {{ $txn->reference ?? '-' }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 max-w-xs truncate">
                                                    {{ $txn->description ?? '-' }}
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                    {{ $txn->payee ?? '-' }}
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-medium text-green-600 dark:text-green-400">
                                                    @if($txn->isInflow())
                                                        {{ number_format($txn->amount, 2) }}
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-medium text-red-600 dark:text-red-400">
                                                    @if($txn->isOutflow())
                                                        {{ number_format($txn->amount, 2) }}
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-4 flex justify-end">
                                <button type="submit"
                                        class="inline-flex items-center px-6 py-3 bg-green-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                        x-bind:disabled="selectedCount === 0">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Reconcile Selected
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">All caught up!</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No unreconciled transactions found for the selected period.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script nonce="{{ app('csp-nonce') }}">
        function reconciliationForm() {
            return {
                selectedDeposits: 0,
                selectedWithdrawals: 0,
                selectedCount: 0,
                statementBalance: '',

                toggleAll(checked) {
                    document.querySelectorAll('.txn-checkbox').forEach(cb => {
                        cb.checked = checked;
                    });
                    this.updateTotals();
                },

                updateTotals() {
                    let deposits = 0;
                    let withdrawals = 0;
                    let count = 0;

                    document.querySelectorAll('.txn-checkbox:checked').forEach(cb => {
                        const amount = parseFloat(cb.dataset.amount);
                        const isInflow = cb.dataset.inflow === '1';
                        if (isInflow) {
                            deposits += amount;
                        } else {
                            withdrawals += amount;
                        }
                        count++;
                    });

                    this.selectedDeposits = deposits;
                    this.selectedWithdrawals = withdrawals;
                    this.selectedCount = count;
                }
            };
        }
    </script>
    @endpush
</x-app-layout>
