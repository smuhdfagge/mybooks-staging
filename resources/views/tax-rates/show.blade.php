<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('tax-rates.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ $taxRate->name }}</h2>
                    <div class="flex items-center gap-2 mt-1">
                        @if($taxRate->is_default)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Default</span>
                        @endif
                        @if($taxRate->is_active)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Active</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Inactive</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex gap-2">
                @can('edit tax-rates')
                <a href="{{ route('tax-rates.edit', $taxRate) }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Edit
                </a>
                @endcan
                @can('delete tax-rates')
                <form action="{{ route('tax-rates.destroy', $taxRate) }}" method="POST" data-confirm="Are you sure?">
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
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Code</label>
                    <p class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $taxRate->code ?? '-' }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Rate</label>
                    <p class="mt-1 text-lg text-gray-900 dark:text-gray-100 font-semibold">{{ $taxRate->formatted_rate }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Type</label>
                    <p class="mt-1 text-lg text-gray-900 dark:text-gray-100 capitalize">{{ $taxRate->type }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Applies To</label>
                    <p class="mt-1 text-lg text-gray-900 dark:text-gray-100 capitalize">
                        {{ $taxRate->applies_to == 'both' ? 'Sales & Purchases' : ucfirst($taxRate->applies_to) . ' Only' }}
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Tax Registration Number</label>
                    <p class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $taxRate->tax_number ?? '-' }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Compound Tax</label>
                    <p class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ $taxRate->is_compound ? 'Yes' : 'No' }}</p>
                </div>
            </div>

            @if($taxRate->description)
                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Description</label>
                    <p class="mt-1 text-gray-900 dark:text-gray-100">{{ $taxRate->description }}</p>
                </div>
            @endif

            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Tax Calculation Example</h3>
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">If item price is</p>
                            <p class="text-xl font-semibold text-gray-900 dark:text-gray-100">$100.00</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Tax amount ({{ $taxRate->formatted_rate }})</p>
                            <p class="text-xl font-semibold text-blue-600 dark:text-blue-400">${{ number_format($taxRate->calculateTax(100), 2) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $taxRate->type == 'exclusive' ? 'Total with tax' : 'Net amount' }}</p>
                            <p class="text-xl font-semibold text-green-600 dark:text-green-400">
                                ${{ number_format($taxRate->type == 'exclusive' ? $taxRate->getGrossAmount(100) : $taxRate->getNetAmount(100), 2) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Created: {{ $taxRate->created_at->format('M d, Y h:i A') }} • Last Updated: {{ $taxRate->updated_at->format('M d, Y h:i A') }}
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
