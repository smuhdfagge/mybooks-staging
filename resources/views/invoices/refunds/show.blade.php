<x-app-layout>
    <x-slot name="header">
        <x-table.page-header :title="'Refund '.$refund->refund_number">
            <x-slot name="badge"><x-status-badge :status="$refund->status" /></x-slot>
            <x-slot name="description">
                For invoice <a href="{{ route('invoices.show', $refund->invoice) }}" class="link">{{ $refund->invoice->invoice_number }}</a> · {{ $refund->customer->name }}
            </x-slot>
            <x-slot name="more">
                <x-table.menu-item :href="route('invoices.refunds.print', $refund)">Print</x-table.menu-item>
                @if ($refund->status !== 'cancelled')
                    <x-table.menu-item danger :post="route('invoices.refunds.cancel', $refund)" method="PATCH" confirm="Cancel this refund? The amount goes back onto the invoice.">Cancel refund</x-table.menu-item>
                @endif
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <!-- Status Banner -->
            @php
                $statusColors = [
                    'pending' => 'bg-yellow-100 dark:bg-yellow-900/50 border-yellow-300 dark:border-yellow-700 text-yellow-800 dark:text-yellow-200',
                    'completed' => 'bg-green-100 dark:bg-green-900/50 border-green-300 dark:border-green-700 text-green-800 dark:text-green-200',
                    'cancelled' => 'bg-red-100 dark:bg-red-900/50 border-red-300 dark:border-red-700 text-red-800 dark:text-red-200',
                ];
            @endphp
            <div class="mb-6 p-4 rounded-lg border {{ $statusColors[$refund->status] ?? 'bg-gray-100 border-gray-300' }}">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        @if($refund->status === 'completed')
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        @elseif($refund->status === 'cancelled')
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        @else
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        @endif
                        <span class="font-semibold">Status: {{ ucfirst($refund->status) }}</span>
                    </div>
                    <div class="text-2xl font-bold">
                        {{ number_format($refund->amount, 2) }}
                    </div>
                </div>
            </div>

            <!-- Refund Details -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6">Refund Details</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Refund Number</p>
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $refund->refund_number }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Refund Date</p>
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $refund->refund_date->format('j F Y') }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Refund Method</p>
                                <p class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ \App\Models\InvoiceRefund::METHODS[$refund->refund_method] ?? ucfirst($refund->refund_method) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Reason</p>
                                <p class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ $refund->reason ? (\App\Models\InvoiceRefund::REASONS[$refund->reason] ?? $refund->reason) : '-' }}
                                </p>
                            </div>
                        </div>
                        
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Invoice</p>
                                <a href="{{ route('invoices.show', $refund->invoice) }}" 
                                   class="font-medium text-brand-600 dark:text-brand-300 hover:underline">
                                    {{ $refund->invoice->invoice_number }}
                                </a>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Customer</p>
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $refund->customer->name }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Reference</p>
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $refund->reference ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Created By</p>
                                <p class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ $refund->createdBy?->name ?? 'System' }}
                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                                        on {{ $refund->created_at->format('j M Y, H:i') }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    @if($refund->notes)
                    <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Notes</p>
                        <p class="text-gray-900 dark:text-gray-100">{{ $refund->notes }}</p>
                    </div>
                    @endif

                    @if($refund->status === 'completed' && $refund->approvedBy)
                    <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Processed by <span class="font-medium text-gray-900 dark:text-gray-100">{{ $refund->approvedBy->name }}</span>
                            on {{ $refund->approved_at->format('j M Y, H:i') }}
                        </p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Journal Entry -->
            @if($refund->journal)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center justify-between">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                            Journal Entry
                        </span>
                        <a href="{{ route('journals.show', $refund->journal) }}" class="text-sm text-brand-600 dark:text-brand-300 hover:underline">
                            {{ $refund->journal->journal_number }}
                        </a>
                    </h3>
                    <x-table caption="Ledger postings">
<x-slot name="head">
                                    <x-table.th>Account</x-table.th>
                                    <x-table.th num>Debit</x-table.th>
                                    <x-table.th num>Credit</x-table.th>
</x-slot>

                                @foreach($refund->journal->entries as $entry)
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
                                    <td class="num">@fig($refund->journal->total_debit, false)</td>
                                    <td class="num">@fig($refund->journal->total_credit, false)</td>
                                </tr>
</x-slot>
</x-table>
                </div>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
