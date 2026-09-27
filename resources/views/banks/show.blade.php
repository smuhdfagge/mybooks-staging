<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $bank->name }}
                @if($bank->is_primary)
                    <span class="ml-2 px-2 py-1 text-xs font-semibold bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 rounded-full">Primary</span>
                @endif
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('banks.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back
                </a>
                @can('edit banks')
                <a href="{{ route('banks.edit', $bank) }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Edit
                </a>
                @endcan
                @can('reconcile banks')
                <a href="{{ route('banks.reconcile', $bank) }}" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Reconcile
                </a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Current Balance</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $bank->currency }} {{ number_format($bank->current_balance, 2) }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Opening Balance</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $bank->currency }} {{ number_format($bank->opening_balance, 2) }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Account Type</div>
                    <div class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ \App\Models\Bank::getAccountTypes()[$bank->account_type] ?? $bank->account_type }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Status</div>
                    <div class="mt-1">
                        @if($bank->is_active)
                            <span class="px-3 py-1 text-sm font-semibold bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded-full">Active</span>
                        @else
                            <span class="px-3 py-1 text-sm font-semibold bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 rounded-full">Inactive</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Bank Details -->
                <div class="lg:col-span-1">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Account Details</h3>
                            <dl class="space-y-4">
                                @if($bank->bank_name)
                                <div>
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Bank Name</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $bank->bank_name }}</dd>
                                </div>
                                @endif
                                @if($bank->account_number)
                                <div>
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Account Number</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-gray-100 font-mono">{{ $bank->masked_account_number }}</dd>
                                </div>
                                @endif
                                @if($bank->routing_number)
                                <div>
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Routing Number</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-gray-100 font-mono">{{ $bank->routing_number }}</dd>
                                </div>
                                @endif
                                @if($bank->swift_code)
                                <div>
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">SWIFT/BIC Code</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-gray-100 font-mono">{{ $bank->swift_code }}</dd>
                                </div>
                                @endif
                                @if($bank->iban)
                                <div>
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">IBAN</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-gray-100 font-mono">{{ $bank->iban }}</dd>
                                </div>
                                @endif
                                @if($bank->branch_name)
                                <div>
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Branch</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $bank->branch_name }}</dd>
                                </div>
                                @endif
                                @if($bank->branch_address)
                                <div>
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Branch Address</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $bank->branch_address }}</dd>
                                </div>
                                @endif
                                <div>
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Currency</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $bank->currency }}</dd>
                                </div>
                                @if($bank->chartOfAccount)
                                <div>
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Linked Account</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        <a href="{{ route('chart-of-accounts.show', $bank->chartOfAccount) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                            {{ $bank->chartOfAccount->account_code }} - {{ $bank->chartOfAccount->name }}
                                        </a>
                                    </dd>
                                </div>
                                @endif
                                @if($bank->opening_balance_date)
                                <div>
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Opening Balance Date</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $bank->opening_balance_date->format('M d, Y') }}</dd>
                                </div>
                                @endif
                                @if($bank->description)
                                <div>
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Description</dt>
                                    <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $bank->description }}</dd>
                                </div>
                                @endif
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="lg:col-span-2">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Recent Transactions</h3>
                                <a href="{{ route('banks.transactions', $bank) }}" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">View All</a>
                            </div>
                            
                            @if($recentTransactions->count() > 0)
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead>
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Reference</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Customer/Vendor</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                            @foreach($recentTransactions as $transaction)
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $transaction['date']->format('M d, Y') }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $transaction['reference'] }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $transaction['party'] }}</td>
                                                <td class="px-4 py-3 text-sm">
                                                    @if($transaction['type'] === 'deposit')
                                                        <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                            Deposit
                                                        </span>
                                                    @else
                                                        <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                            Withdrawal
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-sm text-right font-medium {{ $transaction['type'] === 'deposit' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                                    {{ $transaction['type'] === 'deposit' ? '+' : '-' }}₦{{ number_format($transaction['amount'], 2) }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-center">
                                                    <a href="{{ $transaction['route'] }}" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                                        View
                                                    </a>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-gray-500 dark:text-gray-400 text-center py-8">No transactions yet. Transactions will appear here when payments are received or made via bank transfer.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
