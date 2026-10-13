<x-app-layout>
    <x-slot name="header">
        @php $pr = $paymentReceived; @endphp
        <x-table.page-header :title="($pr->is_deposit ? 'Deposit ' : 'Payment ').$pr->payment_number">
            @if ($pr->is_deposit || $pr->payment_method === 'deposit')
                <x-slot name="badge"><x-status-badge status="open" :label="$pr->is_deposit ? 'Deposit' : 'From a deposit'" /></x-slot>
            @endif
            <x-slot name="description">
                @if ($pr->customer)<a href="{{ route('customers.show', $pr->customer) }}" class="link">{{ $pr->customer->name }}</a> · @endif
                received {{ $pr->payment_date?->format('j M Y') ?? '—' }} · @money($pr->amount)
                @if ($pr->invoice) · for invoice <a href="{{ route('invoices.show', $pr->invoice) }}" class="link">{{ $pr->invoice->invoice_number }}</a>@endif
            </x-slot>
            @can('delete payments-received')
                <x-slot name="more">
                    <x-table.menu-item danger :post="route('payments-received.destroy', $pr)" method="DELETE" confirm="Delete this payment? Its journal is reversed.">Delete payment</x-table.menu-item>
                </x-slot>
            @endcan
            <x-slot name="actions">
                @can('edit payments-received')
                    <a href="{{ route('payments-received.edit', $pr) }}" class="btn-secondary">Edit</a>
                    @if ($pr->is_deposit && $pr->unused_amount > 0)
                        <a href="{{ route('payments-received.apply-deposit', $pr) }}" class="btn-primary">Apply to an invoice</a>
                    @endif
                @endcan
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <!-- Payment Amount Card -->
            <div class="bg-green-700 rounded-lg shadow-sm p-6 mb-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium">
                            @if($paymentReceived->is_deposit)
                                Deposit Amount
                            @else
                                Amount Received
                            @endif
                        </p>
                        <p class="text-4xl font-bold mt-1">{{ number_format($paymentReceived->amount, 2) }}</p>
                        @if($paymentReceived->is_deposit)
                            <div class="mt-2 flex items-center gap-4 text-sm">
                                <span class="text-green-100">Available: <span class="font-semibold text-white">{{ number_format($paymentReceived->unused_amount, 2) }}</span></span>
                                <span class="text-green-100">Applied: <span class="font-semibold text-white">{{ number_format($paymentReceived->applied_amount, 2) }}</span></span>
                            </div>
                        @endif
                    </div>
                    <div class="bg-white/20 rounded-full p-4">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            @if($paymentReceived->is_deposit)
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            @else
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            @endif
                        </svg>
                    </div>
                </div>
            </div>

            @include('withholding-tax._payment-wht', ['payment' => $paymentReceived, 'side' => 'received'])

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Payment Details -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            Payment Details
                        </h3>
                        <dl class="space-y-3">
                            <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Payment Number</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100 font-mono">{{ $paymentReceived->payment_number }}</dd>
                            </div>
                            <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Payment Date</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $paymentReceived->payment_date?->format('j F Y') ?? 'N/A' }}</dd>
                            </div>
                            <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Payment Method</dt>
                                <dd>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $paymentReceived->payment_method === 'cash' ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' : '' }}
                                        {{ $paymentReceived->payment_method === 'bank_transfer' ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' : '' }}
                                        {{ $paymentReceived->payment_method === 'check' ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' : '' }}
                                        {{ $paymentReceived->payment_method === 'credit_card' ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' : '' }}
                                        {{ !in_array($paymentReceived->payment_method, ['cash', 'bank_transfer', 'check', 'credit_card']) ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' : '' }}">
                                        {{ ucfirst(str_replace('_', ' ', $paymentReceived->payment_method)) }}
                                    </span>
                                </dd>
                            </div>
                            @if($paymentReceived->reference)
                            <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Reference</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100 font-mono">{{ $paymentReceived->reference }}</dd>
                            </div>
                            @endif
                            <div class="flex justify-between py-2">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Amount</dt>
                                <dd class="text-lg font-bold text-green-700 dark:text-green-400">{{ number_format($paymentReceived->amount, 2) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Customer & Invoice Info -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            Customer Information
                        </h3>
                        <dl class="space-y-3">
                            <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Customer</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">
                                    @if($paymentReceived->customer)
                                        <a href="{{ route('customers.show', $paymentReceived->customer) }}" class="text-brand-600 dark:text-brand-300 hover:underline">
                                            {{ $paymentReceived->customer->name }}
                                        </a>
                                    @else
                                        -
                                    @endif
                                </dd>
                            </div>
                            @if($paymentReceived->customer?->email)
                            <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $paymentReceived->customer->email }}</dd>
                            </div>
                            @endif
                            <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Applied to Invoice</dt>
                                <dd>
                                    @if($paymentReceived->invoice)
                                        <a href="{{ route('invoices.show', $paymentReceived->invoice) }}" class="text-sm text-brand-600 dark:text-brand-300 hover:underline">
                                            {{ $paymentReceived->invoice->invoice_number }}
                                        </a>
                                    @elseif($paymentReceived->is_deposit)
                                        <span class="text-sm text-green-700 dark:text-green-400">Customer Deposit</span>
                                    @else
                                        <span class="text-sm text-gray-500 dark:text-gray-400">General Payment</span>
                                    @endif
                                </dd>
                            </div>
                            @if($paymentReceived->invoice)
                            <div class="flex justify-between py-2">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Invoice Balance</dt>
                                <dd class="text-sm {{ $paymentReceived->invoice->balance_due > 0 ? 'text-red-600 dark:text-red-300' : 'text-green-700 dark:text-green-400' }} font-medium">
                                    {{ number_format($paymentReceived->invoice->balance_due, 2) }}
                                    @if($paymentReceived->invoice->balance_due <= 0)
                                        <span class="ml-1 text-xs">(Paid)</span>
                                    @endif
                                </dd>
                            </div>
                            @endif
                            @if($paymentReceived->is_deposit)
                            <div class="flex justify-between py-2 border-t border-gray-100 dark:border-gray-700">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Deposit Type</dt>
                                <dd>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-400">
                                        Advance Payment
                                    </span>
                                </dd>
                            </div>
                            <div class="flex justify-between py-2">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Available Balance</dt>
                                <dd class="text-sm font-medium {{ $paymentReceived->unused_amount > 0 ? 'text-green-700 dark:text-green-400' : 'text-gray-500 dark:text-gray-400' }}">
                                    {{ number_format($paymentReceived->unused_amount, 2) }}
                                </dd>
                            </div>
                            @endif
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Deposit Applications (if this is a deposit) -->
            @if($paymentReceived->is_deposit && $paymentReceived->depositApplications->count() > 0)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-brand-500 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        Deposit Applications
                    </h3>
                    <x-table caption="Where this deposit went">
<x-slot name="head">
                                    <x-table.th>Date</x-table.th>
                                    <x-table.th>Invoice</x-table.th>
                                    <x-table.th num>Amount applied</x-table.th>
</x-slot>

                                @foreach($paymentReceived->depositApplications as $application)
                                <tr>
                                    <td>
                                        {{ $application->application_date->format('j M Y') }}
                                    </td>
                                    <td>
                                        <a href="{{ route('invoices.show', $application->invoice) }}" class="text-brand-600 dark:text-brand-300 hover:underline">
                                            {{ $application->invoice->invoice_number }}
                                        </a>
                                    </td>
                                    <td class="num font-medium">
                                        @fig($application->amount, false)
                                    </td>
                                </tr>
                                @endforeach
<x-slot name="foot">
                                <tr class="font-semibold">
                                    <td colspan="2">Total Applied</td>
                                    <td class="num">@fig($paymentReceived->applied_amount, false)</td>
                                </tr>
</x-slot>
</x-table>
                </div>
            </div>
            @endif

            <!-- Notes -->
            @if($paymentReceived->notes)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Notes
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-wrap">{{ $paymentReceived->notes }}</p>
                </div>
            </div>
            @endif

            <!-- Journal Entry / Double-Entry -->
            @if($paymentReceived->journal)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center justify-between">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                            Journal Entry
                        </span>
                        <a href="{{ route('journals.show', $paymentReceived->journal) }}" class="text-sm text-brand-600 dark:text-brand-300 hover:underline">
                            {{ $paymentReceived->journal->journal_number }}
                        </a>
                    </h3>
                    <x-table caption="Ledger postings">
<x-slot name="head">
                                    <x-table.th>Account</x-table.th>
                                    <x-table.th num>Debit</x-table.th>
                                    <x-table.th num>Credit</x-table.th>
</x-slot>

                                @foreach($paymentReceived->journal->entries as $entry)
                                <tr>
                                    <td>
                                        <span class="font-mono text-xs text-gray-500 dark:text-gray-400 mr-2">{{ $entry->account->account_code }}</span>
                                        {{ $entry->account->name }}
                                    </td>
                                    <td class="num {{ $entry->debit > 0 ? 'text-gray-900 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400' }}">
                                        {{ $entry->debit > 0 ? number_format($entry->debit, 2) : '-' }}
                                    </td>
                                    <td class="num {{ $entry->credit > 0 ? 'text-gray-900 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400' }}">
                                        {{ $entry->credit > 0 ? number_format($entry->credit, 2) : '-' }}
                                    </td>
                                </tr>
                                @endforeach
<x-slot name="foot">
                                <tr class="font-semibold">
                                    <td>Total</td>
                                    <td class="num">@fig($paymentReceived->journal->total_debit, false)</td>
                                    <td class="num">@fig($paymentReceived->journal->total_credit, false)</td>
                                </tr>
</x-slot>
</x-table>
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        Posted on {{ $paymentReceived->journal->posted_at?->format('j M Y, H:i') ?? 'Not posted' }}
                    </p>
                </div>
            </div>
            @endif

            <p class="mt-6 text-sm text-gray-600 dark:text-gray-400">
                    Created {{ $paymentReceived->created_at->diffForHumans() }}
                            </p>
        </div>
    </div>
</x-app-layout>
