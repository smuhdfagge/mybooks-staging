<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('tax-groups.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ $taxGroup->name }}</h2>
                    <div class="flex items-center gap-2 mt-1">
                        @if($taxGroup->is_default)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-brand-100 text-brand-800 dark:bg-brand-900 dark:text-brand-200">Default</span>
                        @endif
                        @if($taxGroup->is_active)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Active</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Inactive</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex gap-2">
                @can('edit tax-rates')
                <a href="{{ route('tax-groups.edit', $taxGroup) }}" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Edit
                </a>
                @endcan
                @can('delete tax-rates')
                <form action="{{ route('tax-groups.destroy', $taxGroup) }}" method="POST" data-confirm="Are you sure?">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        Delete
                    </button>
                </form>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Code</label>
                    <p class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $taxGroup->code ?? '-' }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Combined Rate</label>
                    <p class="mt-1 text-lg text-gray-900 dark:text-gray-100 font-semibold text-brand-600 dark:text-brand-300">{{ $taxGroup->formatted_rate }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Number of Taxes</label>
                    <p class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $taxGroup->taxRates->count() }}</p>
                </div>
            </div>

            @if($taxGroup->description)
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Description</label>
                    <p class="mt-1 text-gray-900 dark:text-gray-100">{{ $taxGroup->description }}</p>
                </div>
            @endif

            <!-- Tax Rates in Group -->
            <div class="mt-8">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Included Tax Rates</h3>
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">#</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Rate</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Compound</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($taxGroup->taxRates as $index => $taxRate)
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                                    <td class="px-6 py-4">
                                        <a href="{{ route('tax-rates.show', $taxRate) }}" class="text-sm font-medium text-gray-900 dark:text-gray-100 hover:text-brand-600 dark:hover:text-brand-300">
                                            {{ $taxRate->name }}
                                            @if($taxRate->code) <span class="text-gray-500">({{ $taxRate->code }})</span> @endif
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-semibold text-brand-600 dark:text-brand-300">{{ $taxRate->formatted_rate }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 capitalize">{{ $taxRate->type }}</td>
                                    <td class="px-6 py-4">
                                        @if($taxRate->is_compound)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-accent-100 text-accent-800 dark:bg-accent-900/50 dark:text-accent-200">Yes</span>
                                        @else
                                            <span class="text-gray-500 dark:text-gray-400 text-sm">No</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">No tax rates in this group</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tax Calculation Example -->
            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Tax Calculation Example</h3>
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">For an item priced at <span class="font-semibold text-gray-900 dark:text-gray-100">$100.00</span>:</p>
                    
                    @php
                        $taxes = $taxGroup->calculateTaxes(100);
                        $totalTax = array_sum(array_column($taxes, 'amount'));
                    @endphp

                    <div class="space-y-2">
                        @foreach($taxes as $tax)
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-700 dark:text-gray-300">
                                    {{ $tax['name'] }} ({{ rtrim(rtrim(number_format($tax['rate'], 4), '0'), '.') }}%)
                                    @if($tax['is_compound']) <span class="text-accent-700 dark:text-accent-300 text-xs">(compound)</span> @endif
                                </span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">@money($tax['amount'])</span>
                            </div>
                        @endforeach
                        
                        <div class="pt-2 border-t border-gray-300 dark:border-gray-600 flex justify-between">
                            <span class="font-semibold text-gray-900 dark:text-gray-100">Total Tax</span>
                            <span class="font-semibold text-brand-600 dark:text-brand-300">@money($totalTax)</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-semibold text-gray-900 dark:text-gray-100">Grand Total</span>
                            <span class="font-semibold text-green-600 dark:text-green-400">@money(100 + $totalTax)</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Created: {{ $taxGroup->created_at->format('M d, Y h:i A') }} • Last Updated: {{ $taxGroup->updated_at->format('M d, Y h:i A') }}
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
