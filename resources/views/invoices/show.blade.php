<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Invoice {{ $invoice->invoice_number }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $invoice->customer->name }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($invoice->status === 'draft')
                <form action="{{ route('invoices.send', $invoice) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                        Send
                    </button>
                </form>
                @endif
                @php
                    $maxRefundable = $invoice->amount_paid - ($invoice->total_refunded ?? 0);
                @endphp
                @if($maxRefundable > 0 && !in_array($invoice->status, ['draft', 'cancelled']))
                <a href="{{ route('invoices.refunds.create', $invoice) }}" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                    </svg>
                    Refund
                </a>
                @endif
                <a href="{{ route('invoices.print', $invoice) }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Print
                </a>
                <a href="{{ route('invoices.edit', $invoice) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit
                </a>
                <a href="{{ route('invoices.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <!-- Status Banner -->
            @php
                $statusColors = [
                    'draft' => 'bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200',
                    'sent' => 'bg-blue-100 dark:bg-blue-900/50 border-blue-300 dark:border-blue-700 text-blue-800 dark:text-blue-200',
                    'viewed' => 'bg-yellow-100 dark:bg-yellow-900/50 border-yellow-300 dark:border-yellow-700 text-yellow-800 dark:text-yellow-200',
                    'partial' => 'bg-orange-100 dark:bg-orange-900/50 border-orange-300 dark:border-orange-700 text-orange-800 dark:text-orange-200',
                    'paid' => 'bg-green-100 dark:bg-green-900/50 border-green-300 dark:border-green-700 text-green-800 dark:text-green-200',
                    'overdue' => 'bg-red-100 dark:bg-red-900/50 border-red-300 dark:border-red-700 text-red-800 dark:text-red-200',
                    'cancelled' => 'bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200',
                ];
            @endphp
            <div class="mb-6 p-4 rounded-lg border {{ $statusColors[$invoice->status] ?? 'bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600' }}">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div class="flex items-center gap-3">
                        <span class="font-semibold">Status: {{ ucfirst($invoice->status) }}</span>
                        @if($invoice->status === 'overdue')
                            <span class="text-sm">({{ $invoice->due_date->diffForHumans() }})</span>
                        @endif
                    </div>
                    <div class="text-sm">
                        <span>Balance Due: </span>
                        <span class="font-bold text-lg">{{ number_format($invoice->balance_due, 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Invoice Document -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 lg:p-8">
                    <!-- Header -->
                    <div class="flex flex-col lg:flex-row lg:justify-between gap-6 mb-8 pb-6 border-b border-gray-200 dark:border-gray-700">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">INVOICE</h1>
                            <p class="text-lg text-gray-600 dark:text-gray-400 mt-1"># {{ $invoice->invoice_number }}</p>
                            @if($invoice->reference)
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Ref: {{ $invoice->reference }}</p>
                            @endif
                        </div>
                        <div class="text-left lg:text-right">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Invoice Date</p>
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $invoice->invoice_date->format('F d, Y') }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Due Date</p>
                            <p class="font-medium {{ $invoice->due_date->isPast() && $invoice->balance_due > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100' }}">
                                {{ $invoice->due_date->format('F d, Y') }}
                            </p>
                            @if($warehouseName = \App\Models\Warehouse::nameIfMany($invoice->warehouse_id))
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Warehouse</p>
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $warehouseName }}</p>
                            @endif
                        </div>
                    </div>

                    <!-- Bill To -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Bill To</h3>
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $invoice->customer->name }}</p>
                            @if($invoice->customer->company_name)
                                <p class="text-gray-600 dark:text-gray-400">{{ $invoice->customer->company_name }}</p>
                            @endif
                            @if($invoice->customer->billing_address)
                                <p class="text-gray-600 dark:text-gray-400 mt-1">{{ $invoice->customer->billing_address }}</p>
                            @endif
                            @if($invoice->customer->city || $invoice->customer->state)
                                <p class="text-gray-600 dark:text-gray-400">
                                    {{ $invoice->customer->city }}{{ $invoice->customer->city && $invoice->customer->state ? ', ' : '' }}{{ $invoice->customer->state }} {{ $invoice->customer->postal_code }}
                                </p>
                            @endif
                            @if($invoice->customer->email)
                                <p class="text-gray-600 dark:text-gray-400 mt-2">{{ $invoice->customer->email }}</p>
                            @endif
                        </div>
                    </div>

                    <!-- Line Items -->
                    <div class="overflow-x-auto mb-8">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b-2 border-gray-200 dark:border-gray-700">
                                    <th class="text-left py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">Description</th>
                                    <th class="text-right py-3 text-sm font-semibold text-gray-900 dark:text-gray-100 w-24">Qty</th>
                                    <th class="text-right py-3 text-sm font-semibold text-gray-900 dark:text-gray-100 w-32">Price</th>
                                    <th class="text-right py-3 text-sm font-semibold text-gray-900 dark:text-gray-100 w-24">Tax</th>
                                    <th class="text-right py-3 text-sm font-semibold text-gray-900 dark:text-gray-100 w-32">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoice->items as $item)
                                <tr class="border-b border-gray-100 dark:border-gray-700">
                                    <td class="py-4">
                                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $item->description }}</p>
                                        @if($item->item)
                                            <p class="text-sm text-gray-500 dark:text-gray-400">SKU: {{ $item->item->sku }}</p>
                                        @endif
                                    </td>
                                    <td class="py-4 text-right text-gray-600 dark:text-gray-400">{{ number_format($item->quantity, 2) }}</td>
                                    <td class="py-4 text-right text-gray-600 dark:text-gray-400">{{ number_format($item->unit_price, 2) }}</td>
                                    <td class="py-4 text-right text-gray-600 dark:text-gray-400">{{ $item->tax_rate }}%</td>
                                    <td class="py-4 text-right font-medium text-gray-900 dark:text-gray-100">{{ number_format($item->total, 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Totals -->
                    <div class="flex justify-end">
                        <div class="w-full md:w-72 space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">Subtotal</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($invoice->subtotal, 2) }}</span>
                            </div>
                            @if($invoice->discount_amount > 0)
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">Discount</span>
                                <span class="font-medium text-red-600 dark:text-red-400">-{{ number_format($invoice->discount_amount, 2) }}</span>
                            </div>
                            @endif
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">Tax</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($invoice->tax_amount, 2) }}</span>
                            </div>
                            <div class="flex justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
                                <span class="text-lg font-bold text-gray-900 dark:text-gray-100">Total</span>
                                <span class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ number_format($invoice->total, 2) }}</span>
                            </div>
                            @if($invoice->balance_due != $invoice->total)
                            <div class="flex justify-between text-sm text-green-600 dark:text-green-400">
                                <span>Amount Paid</span>
                                <span class="font-medium">{{ number_format($invoice->total - $invoice->balance_due, 2) }}</span>
                            </div>
                            <div class="flex justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
                                <span class="font-bold text-gray-900 dark:text-gray-100">Balance Due</span>
                                <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($invoice->balance_due, 2) }}</span>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Notes & Terms -->
                    @if($invoice->notes || $invoice->terms)
                    <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700 grid grid-cols-1 md:grid-cols-2 gap-6">
                        @if($invoice->notes)
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Notes</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $invoice->notes }}</p>
                        </div>
                        @endif
                        @if($invoice->terms)
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Terms & Conditions</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $invoice->terms }}</p>
                        </div>
                        @endif
                    </div>
                    @endif
                </div>
            </div>

            <!-- Payments History -->
            @if($invoice->payments && $invoice->payments->count() > 0)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Payment History</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Method</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Reference</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">WHT deducted</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($invoice->payments as $payment)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $payment->payment_date?->format('M d, Y') ?? 'N/A' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ ucfirst($payment->payment_method) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $payment->reference ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-green-600 dark:text-green-400 text-right font-medium">{{ number_format($payment->amount, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 text-right">{{ (float) $payment->wht_amount > 0 ? number_format($payment->wht_amount, 2) : '-' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            @if(\App\Http\Middleware\EnsureFeatureEnabled::enabled('sms_whatsapp') && $invoice->status !== 'draft' && $invoice->customer)
                @include('invoices._messages')
            @endif

            <!-- Credit notes: raised against this invoice, and credit applied to it -->
            @if(\App\Http\Middleware\EnsureFeatureEnabled::enabled('credit_notes') && ! in_array($invoice->status, ['draft', 'cancelled']))
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Credit notes</h3>
                        @can('create invoices')
                            <a href="{{ route('credit-notes.create', ['invoice_id' => $invoice->id]) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">Create credit note</a>
                        @endcan
                    </div>
                    @forelse($invoice->creditNotes as $note)
                        <p class="text-sm text-gray-700 dark:text-gray-300 flex flex-wrap justify-between gap-3">
                            <span>
                                <a href="{{ route('credit-notes.show', $note) }}" class="text-indigo-600 dark:text-indigo-400">{{ $note->credit_note_number }}</a>
                                · {{ $note->credit_note_date->format('d M Y') }}
                                · <x-status-badge :status="$note->status" :label="$note->status === 'closed' ? 'Used up' : null" />
                                @if($note->restock) · goods returned @endif
                            </span>
                            <span class="font-medium">@money($note->total)</span>
                        </p>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">No credit note has been raised against this invoice.</p>
                    @endforelse
                    @if($invoice->creditNoteApplications->isNotEmpty())
                        <h4 class="pt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Credit applied to this invoice</h4>
                        @foreach($invoice->creditNoteApplications as $application)
                            <p class="text-sm text-gray-700 dark:text-gray-300 flex justify-between gap-3">
                                <span>{{ $application->applied_date->format('d M Y') }} · from credit note <a href="{{ route('credit-notes.show', $application->creditNote) }}" class="text-indigo-600 dark:text-indigo-400">{{ $application->creditNote->credit_note_number }}</a></span>
                                <span class="font-medium">-@money($application->amount)</span>
                            </p>
                        @endforeach
                    @endif
                </div>
            </div>
            @endif

            <!-- Refund History -->
            @if($invoice->refunds && $invoice->refunds->count() > 0)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                        </svg>
                        Refund History
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Refund #</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Method</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Reason</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($invoice->refunds as $refund)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-4 py-3 text-sm">
                                        <a href="{{ route('invoices.refunds.show', $refund) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                                            {{ $refund->refund_number }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $refund->refund_date->format('M d, Y') }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                        {{ \App\Models\InvoiceRefund::METHODS[$refund->refund_method] ?? ucfirst($refund->refund_method) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                        {{ $refund->reason ? (\App\Models\InvoiceRefund::REASONS[$refund->reason] ?? $refund->reason) : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-center">
                                        @php
                                            $refundStatusColors = [
                                                'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300',
                                                'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300',
                                                'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300',
                                            ];
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $refundStatusColors[$refund->status] ?? 'bg-gray-100 text-gray-800' }}">
                                            {{ ucfirst($refund->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-red-600 dark:text-red-400 text-right font-medium">
                                        -{{ number_format($refund->amount, 2) }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <td colspan="5" class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100 text-right">
                                        Total Refunded:
                                    </td>
                                    <td class="px-4 py-3 text-sm font-bold text-red-600 dark:text-red-400 text-right">
                                        -{{ number_format($invoice->total_refunded ?? 0, 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            <!-- Record Payment -->
            @if($invoice->balance_due > 0 && !in_array($invoice->status, ['draft', 'cancelled']))
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Record Payment</h3>
                    <form action="{{ route('payments-received.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        @csrf
                        <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                        <input type="hidden" name="customer_id" value="{{ $invoice->customer_id }}">
                        
                        <div>
                            <label for="amount" class="form-label">Amount</label>
                            <input id="amount" type="number" name="amount" value="{{ $invoice->balance_due }}" min="0.01" max="{{ $invoice->balance_due }}" step="0.01" required
                                class="form-control">
                        </div>
                        
                        <div>
                            <label for="payment_date" class="form-label">Date</label>
                            <input id="payment_date" type="date" name="payment_date" value="{{ date('Y-m-d') }}" required
                                class="form-control">
                        </div>
                        
                        <div>
                            <label for="payment_method" class="form-label">Method</label>
                            <x-searchable-select
                                name="payment_method"
                                :options="['cash' => 'Cash', 'bank_transfer' => 'Bank Transfer', 'check' => 'Check', 'credit_card' => 'Credit Card', 'other' => 'Other']"
                                value="cash"
                                placeholder="Select Method"
                                search-placeholder="Search..."
                                :has-error="false" />
                        </div>
                        
                        <div class="flex items-end">
                            <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 transition">
                                Record Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            @endif

            <!-- Invoice History / Audit Trail -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Invoice History
                    </h3>
                    <div class="flow-root">
                        <ul role="list" class="-mb-8">
                            <!-- Created -->
                            <li>
                                <div class="relative pb-8">
                                    <span class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-700" aria-hidden="true"></span>
                                    <div class="relative flex space-x-3">
                                        <div>
                                            <span class="h-8 w-8 rounded-full bg-green-500 flex items-center justify-center ring-8 ring-white dark:ring-gray-800">
                                                <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                                </svg>
                                            </span>
                                        </div>
                                        <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                            <div>
                                                <p class="text-sm text-gray-900 dark:text-gray-100">Invoice created</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">by {{ $invoice->createdBy?->name ?? 'System' }}</p>
                                            </div>
                                            <div class="whitespace-nowrap text-right text-sm text-gray-500 dark:text-gray-400">
                                                <time datetime="{{ $invoice->created_at }}">{{ $invoice->created_at->format('M d, Y') }}</time>
                                                <p class="text-xs">{{ $invoice->created_at->format('g:i A') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>

                            <!-- Updated (if different from created) -->
                            @if($invoice->updated_at->gt($invoice->created_at->addMinutes(1)))
                            <li>
                                <div class="relative pb-8">
                                    <span class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-700" aria-hidden="true"></span>
                                    <div class="relative flex space-x-3">
                                        <div>
                                            <span class="h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center ring-8 ring-white dark:ring-gray-800">
                                                <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </span>
                                        </div>
                                        <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                            <div>
                                                <p class="text-sm text-gray-900 dark:text-gray-100">Invoice updated</p>
                                            </div>
                                            <div class="whitespace-nowrap text-right text-sm text-gray-500 dark:text-gray-400">
                                                <time datetime="{{ $invoice->updated_at }}">{{ $invoice->updated_at->format('M d, Y') }}</time>
                                                <p class="text-xs">{{ $invoice->updated_at->format('g:i A') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            @endif

                            <!-- Payments -->
                            @foreach($invoice->payments as $payment)
                            <li>
                                <div class="relative pb-8">
                                    @if(!$loop->last || $invoice->released_at)
                                    <span class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-700" aria-hidden="true"></span>
                                    @endif
                                    <div class="relative flex space-x-3">
                                        <div>
                                            <span class="h-8 w-8 rounded-full bg-emerald-500 flex items-center justify-center ring-8 ring-white dark:ring-gray-800">
                                                <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                            </span>
                                        </div>
                                        <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                            <div>
                                                <p class="text-sm text-gray-900 dark:text-gray-100">
                                                    Payment received: <span class="font-semibold text-green-600 dark:text-green-400">{{ number_format($payment->amount, 2) }}</span>
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    via {{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}
                                                    @if($payment->createdBy) • by {{ $payment->createdBy->name }} @endif
                                                </p>
                                            </div>
                                            <div class="whitespace-nowrap text-right text-sm text-gray-500 dark:text-gray-400">
                                                <time datetime="{{ $payment->payment_date }}">{{ $payment->payment_date->format('M d, Y') }}</time>
                                                <p class="text-xs">{{ $payment->created_at->format('g:i A') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            @endforeach

                            <!-- Released -->
                            @if($invoice->released_at)
                            <li>
                                <div class="relative pb-8">
                                    <div class="relative flex space-x-3">
                                        <div>
                                            <span class="h-8 w-8 rounded-full bg-purple-500 flex items-center justify-center ring-8 ring-white dark:ring-gray-800">
                                                <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            </span>
                                        </div>
                                        <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                            <div>
                                                <p class="text-sm text-gray-900 dark:text-gray-100">Invoice released</p>
                                                @if($invoice->waybill_number)
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Waybill: {{ $invoice->waybill_number }}</p>
                                                @endif
                                            </div>
                                            <div class="whitespace-nowrap text-right text-sm text-gray-500 dark:text-gray-400">
                                                <time datetime="{{ $invoice->released_at }}">{{ $invoice->released_at->format('M d, Y') }}</time>
                                                <p class="text-xs">{{ $invoice->released_at->format('g:i A') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Journal Entry / Double-Entry -->
            @if($invoice->journal)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center justify-between">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                            Journal Entry
                        </span>
                        <a href="{{ route('journals.show', $invoice->journal) }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                            {{ $invoice->journal->journal_number }}
                        </a>
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Account</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Debit</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Credit</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($invoice->journal->entries as $entry)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                        <span class="font-mono text-xs text-gray-500 dark:text-gray-400 mr-2">{{ $entry->account->account_code }}</span>
                                        {{ $entry->account->name }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right {{ $entry->debit > 0 ? 'text-gray-900 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400' }}">
                                        {{ $entry->debit > 0 ? number_format($entry->debit, 2) : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right {{ $entry->credit > 0 ? 'text-gray-900 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400' }}">
                                        {{ $entry->credit > 0 ? number_format($entry->credit, 2) : '-' }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 dark:bg-gray-700">
                                <tr class="font-semibold">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">Total</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($invoice->journal->total_debit, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($invoice->journal->total_credit, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        Posted on {{ $invoice->journal->posted_at?->format('M d, Y g:i A') ?? 'Not posted' }}
                    </p>
                </div>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
