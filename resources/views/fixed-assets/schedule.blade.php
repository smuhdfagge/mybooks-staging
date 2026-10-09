<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Depreciation Schedule') }} - {{ $year }}
            </h2>
            <a href="{{ route('fixed-assets.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                Back to Assets
            </a>
        </div>
    </x-slot>

    @php $currency = auth()->user()->tenant->currency_symbol; @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <form method="GET" action="{{ route('fixed-assets.depreciation-schedule') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label for="category_id" class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Category</label>
                            <select name="category_id" id="category_id"
                                class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                <option value="">All Categories</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="status" class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Status</label>
                            <select name="status" id="status"
                                class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                <option value="">All Statuses</option>
                                <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="under_maintenance" {{ request('status') == 'under_maintenance' ? 'selected' : '' }}>Under Maintenance</option>
                                <option value="idle" {{ request('status') == 'idle' ? 'selected' : '' }}>Idle</option>
                            </select>
                        </div>

                        <div>
                            <label for="year" class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Year</label>
                            <select name="year" id="year"
                                class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                @for($y = date('Y') + 5; $y >= date('Y') - 10; $y--)
                                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>

                        <div class="flex items-end">
                            <button type="submit" class="w-full bg-brand-500 hover:bg-brand-700 text-white font-bold py-2 px-4 rounded">
                                Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary Card -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Total Assets</div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ count($scheduleData) }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Total {{ $year }} Depreciation</div>
                            <div class="text-2xl font-bold text-brand-600 dark:text-brand-300">{{ $currency }}{{ number_format($totalYearDepreciation, 2) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Schedule Table -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Asset
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Category
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Cost
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Accumulated
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Book Value
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        {{ $year }} Depreciation
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($scheduleData as $data)
                                    @php $asset = $data['asset']; @endphp
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ $asset->name }}
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ $asset->asset_number }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ $asset->category->name ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-gray-100">
                                            {{ $currency }}{{ number_format($asset->purchase_cost, 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-gray-100">
                                            {{ $currency }}{{ number_format($asset->accumulated_depreciation, 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $currency }}{{ number_format($asset->book_value, 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-gray-100">
                                            {{ $currency }}{{ number_format($data['year_depreciation'], 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                {{ $asset->status === 'active' ? 'bg-green-100 text-green-800' : '' }}
                                                {{ $asset->status === 'under_maintenance' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                                {{ $asset->status === 'idle' ? 'bg-gray-100 text-gray-800' : '' }}
                                                {{ $asset->status === 'disposed' ? 'bg-red-100 text-red-800' : '' }}">
                                                {{ ucfirst(str_replace('_', ' ', $asset->status)) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                            <a href="{{ route('fixed-assets.schedule', $asset) }}" class="text-brand-600 hover:text-brand-900 dark:text-brand-300 dark:hover:text-brand-300">
                                                View Schedule
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                            No assets found matching the selected criteria.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if(count($scheduleData) > 0)
                            <tfoot class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th colspan="2" class="px-6 py-3 text-left text-sm font-bold text-gray-700 dark:text-gray-300">
                                        Totals ({{ count($scheduleData) }} assets)
                                    </th>
                                    <th class="px-6 py-3 text-right text-sm font-bold text-gray-700 dark:text-gray-300">
                                        {{ $currency }}{{ number_format(collect($scheduleData)->sum(fn($d) => $d['asset']->purchase_cost), 2) }}
                                    </th>
                                    <th class="px-6 py-3 text-right text-sm font-bold text-gray-700 dark:text-gray-300">
                                        {{ $currency }}{{ number_format(collect($scheduleData)->sum(fn($d) => $d['asset']->accumulated_depreciation), 2) }}
                                    </th>
                                    <th class="px-6 py-3 text-right text-sm font-bold text-gray-700 dark:text-gray-300">
                                        {{ $currency }}{{ number_format(collect($scheduleData)->sum(fn($d) => $d['asset']->book_value), 2) }}
                                    </th>
                                    <th class="px-6 py-3 text-right text-sm font-bold text-gray-700 dark:text-gray-300">
                                        {{ $currency }}{{ number_format($totalYearDepreciation, 2) }}
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
