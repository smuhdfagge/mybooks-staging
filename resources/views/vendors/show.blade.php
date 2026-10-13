<x-app-layout>
    <x-slot name="header">
        <x-table.page-header :title="$vendor->name" :description="$vendor->company_name">
            @if (\App\Http\Middleware\EnsureFeatureEnabled::enabled('statements'))
                <x-slot name="more">
                    <x-table.menu-item :href="route('vendors.statement', $vendor)">Statement</x-table.menu-item>
                </x-slot>
            @endif
            <x-slot name="actions">
                @can('edit vendors')
                    <a href="{{ route('vendors.edit', $vendor) }}" class="btn-secondary">Edit</a>
                @endcan
                @can('create bills')
                    <a href="{{ route('bills.create', ['vendor_id' => $vendor->id]) }}" class="btn-primary">New bill</a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-red-100 dark:bg-red-900/50 rounded-full p-3">
                            <svg class="w-6 h-6 text-red-600 dark:text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Balance we owe</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">@money($balanceOwed)</p>
                            @if(abs($balanceOwed - (float) $vendor->outstanding_balance) >= 0.005)
                                <p class="text-xs text-gray-500 dark:text-gray-400">Unpaid bills @money($vendor->outstanding_balance); the balance also counts credits and advances not yet used, and assets bought on account</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-brand-100 dark:bg-brand-900/50 rounded-full p-3">
                            <svg class="w-6 h-6 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Bills</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $vendor->bills_count ?? $vendor->bills->count() }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 dark:bg-green-900/50 rounded-full p-3">
                            <svg class="w-6 h-6 text-green-700 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Purchases</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($vendor->total_purchases, 2) }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-brand-100 dark:bg-brand-900/50 rounded-full p-3">
                            <svg class="w-6 h-6 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Payment Terms</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $vendor->payment_terms ? "Net {$vendor->payment_terms}" : 'Due on Receipt' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Vendor Details -->
                <div class="lg:col-span-1">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Contact Information</h3>
                            <dl class="space-y-4">
                                @if($vendor->email)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                        <a href="mailto:{{ $vendor->email }}" class="text-brand-600 dark:text-brand-300 hover:text-brand-900 dark:hover:text-brand-300">{{ $vendor->email }}</a>
                                    </dd>
                                </div>
                                @endif

                                @if($vendor->phone)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Phone</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                        <a href="tel:{{ $vendor->phone }}" class="text-brand-600 dark:text-brand-300 hover:text-brand-900 dark:hover:text-brand-300">{{ $vendor->phone }}</a>
                                    </dd>
                                </div>
                                @endif

                                @if($vendor->tax_number)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Tax Number</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $vendor->tax_number }}</dd>
                                </div>
                                @endif

                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                                    <dd class="mt-1">
                                        @if($vendor->is_active)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-400">Active</span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-300">Inactive</span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    @if($vendor->address)
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Address</h3>
                            <p class="text-sm text-gray-900 dark:text-gray-100">
                                {{ $vendor->address }}<br>
                                @if($vendor->city || $vendor->state || $vendor->postal_code)
                                    {{ $vendor->city }}{{ $vendor->city && $vendor->state ? ', ' : '' }}{{ $vendor->state }} {{ $vendor->postal_code }}<br>
                                @endif
                                {{ $vendor->country }}
                            </p>
                        </div>
                    </div>
                    @endif

                    @if($vendor->notes)
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Notes</h3>
                            <p class="text-sm text-gray-900 dark:text-gray-100 whitespace-pre-line">{{ $vendor->notes }}</p>
                        </div>
                    </div>
                    @endif
                </div>

                <!-- Recent Bills & Expenses -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Recent Bills -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Recent Bills</h3>
                                <a href="{{ route('bills.create') }}?vendor_id={{ $vendor->id }}" class="text-sm text-brand-600 dark:text-brand-300 hover:text-brand-900">+ New Bill</a>
                            </div>
                            @if($vendor->bills->count() > 0)
                                <x-table caption="Recent bills">
<x-slot name="head">
                                                <x-table.th>Bill #</x-table.th>
                                                <x-table.th>Date</x-table.th>
                                                <x-table.th>Due date</x-table.th>
                                                <x-table.th num>Total</x-table.th>
                                                <x-table.th num>Balance</x-table.th>
                                                <x-table.th class="text-center">Status</x-table.th>
</x-slot>

                                            @foreach($vendor->bills->take(5) as $bill)
                                                <tr>
                                                    <td>
                                                        <a href="{{ route('bills.show', $bill) }}" class="tbl-link">{{ $bill->bill_number }}</a>
                                                    </td>
                                                    <td>{{ $bill->bill_date->format('j M Y') }}</td>
                                                    <td>{{ $bill->due_date->format('j M Y') }}</td>
                                                    <td class="num">@fig($bill->total, false)</td>
                                                    <td class="num">@fig($bill->balance_due, false)</td>
                                                    <td class="text-center">
                                                        @if($bill->status === 'paid')
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-400">Paid</span>
                                                        @elseif($bill->status === 'partial')
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 dark:bg-yellow-900/50 text-yellow-800 dark:text-yellow-400">Partial</span>
                                                        @else
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-300">Unpaid</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
</x-table>
                            @else
                                <p class="text-sm text-gray-500 dark:text-gray-400">No bills recorded for this vendor.</p>
                            @endif
                        </div>
                    </div>

                    <!-- Supplier credits and advances not yet used -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Credits and advances</h3>
                                <div class="flex gap-4 text-sm">
                                    @can('create bills')<a href="{{ route('vendor-credits.create', ['vendor_id' => $vendor->id]) }}" class="text-brand-600 dark:text-brand-300">+ Supplier credit</a>@endcan
                                    @can('create payments-made')<a href="{{ route('supplier-advances.create', ['vendor_id' => $vendor->id]) }}" class="text-brand-600 dark:text-brand-300">+ Advance</a>@endcan
                                </div>
                            </div>
                            <dl class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Credit from this supplier, not yet used</dt>
                                    <dd class="text-xl font-semibold text-gray-900 dark:text-gray-100">@money($openCredits->sum('balance'))</dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Paid in advance, not yet used</dt>
                                    <dd class="text-xl font-semibold text-gray-900 dark:text-gray-100">@money($openAdvances->sum('unused_amount'))</dd>
                                </div>
                            </dl>
                            @if($openCredits->isEmpty() && $openAdvances->isEmpty())
                                <p class="text-sm text-gray-500 dark:text-gray-400">Nothing waiting to be used.</p>
                            @else
                                <ul class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                    @foreach($openCredits as $credit)
                                        <li class="py-2 flex justify-between gap-3">
                                            <a href="{{ route('vendor-credits.show', $credit) }}" class="text-brand-600 dark:text-brand-300">{{ $credit->vendor_credit_number }} · {{ $credit->credit_date->format('j M Y') }}</a>
                                            <span class="text-gray-900 dark:text-gray-100">@money($credit->balance)</span>
                                        </li>
                                    @endforeach
                                    @foreach($openAdvances as $advance)
                                        <li class="py-2 flex justify-between gap-3">
                                            <a href="{{ route('supplier-advances.show', $advance) }}" class="text-brand-600 dark:text-brand-300">Advance {{ $advance->payment_number }} · {{ $advance->payment_date->format('j M Y') }}</a>
                                            <span class="text-gray-900 dark:text-gray-100">@money($advance->unused_amount)</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>

                    <!-- Recent Expenses -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Recent Expenses</h3>
                                <a href="{{ route('expenses.create') }}?vendor_id={{ $vendor->id }}" class="text-sm text-brand-600 dark:text-brand-300 hover:text-brand-900">+ New Expense</a>
                            </div>
                            @if($vendor->expenses->count() > 0)
                                <x-table caption="Recent expenses">
<x-slot name="head">
                                                <x-table.th>Expense #</x-table.th>
                                                <x-table.th>Date</x-table.th>
                                                <x-table.th>Description</x-table.th>
                                                <x-table.th num>Amount</x-table.th>
</x-slot>

                                            @foreach($vendor->expenses->take(5) as $expense)
                                                <tr>
                                                    <td>
                                                        <a href="{{ route('expenses.show', $expense) }}" class="tbl-link">{{ $expense->expense_number }}</a>
                                                    </td>
                                                    <td>{{ $expense->expense_date->format('j M Y') }}</td>
                                                    <td>{{ Str::limit($expense->description, 30) }}</td>
                                                    <td class="num">@fig($expense->total, false)</td>
                                                </tr>
                                            @endforeach
</x-table>
                            @else
                                <p class="text-sm text-gray-500 dark:text-gray-400">No expenses recorded for this vendor.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
