<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Customer Statement') }}
            </h2>
            <div class="mt-2 sm:mt-0">
                <a href="{{ route('reports.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                    ← Back to Reports
                </a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 sm:p-6">
            <form method="GET" action="{{ route('reports.customer-statement') }}" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Customer Selection -->
                    <div>
                        <label for="customer_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Customer <span class="text-red-500">*</span>
                        </label>
                        <select name="customer_id" id="customer_id" required
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Select Customer</option>
                            @foreach($customers as $cust)
                                <option value="{{ $cust->id }}" {{ request('customer_id') == $cust->id ? 'selected' : '' }}>
                                    {{ $cust->name }}{{ $cust->company_name ? ' (' . $cust->company_name . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Start Date -->
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Start Date
                        </label>
                        <input type="date" name="start_date" id="start_date" 
                               value="{{ $startDate }}"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <!-- End Date -->
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            End Date
                        </label>
                        <input type="date" name="end_date" id="end_date" 
                               value="{{ $endDate }}"
                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <!-- Submit Button -->
                    <div class="flex items-end">
                        <button type="submit" 
                                class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                            Generate Statement
                        </button>
                    </div>
                </div>
            </form>
        </div>

        @if(isset($customer))
        <!-- Statement Header -->
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 sm:p-6">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $customer->name }}</h3>
                    @if($customer->company_name)
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $customer->company_name }}</p>
                    @endif
                    @if($customer->billing_address)
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $customer->billing_address }}</p>
                    @endif
                    @if($customer->city || $customer->state || $customer->postal_code)
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $customer->city }}{{ $customer->state ? ', ' . $customer->state : '' }} {{ $customer->postal_code }}
                        </p>
                    @endif
                    @if($customer->email)
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $customer->email }}</p>
                    @endif
                    @if($customer->phone)
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $customer->phone }}</p>
                    @endif
                </div>
                <div class="mt-4 sm:mt-0 text-left sm:text-right">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Statement Period</p>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">Current Balance</p>
                    <p class="text-lg font-bold {{ $currentBalance > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                        {{ number_format($currentBalance, 2) }}
                    </p>
                </div>
            </div>

            <!-- Export/Print Actions -->
            <div class="flex justify-end space-x-2 mt-4 pt-4 border-t dark:border-gray-700">
                <button onclick="window.print()" 
                        class="px-4 py-2 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600">
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                    </svg>
                    Print
                </button>
            </div>
        </div>

        <!-- Balance Summary -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Opening Balance</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($openingBalance, 2) }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Invoices</p>
                <p class="text-xl font-bold text-blue-600 dark:text-blue-400 mt-1">{{ number_format($totalInvoices, 2) }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Payments</p>
                <p class="text-xl font-bold text-green-600 dark:text-green-400 mt-1">{{ number_format($totalPayments, 2) }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Closing Balance</p>
                <p class="text-xl font-bold {{ $closingBalance > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }} mt-1">
                    {{ number_format($closingBalance, 2) }}
                </p>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Reference</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Due Date</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Charges</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Payments</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @if($openingBalance > 0)
                        <tr class="bg-gray-50 dark:bg-gray-700">
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">Opening</td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">Balance Forward</td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">-</td>
                            <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white">-</td>
                            <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white">-</td>
                            <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-white">{{ number_format($openingBalance, 2) }}</td>
                        </tr>
                        @endif

                        @forelse($transactions as $transaction)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                {{ \Carbon\Carbon::parse($transaction['date'])->format('M d, Y') }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                                    {{ $transaction['type'] === 'invoice' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' }}">
                                    {{ ucfirst($transaction['type']) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if($transaction['type'] === 'invoice')
                                    <a href="{{ route('invoices.show', $transaction['model']->id) }}" 
                                       class="text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ $transaction['reference'] }}
                                    </a>
                                @else
                                    <a href="{{ route('payments-received.show', $transaction['model']->id) }}" 
                                       class="text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ $transaction['reference'] }}
                                    </a>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                @if($transaction['due_date'])
                                    {{ \Carbon\Carbon::parse($transaction['due_date'])->format('M d, Y') }}
                                    @if($transaction['status'] === 'overdue')
                                        <span class="ml-1 text-red-600 dark:text-red-400 font-medium">(Overdue)</span>
                                    @endif
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-right {{ $transaction['debit'] > 0 ? 'font-medium text-gray-900 dark:text-white' : 'text-gray-400' }}">
                                {{ $transaction['debit'] > 0 ? number_format($transaction['debit'], 2) : '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-right {{ $transaction['credit'] > 0 ? 'font-medium text-green-600 dark:text-green-400' : 'text-gray-400' }}">
                                {{ $transaction['credit'] > 0 ? number_format($transaction['credit'], 2) : '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-medium {{ $transaction['balance'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">
                                {{ number_format($transaction['balance'], 2) }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                No transactions found for the selected period.
                            </td>
                        </tr>
                        @endforelse

                        @if($transactions->count() > 0)
                        <tr class="bg-gray-50 dark:bg-gray-700 font-medium">
                            <td colspan="4" class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white">Closing Balance:</td>
                            <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-white">{{ number_format($totalInvoices, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right text-green-600 dark:text-green-400">{{ number_format($totalPayments, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-bold {{ $closingBalance > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">
                                {{ number_format($closingBalance, 2) }}
                            </td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        @if($closingBalance > 0 && $transactions->count() > 0)
        <!-- Outstanding Note -->
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <div>
                    <h4 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Outstanding Balance</h4>
                    <p class="text-sm text-yellow-700 dark:text-yellow-300 mt-1">
                        This customer has an outstanding balance of <span class="font-semibold">{{ number_format($closingBalance, 2) }}</span>. 
                        Please ensure timely payment to avoid overdue charges.
                    </p>
                </div>
            </div>
        </div>
        @endif
        @endif
    </div>

    @push('styles')
    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            .bg-white, .dark\:bg-gray-800 {
                background: white !important;
                color: black !important;
            }
            .text-gray-500, .text-gray-600, .dark\:text-gray-400 {
                color: #666 !important;
            }
            .print-area, .print-area * {
                visibility: visible;
            }
            .print-area {
                position: absolute;
                left: 0;
                top: 0;
            }
            header, .no-print, button {
                display: none !important;
            }
        }
    </style>
    @endpush
</x-app-layout>
