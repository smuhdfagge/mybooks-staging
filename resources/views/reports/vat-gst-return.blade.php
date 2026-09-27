<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('VAT/GST Return') }}
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('reports.index') }}" class="inline-flex items-center justify-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Reports
                </a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <form method="GET" action="{{ route('reports.vat-gst-return') }}" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Start Date</label>
                            <input type="date" name="start_date" id="start_date" value="{{ $startDate }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                        </div>
                        <div>
                            <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">End Date</label>
                            <input type="date" name="end_date" id="end_date" value="{{ $endDate }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                        </div>
                        <div>
                            <label for="tax_rate_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tax Rate</label>
                            <select name="tax_rate_id" id="tax_rate_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                                <option value="">All Tax Rates</option>
                                @foreach($taxRates as $rate)
                                    <option value="{{ $rate->id }}" {{ $taxRateId == $rate->id ? 'selected' : '' }}>
                                        {{ $rate->name }} ({{ $rate->formatted_rate }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                                </svg>
                                Generate Report
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Output Tax (Sales) -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Output Tax (Sales)</p>
                            <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ number_format($totalOutputTax, 2) }}</p>
                        </div>
                        <div class="p-3 bg-red-100 dark:bg-red-900/30 rounded-full">
                            <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Taxable Sales: {{ number_format($totalOutputTaxable, 2) }}
                    </p>
                </div>
            </div>

            <!-- Input Tax (Purchases) -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Input Tax (Purchases)</p>
                            <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format($totalInputTax, 2) }}</p>
                        </div>
                        <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-full">
                            <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Taxable Purchases: {{ number_format($totalInputTaxable, 2) }}
                    </p>
                </div>
            </div>

            <!-- Net VAT/GST -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                Net VAT/GST {{ $netTaxPayable >= 0 ? 'Payable' : 'Refundable' }}
                            </p>
                            <p class="text-2xl font-bold {{ $netTaxPayable >= 0 ? 'text-orange-600 dark:text-orange-400' : 'text-blue-600 dark:text-blue-400' }}">
                                {{ number_format(abs($netTaxPayable), 2) }}
                            </p>
                        </div>
                        <div class="p-3 {{ $netTaxPayable >= 0 ? 'bg-orange-100 dark:bg-orange-900/30' : 'bg-blue-100 dark:bg-blue-900/30' }} rounded-full">
                            <svg class="w-6 h-6 {{ $netTaxPayable >= 0 ? 'text-orange-600 dark:text-orange-400' : 'text-blue-600 dark:text-blue-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Output Tax - Input Tax
                    </p>
                </div>
            </div>
        </div>

        <!-- Tax by Rate Breakdown -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Output Tax by Rate -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Output Tax by Rate</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tax Rate</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Taxable Amount</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tax Amount</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Invoices</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($outputTaxByRate as $row)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ number_format($row->tax_rate, 2) }}%</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">{{ number_format($row->taxable_amount, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-medium text-red-600 dark:text-red-400">{{ number_format($row->tax_amount, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-center text-gray-600 dark:text-gray-400">{{ $row->transaction_count }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-3 text-sm text-center text-gray-500 dark:text-gray-400">No output tax data</td>
                                </tr>
                                @endforelse
                            </tbody>
                            @if($outputTaxByRate->count() > 0)
                            <tfoot class="bg-gray-100 dark:bg-gray-700">
                                <tr>
                                    <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-white">Total</td>
                                    <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900 dark:text-white">{{ number_format($totalOutputTaxable, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-semibold text-red-600 dark:text-red-400">{{ number_format($totalOutputTax, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-center font-semibold text-gray-900 dark:text-white">{{ $outputTaxByRate->sum('transaction_count') }}</td>
                                </tr>
                            </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>

            <!-- Input Tax by Rate -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Input Tax by Rate</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tax Rate</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Taxable Amount</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tax Amount</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Bills</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($inputTaxByRate as $row)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ number_format($row->tax_rate, 2) }}%</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">{{ number_format($row->taxable_amount, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-medium text-green-600 dark:text-green-400">{{ number_format($row->tax_amount, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-center text-gray-600 dark:text-gray-400">{{ $row->transaction_count }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-3 text-sm text-center text-gray-500 dark:text-gray-400">No input tax data</td>
                                </tr>
                                @endforelse
                            </tbody>
                            @if($inputTaxByRate->count() > 0)
                            <tfoot class="bg-gray-100 dark:bg-gray-700">
                                <tr>
                                    <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-white">Total</td>
                                    <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900 dark:text-white">{{ number_format($totalInputTaxable, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-semibold text-green-600 dark:text-green-400">{{ number_format($totalInputTax, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-center font-semibold text-gray-900 dark:text-white">{{ $inputTaxByRate->sum('transaction_count') }}</td>
                                </tr>
                            </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- VAT/GST Calculation Summary -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">VAT/GST Calculation Summary</h3>
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="text-center p-4 border border-gray-200 dark:border-gray-600 rounded-lg">
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Output Tax</p>
                            <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ number_format($totalOutputTax, 2) }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Tax charged on sales</p>
                        </div>
                        <div class="text-center p-4 border border-gray-200 dark:border-gray-600 rounded-lg">
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Input Tax</p>
                            <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format($totalInputTax, 2) }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Tax paid on purchases</p>
                        </div>
                        <div class="text-center p-4 border-2 {{ $netTaxPayable >= 0 ? 'border-orange-300 dark:border-orange-600 bg-orange-50 dark:bg-orange-900/20' : 'border-blue-300 dark:border-blue-600 bg-blue-50 dark:bg-blue-900/20' }} rounded-lg">
                            <p class="text-sm {{ $netTaxPayable >= 0 ? 'text-orange-600 dark:text-orange-400' : 'text-blue-600 dark:text-blue-400' }} mb-2">
                                {{ $netTaxPayable >= 0 ? 'Tax Payable' : 'Tax Refundable' }}
                            </p>
                            <p class="text-2xl font-bold {{ $netTaxPayable >= 0 ? 'text-orange-600 dark:text-orange-400' : 'text-blue-600 dark:text-blue-400' }}">
                                {{ number_format(abs($netTaxPayable), 2) }}
                            </p>
                            <p class="text-xs {{ $netTaxPayable >= 0 ? 'text-orange-500 dark:text-orange-400' : 'text-blue-500 dark:text-blue-400' }} mt-1">
                                
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Transactions -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Output Tax Transactions (Sales)</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Invoice #</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Customer</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Subtotal</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tax</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($outputTransactions->take(10) as $invoice)
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $invoice->invoice_date->format('M d, Y') }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <a href="{{ route('invoices.show', $invoice) }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ $invoice->invoice_number }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $invoice->customer->name ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">{{ number_format($invoice->subtotal, 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-medium text-red-600 dark:text-red-400">{{ number_format($invoice->tax_amount, 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ number_format($invoice->total, 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="px-4 py-3 text-sm text-center text-gray-500 dark:text-gray-400">No taxable invoices in this period</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                    @if($outputTransactions->count() > 10)
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400 text-center">Showing 10 of {{ $outputTransactions->count() }} transactions</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Input Tax Transactions (Purchases)</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Bill #</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Vendor</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Subtotal</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tax</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($inputTransactions->take(10) as $bill)
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $bill->bill_date->format('M d, Y') }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <a href="{{ route('bills.show', $bill) }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ $bill->bill_number }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $bill->vendor->name ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">{{ number_format($bill->subtotal, 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-medium text-green-600 dark:text-green-400">{{ number_format($bill->tax_amount, 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ number_format($bill->total, 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="px-4 py-3 text-sm text-center text-gray-500 dark:text-gray-400">No taxable bills in this period</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                    @if($inputTransactions->count() > 10)
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400 text-center">Showing 10 of {{ $inputTransactions->count() }} transactions</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Report Info -->
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Report Period: <span class="font-medium text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }}</span>
                to <span class="font-medium text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}</span>
            </p>
        </div>
    </div>
</x-app-layout>
