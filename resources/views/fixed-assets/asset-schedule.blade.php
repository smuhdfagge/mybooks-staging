<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Depreciation Schedule') }} - {{ $fixedAsset->name }}
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('fixed-assets.show', $fixedAsset) }}" class="bg-brand-500 hover:bg-brand-700 text-white font-bold py-2 px-4 rounded">
                    View Asset
                </a>
                <a href="{{ route('fixed-assets.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Back to Assets
                </a>
            </div>
        </div>
    </x-slot>

    @php $currency = auth()->user()->tenant->currency_symbol; @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Asset Summary -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Asset Number</div>
                            <div class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $fixedAsset->asset_number }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Purchase Cost</div>
                            <div class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $currency }}{{ number_format($fixedAsset->purchase_cost, 2) }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Salvage Value</div>
                            <div class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $currency }}{{ number_format($fixedAsset->salvage_value, 2) }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Accumulated Depreciation</div>
                            <div class="text-lg font-bold text-red-600 dark:text-red-300">{{ $currency }}{{ number_format($fixedAsset->accumulated_depreciation, 2) }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Book Value</div>
                            <div class="text-lg font-bold text-brand-600 dark:text-brand-300">{{ $currency }}{{ number_format($fixedAsset->book_value, 2) }}</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Purchase Date</div>
                            <div class="text-md text-gray-900 dark:text-gray-100">{{ $fixedAsset->purchase_date->format('M d, Y') }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Depreciation Start</div>
                            <div class="text-md text-gray-900 dark:text-gray-100">{{ $fixedAsset->depreciation_start_date->format('M d, Y') }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Useful Life</div>
                            <div class="text-md text-gray-900 dark:text-gray-100">{{ $fixedAsset->useful_life }} years</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Depreciation Method</div>
                            <div class="text-md text-gray-900 dark:text-gray-100">{{ ucwords(str_replace('_', ' ', $fixedAsset->depreciation_method)) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recorded Depreciation -->
            @if($depreciations->count() > 0)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Recorded Depreciation</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Period
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Date
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Depreciation Amount
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Accumulated
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Book Value
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Journal
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($depreciations as $depreciation)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ $depreciation->period_number }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ $depreciation->depreciation_date->format('M d, Y') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-gray-100">
                                            {{ $currency }}{{ number_format($depreciation->depreciation_amount, 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-gray-100">
                                            {{ $currency }}{{ number_format($depreciation->accumulated_depreciation, 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $currency }}{{ number_format($depreciation->book_value, 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                            @if($depreciation->journal_id)
                                                <a href="{{ route('journals.show', $depreciation->journal_id) }}" class="text-brand-600 hover:text-brand-900 dark:text-brand-300">
                                                    View
                                                </a>
                                            @else
                                                <span class="text-gray-500 dark:text-gray-400">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            <!-- Projected Schedule -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Projected Depreciation Schedule</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Period
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Date
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Opening Value
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Depreciation
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Accumulated
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Closing Value
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($schedule as $period)
                                    <tr class="{{ $period['period'] <= $depreciations->count() ? 'bg-green-50 dark:bg-green-900/20' : '' }}">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ $period['period'] }}
                                            @if($period['period'] <= $depreciations->count())
                                                <span class="ml-2 text-xs text-green-700 dark:text-green-400">(Recorded)</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ \Carbon\Carbon::parse($period['date'])->format('M d, Y') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-gray-100">
                                            {{ $currency }}{{ number_format($period['opening_value'], 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-gray-100">
                                            {{ $currency }}{{ number_format($period['depreciation'], 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-gray-100">
                                            {{ $currency }}{{ number_format($period['accumulated'], 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $currency }}{{ number_format($period['closing_value'], 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                            No depreciation schedule available.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if(count($schedule) > 0)
                            <tfoot class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th colspan="3" class="px-6 py-3 text-left text-sm font-bold text-gray-700 dark:text-gray-300">
                                        Total Depreciation
                                    </th>
                                    <th class="px-6 py-3 text-right text-sm font-bold text-gray-700 dark:text-gray-300">
                                        {{ $currency }}{{ number_format(collect($schedule)->sum('depreciation'), 2) }}
                                    </th>
                                    <th colspan="2"></th>
                                </tr>
                            </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
