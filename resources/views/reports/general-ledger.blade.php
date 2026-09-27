<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('General Ledger') }}
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                <x-report-export-buttons 
                    report-type="general-ledger" 
                    :filters="['start_date' => $startDate, 'end_date' => $endDate, 'account_id' => $accountId]" 
                />
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
                <form method="GET" action="{{ route('reports.general-ledger') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div>
                        <label for="account_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Account</label>
                        <select name="account_id" id="account_id" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                            <option value="">Select an account</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" {{ $accountId == $account->id ? 'selected' : '' }}>
                                    {{ $account->account_code }} - {{ $account->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
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
                    <div class="flex items-end">
                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                            </svg>
                            Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @if($selectedAccount)
            <!-- Account Info -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ $selectedAccount->account_code }} - {{ $selectedAccount->name }}
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                Type: {{ ucfirst($selectedAccount->type) }}
                            </p>
                        </div>
                        <div class="text-right">
                            @php
                                $totalDebit = $entries->sum('debit');
                                $totalCredit = $entries->sum('credit');
                                $closingBalance = ($openingBalance ?? 0) + $totalDebit - $totalCredit;
                            @endphp
                            <p class="text-sm text-gray-500 dark:text-gray-400">Closing Balance</p>
                            <p class="text-2xl font-bold {{ $closingBalance >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                {{ number_format(abs($closingBalance), 2) }} {{ $closingBalance >= 0 ? 'Dr' : 'Cr' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ledger Entries Table -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Ledger Entries</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Date
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Journal #
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Description
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Debit
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Credit
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Balance
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <!-- Opening Balance Row -->
                                <tr class="bg-blue-50 dark:bg-blue-900/20">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        -
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-blue-700 dark:text-blue-300">
                                        Opening Balance
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-400 dark:text-gray-500">
                                        -
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-400 dark:text-gray-500">
                                        -
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium {{ ($openingBalance ?? 0) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ number_format(abs($openingBalance ?? 0), 2) }} {{ ($openingBalance ?? 0) >= 0 ? 'Dr' : 'Cr' }}
                                    </td>
                                </tr>
                                @php $runningBalance = $openingBalance ?? 0; @endphp
                                @forelse($entries->sortBy('journal.journal_date') as $entry)
                                    @php
                                        $runningBalance += ($entry->debit - $entry->credit);
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ \Carbon\Carbon::parse($entry->journal->journal_date)->format('M d, Y') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <a href="{{ route('journals.show', $entry->journal) }}" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                                {{ $entry->journal->journal_number }}
                                            </a>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                            {{ $entry->description ?? $entry->journal->description ?? '-' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm {{ $entry->debit > 0 ? 'text-gray-900 dark:text-white font-medium' : 'text-gray-400 dark:text-gray-500' }}">
                                            {{ $entry->debit > 0 ? number_format($entry->debit, 2) : '-' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm {{ $entry->credit > 0 ? 'text-gray-900 dark:text-white font-medium' : 'text-gray-400 dark:text-gray-500' }}">
                                            {{ $entry->credit > 0 ? number_format($entry->credit, 2) : '-' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium {{ $runningBalance >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                            {{ number_format(abs($runningBalance), 2) }} {{ $runningBalance >= 0 ? 'Dr' : 'Cr' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                            No transactions in this period
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <td colspan="3" class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-white">
                                        Total Movements / Closing Balance
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ number_format($totalDebit, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ number_format($totalCredit, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold {{ $closingBalance >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ number_format(abs($closingBalance), 2) }} {{ $closingBalance >= 0 ? 'Dr' : 'Cr' }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Balance Summary -->
                    <div class="mt-4 p-4 bg-gray-100 dark:bg-gray-700 rounded-lg">
                        <div class="grid grid-cols-3 gap-4 text-center">
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Opening Balance</p>
                                <p class="text-lg font-semibold {{ ($openingBalance ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ number_format(abs($openingBalance ?? 0), 2) }} {{ ($openingBalance ?? 0) >= 0 ? 'Dr' : 'Cr' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Net Movement</p>
                                @php $netMovement = $totalDebit - $totalCredit; @endphp
                                <p class="text-lg font-semibold {{ $netMovement >= 0 ? 'text-blue-600' : 'text-orange-600' }}">
                                    {{ $netMovement >= 0 ? '+' : '-' }}{{ number_format(abs($netMovement), 2) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Closing Balance</p>
                                <p class="text-lg font-semibold {{ $closingBalance >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ number_format(abs($closingBalance), 2) }} {{ $closingBalance >= 0 ? 'Dr' : 'Cr' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <!-- No Account Selected -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Select an Account</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Please select an account from the dropdown above to view its ledger entries.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Report Info -->
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Report generated for period: <span class="font-medium text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }}</span> 
                to <span class="font-medium text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}</span>
            </p>
        </div>
    </div>
</x-app-layout>
