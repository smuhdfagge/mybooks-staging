<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Accounting Periods
            </h2>
            <div class="flex gap-2">
                <button type="button" data-open-modal="generate-periods" class="inline-flex items-center px-4 py-2 bg-green-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Generate Year
                </button>
                <a href="{{ route('accounting-periods.create') }}" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Period
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @if($lockDates !== null)
                @include('accounting-periods._lock-dates')
            @endif

            <!-- Period Status Legend -->
            <div class="mb-6 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 flex flex-wrap gap-4 items-center text-sm">
                    <span class="font-medium text-gray-700 dark:text-gray-300">Status Legend:</span>
                    <span class="inline-flex items-center">
                        <span class="w-3 h-3 rounded-full bg-green-500 mr-2"></span>
                        <span class="text-gray-600 dark:text-gray-400">Open - Transactions allowed</span>
                    </span>
                    <span class="inline-flex items-center">
                        <span class="w-3 h-3 rounded-full bg-yellow-500 mr-2"></span>
                        <span class="text-gray-600 dark:text-gray-400">Closed - No changes (can reopen)</span>
                    </span>
                    <span class="inline-flex items-center">
                        <span class="w-3 h-3 rounded-full bg-red-500 mr-2"></span>
                        <span class="text-gray-600 dark:text-gray-400">Locked - Permanently closed</span>
                    </span>
                </div>
            </div>

            <!-- Periods List -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if($periods->isEmpty())
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No accounting periods</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating a new accounting period or generating periods for a fiscal year.</p>
                            <div class="mt-6 flex justify-center gap-3">
                                <button type="button" data-open-modal="generate-periods" class="inline-flex items-center px-4 py-2 bg-green-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700">
                                    Generate Fiscal Year
                                </button>
                                <a href="{{ route('accounting-periods.create') }}" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700">
                                    Create Period
                                </a>
                            </div>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Period</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date Range</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Fiscal Year</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Closed</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($periods as $period)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <a href="{{ route('accounting-periods.show', $period) }}" class="text-brand-600 dark:text-brand-300 hover:underline font-medium">
                                                {{ $period->name }}
                                            </a>
                                            @if($period->is_year_end)
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-accent-100 text-accent-800 dark:bg-accent-900/50 dark:text-accent-200">
                                                    Year End
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ $period->start_date->format('M d, Y') }} - {{ $period->end_date->format('M d, Y') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $period->fiscal_year ?? '-' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($period->status === 'open')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                    <span class="w-2 h-2 rounded-full bg-green-500 mr-1.5"></span>
                                                    Open
                                                </span>
                                            @elseif($period->status === 'closed')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                    <span class="w-2 h-2 rounded-full bg-yellow-500 mr-1.5"></span>
                                                    Closed
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                    <span class="w-2 h-2 rounded-full bg-red-500 mr-1.5"></span>
                                                    Locked
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            @if($period->closed_at)
                                                {{ $period->closed_at->format('M d, Y') }}
                                                @if($period->closedBy)
                                                    <br><span class="text-xs">by {{ $period->closedBy->name }}</span>
                                                @endif
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="{{ route('accounting-periods.show', $period) }}" class="text-brand-600 dark:text-brand-300 hover:text-brand-900 mr-3">View</a>
                                            @if(!$period->isLocked())
                                                <a href="{{ route('accounting-periods.edit', $period) }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900">Edit</a>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Generate Periods Modal -->
    <x-modal name="generate-periods" title="Generate Monthly Periods" maxWidth="md">
        <div class="px-6 pb-5">
            <div class="mt-4">
                <form action="{{ route('accounting-periods.generate') }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label for="fiscal_year" class="form-label">Fiscal Year</label>
                        <input type="number" name="fiscal_year" id="fiscal_year" value="{{ date('Y') }}" min="2000" max="2100" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-brand-500 focus:ring-brand-500" required>
                    </div>
                    <div class="mb-4">
                        <label for="start_month" class="form-label">Fiscal Year Starts In</label>
                        <select name="start_month" id="start_month" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="1">January</option>
                            <option value="2">February</option>
                            <option value="3">March</option>
                            <option value="4">April</option>
                            <option value="5">May</option>
                            <option value="6">June</option>
                            <option value="7">July</option>
                            <option value="8">August</option>
                            <option value="9">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" data-close-modal="generate-periods" class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-green-700 text-white rounded-md hover:bg-green-700">
                            Generate Periods
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </x-modal>
</x-app-layout>
