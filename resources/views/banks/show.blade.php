<x-app-layout>
    <x-slot name="header">
        <x-table.page-header :title="$bank->name" :description="collect([$bank->bank_name, $bank->account_number ? $bank->masked_account_number : null])->filter()->push('Amounts in '.trim(\App\Support\Money::symbol($bank->currency ?: null)).'.')->join(' · ')">
            @if ($bank->is_primary)
                <x-slot name="badge"><x-status-badge status="active" label="Main account" /></x-slot>
            @endif
            <x-slot name="more">
                <x-table.menu-item :href="route('banks.transactions', $bank)">Money in and out</x-table.menu-item>
                @can('edit banks')
                    <x-table.menu-item :href="route('banks.edit', $bank)">Edit</x-table.menu-item>
                @endcan
                @if (\App\Http\Middleware\EnsureFeatureEnabled::enabled('bank_feeds'))
                    @if ($feed)
                        <a href="{{ route('bank-feeds.index') }}" role="menuitem" class="flex w-full items-center rounded-md px-2.5 py-1.5 text-left text-sm text-gray-800 hover:bg-gray-100 focus:bg-gray-100 focus:outline-none dark:text-gray-200 dark:hover:bg-gray-700 dark:focus:bg-gray-700" data-testid="feed-status">Bank feed: {{ $feed->statusEnum()->label() }}</a>
                    @else
                        @can('edit banks')
                            <a href="{{ route('bank-feeds.connect', ['bank' => $bank->id]) }}" role="menuitem" class="flex w-full items-center rounded-md px-2.5 py-1.5 text-left text-sm text-gray-800 hover:bg-gray-100 focus:bg-gray-100 focus:outline-none dark:text-gray-200 dark:hover:bg-gray-700 dark:focus:bg-gray-700" data-testid="connect-feed">Connect bank feed</a>
                        @endcan
                    @endif
                @endif
                <x-table.menu-item :href="route('banks.index')">All bank accounts</x-table.menu-item>
            </x-slot>
            <x-slot name="actions">
                @can('reconcile banks')
                    <a href="{{ route('banks.reconcile', $bank) }}" class="btn-new">Reconcile</a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-report.stats :cols="4" class="mb-6">
                <x-report.stat label="Balance" :value="\App\Support\Figure::show($bank->current_balance, false)" />
                <x-report.stat label="Opening balance" :value="\App\Support\Figure::show($bank->opening_balance, false)" :hint="$bank->opening_balance_date?->format('j M Y')" />
                <x-report.stat label="Account type" :value="\App\Models\Bank::getAccountTypes()[$bank->account_type] ?? \Illuminate\Support\Str::headline((string) $bank->account_type)" />
                <x-report.stat label="Status" :value="$bank->is_active ? 'In use' : 'Not in use'" :tone="$bank->is_active ? null : 'bad'" />
            </x-report.stats>

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
                                        <a href="{{ route('chart-of-accounts.show', $bank->chartOfAccount) }}" class="text-brand-600 hover:text-brand-800 dark:text-brand-300">
                                            {{ $bank->chartOfAccount->account_code }} - {{ $bank->chartOfAccount->name }}
                                        </a>
                                    </dd>
                                </div>
                                @endif
                                @if($bank->opening_balance_date)
                                <div>
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Opening Balance Date</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $bank->opening_balance_date->format('j M Y') }}</dd>
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
                                <a href="{{ route('banks.transactions', $bank) }}" class="text-sm text-brand-600 hover:text-brand-800 dark:text-brand-300">View All</a>
                            </div>
                            
                            @if($recentTransactions->count() > 0)
                                <x-table caption="Recent transactions">
<x-slot name="head">
                                                <x-table.th>Date</x-table.th>
                                                <x-table.th>Reference</x-table.th>
                                                <x-table.th>Customer/Vendor</x-table.th>
                                                <x-table.th>Type</x-table.th>
                                                <x-table.th num>Amount</x-table.th>
                                                <x-table.th class="text-center">Action</x-table.th>
</x-slot>

                                            @foreach($recentTransactions as $transaction)
                                            <tr>
                                                <td>{{ $transaction['date']->format('j M Y') }}</td>
                                                <td>{{ $transaction['reference'] }}</td>
                                                <td>{{ $transaction['party'] }}</td>
                                                <td>
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
                                                <td class="num font-medium {{ $transaction['type'] === 'deposit' ? 'text-green-700 dark:text-green-400' : 'text-red-600 dark:text-red-300' }}">
                                                    {{ $transaction['type'] === 'deposit' ? '+' : '-' }}@fig($transaction['amount'], false)
                                                </td>
                                                <td class="text-center">
                                                    <a href="{{ $transaction['route'] }}" class="tbl-link">
                                                        View
                                                    </a>
                                                </td>
                                            </tr>
                                            @endforeach
</x-table>
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
