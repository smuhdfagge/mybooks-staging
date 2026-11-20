<x-app-layout>
    <x-slot name="header">
        <x-table.page-header :title="$customer->name" :description="$customer->company_name">
            @if (\App\Http\Middleware\EnsureFeatureEnabled::enabled('statements'))
                <x-slot name="more">
                    <x-table.menu-item :href="route('customers.statement', $customer)">Statement</x-table.menu-item>
                </x-slot>
            @endif
            <x-slot name="actions">
                @can('edit customers')
                    <a href="{{ route('customers.edit', $customer) }}" class="btn-secondary">Edit</a>
                @endcan
                @can('create invoices')
                    <a href="{{ route('invoices.create', ['customer_id' => $customer->id]) }}" class="btn-primary">New invoice</a>
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-brand-100 dark:bg-brand-900/50 rounded-full p-3">
                            <svg class="w-6 h-6 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Balance owed</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">@money($balanceOwed)</p>
                            @if(abs($balanceOwed - (float) $customer->outstanding_balance) >= 0.005)
                                <p class="text-xs text-gray-500 dark:text-gray-400">Unpaid invoices @money($customer->outstanding_balance), less credits and deposits not yet used</p>
                            @endif
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
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Deposit Balance</p>
                            <p class="text-2xl font-bold {{ ($customer->deposit_balance ?? 0) > 0 ? 'text-green-700 dark:text-green-400' : 'text-gray-900 dark:text-gray-100' }}">{{ \App\Support\Money::format($customer->deposit_balance ?? 0) }}</p>
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
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Invoices</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $customer->invoices_count ?? $customer->invoices->count() }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-brand-100 dark:bg-brand-900/50 rounded-full p-3">
                            <svg class="w-6 h-6 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Credit Limit</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ \App\Support\Money::format($customer->credit_limit) }}</p>
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
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $customer->payment_terms ? "Net {$customer->payment_terms}" : 'Due on Receipt' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Customer Details -->
                <div class="lg:col-span-1">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Contact Information</h3>
                            <dl class="space-y-4">
                                @if($customer->email)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                        <a href="mailto:{{ $customer->email }}" class="text-brand-600 dark:text-brand-300 hover:text-brand-900 dark:hover:text-brand-300">{{ $customer->email }}</a>
                                    </dd>
                                </div>
                                @endif

                                @if($customer->phone)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Phone</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                        <a href="tel:{{ $customer->phone }}" class="text-brand-600 dark:text-brand-300 hover:text-brand-900 dark:hover:text-brand-300">{{ $customer->phone }}</a>
                                    </dd>
                                </div>
                                @endif

                                @if($customer->tax_number)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Tax Number</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $customer->tax_number }}</dd>
                                </div>
                                @endif

                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                                    <dd class="mt-1">
                                        @if($customer->is_active)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-400">Active</span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-300">Inactive</span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    @if($customer->billing_address || $customer->shipping_address)
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Addresses</h3>
                            <dl class="space-y-4">
                                @if($customer->billing_address)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Billing Address</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                        {{ $customer->billing_address }}<br>
                                        @if($customer->city || $customer->state || $customer->postal_code)
                                            {{ $customer->city }}{{ $customer->city && $customer->state ? ', ' : '' }}{{ $customer->state }} {{ $customer->postal_code }}<br>
                                        @endif
                                        {{ $customer->country }}
                                    </dd>
                                </div>
                                @endif

                                @if($customer->shipping_address)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Shipping Address</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $customer->shipping_address }}</dd>
                                </div>
                                @endif
                            </dl>
                        </div>
                    </div>
                    @endif

                    @if($customer->notes)
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Notes</h3>
                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $customer->notes }}</p>
                        </div>
                    </div>
                    @endif
                </div>

                <!-- Recent Invoices -->
                <div class="lg:col-span-2">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Recent Invoices</h3>
                                <a href="{{ route('invoices.create') }}?customer_id={{ $customer->id }}" class="text-sm text-brand-600 dark:text-brand-300 hover:text-brand-900 dark:hover:text-brand-300">
                                    + New Invoice
                                </a>
                            </div>
                            
                            @if($customer->invoices->count() > 0)
                            @php
                                $today = now()->startOfDay();
                                $late = fn ($inv) => in_array($inv->status, ['sent', 'unpaid', 'partial', 'overdue'], true) && $inv->balance_due > 0 && $inv->due_date && $inv->due_date->lt($today);
                            @endphp
                            <x-table caption="Recent invoices">
                                <x-slot name="head">
                                    <x-table.th>Invoice</x-table.th>
                                    <x-table.th class="hidden sm:table-cell">Date</x-table.th>
                                    <x-table.th>Status</x-table.th>
                                    <x-table.th num class="hidden sm:table-cell">Total</x-table.th>
                                    <x-table.th num>Balance due</x-table.th>
                                </x-slot>
                                @foreach ($customer->invoices->take(10) as $invoice)
                                    <tr>
                                        <td><a href="{{ route('invoices.show', $invoice) }}" class="tbl-link">{{ $invoice->invoice_number }}</a></td>
                                        <td class="hidden sm:table-cell tbl-muted">{{ $invoice->invoice_date->format('j M Y') }}</td>
                                        <td><x-status-badge :status="$late($invoice) ? 'overdue' : $invoice->status" /></td>
                                        <td class="num hidden sm:table-cell">@fig($invoice->total, false)</td>
                                        <td class="num {{ $late($invoice) ? 'tbl-late' : \App\Support\Figure::tone($invoice->balance_due) }}">@fig($invoice->balance_due, false)</td>
                                    </tr>
                                @endforeach
                            </x-table>
                            @else
                            <div class="text-center py-8">
                                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No invoices yet</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create your first invoice for this customer.</p>
                                <div class="mt-6">
                                    <a href="{{ route('invoices.create') }}?customer_id={{ $customer->id }}" class="btn-primary">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        Create invoice
                                    </a>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Recent Payments -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Recent Payments</h3>
                            
                            @if($customer->payments && $customer->payments->count() > 0)
                            <x-table caption="Recent payments">
<x-slot name="head">
                                            <x-table.th>Date</x-table.th>
                                            <x-table.th>Invoice</x-table.th>
                                            <x-table.th>Method</x-table.th>
                                            <x-table.th num>Amount</x-table.th>
</x-slot>

                                        @foreach($customer->payments->take(5) as $payment)
                                        <tr>
                                            <td>
                                                {{ $payment->payment_date->format('j M Y') }}
                                            </td>
                                            <td>
                                                @if($payment->invoice)
                                                    <a href="{{ route('invoices.show', $payment->invoice) }}" class="text-brand-600 dark:text-brand-300 hover:text-brand-900 dark:hover:text-brand-300">
                                                        {{ $payment->invoice->invoice_number }}
                                                    </a>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                {{ ucfirst($payment->payment_method) }}
                                            </td>
                                            <td class="text-green-700 dark:text-green-400 num font-medium">
                                                +@fig($payment->amount, false)
                                            </td>
                                        </tr>
                                        @endforeach
</x-table>
                            @else
                            <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No payments recorded yet.</p>
                            @endif
                        </div>
                    </div>

                    <!-- Credit notes not yet used -->
                    @if(\App\Http\Middleware\EnsureFeatureEnabled::enabled('credit_notes'))
                    @can('view invoices')
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                        <div class="p-6">
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Unused credit</h3>
                                @can('create invoices')
                                    <a href="{{ route('credit-notes.create', ['customer_id' => $customer->id]) }}" class="text-sm text-brand-600 dark:text-brand-300">+ Credit note</a>
                                @endcan
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Credit notes for this customer, not yet applied or refunded</p>
                            <p class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-3">@money($openCredits->sum('balance'))</p>
                            @if($openCredits->isEmpty())
                                <p class="text-sm text-gray-500 dark:text-gray-400">Nothing waiting to be used.</p>
                            @else
                                <ul class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                    @foreach($openCredits as $credit)
                                        <li class="py-2 flex justify-between gap-3">
                                            <a href="{{ route('credit-notes.show', $credit) }}" class="text-brand-600 dark:text-brand-300">{{ $credit->credit_note_number }} · {{ $credit->credit_note_date->format('j M Y') }}</a>
                                            <span class="text-gray-900 dark:text-gray-100">@money($credit->balance)</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                    @endcan
                    @endif

                    <!-- Customer Deposits -->
                    @php
                        $deposits = $customer->deposits()->orderBy('payment_date', 'desc')->take(10)->get();
                    @endphp
                    @if($deposits->count() > 0 || $customer->deposit_balance > 0)
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Customer Deposits</h3>
                                <a href="{{ route('payments-received.create') }}?customer_id={{ $customer->id }}" class="text-sm text-brand-600 dark:text-brand-300 hover:text-brand-900 dark:hover:text-brand-300">
                                    + Record Deposit
                                </a>
                            </div>

                            @if($customer->deposit_balance > 0)
                            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md p-4 mb-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 text-green-700 dark:text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <span class="text-sm font-medium text-green-800 dark:text-green-300">Available Deposit Balance</span>
                                    </div>
                                    <span class="text-lg font-bold text-green-700 dark:text-green-400">{{ \App\Support\Money::format($customer->deposit_balance) }}</span>
                                </div>
                            </div>
                            @endif
                            
                            @if($deposits->count() > 0)
                            <x-table caption="Deposits">
<x-slot name="head">
                                            <x-table.th>Date</x-table.th>
                                            <x-table.th>Reference</x-table.th>
                                            <x-table.th num>Amount</x-table.th>
                                            <x-table.th num>Balance</x-table.th>
</x-slot>

                                        @foreach($deposits as $deposit)
                                        <tr>
                                            <td>
                                                {{ $deposit->payment_date->format('j M Y') }}
                                            </td>
                                            <td>
                                                <a href="{{ route('payments-received.show', $deposit) }}" class="tbl-link">
                                                    {{ $deposit->payment_number }}
                                                </a>
                                            </td>
                                            <td class="num">
                                                @fig($deposit->amount, false)
                                            </td>
                                            <td class="num">
                                                @if($deposit->unused_amount > 0)
                                                    <span class="text-green-700 dark:text-green-400 font-medium">@fig($deposit->unused_amount, false)</span>
                                                @else
                                                    <span class="text-gray-500 dark:text-gray-400">Used</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
</x-table>
                            @else
                            <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No deposits recorded.</p>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
