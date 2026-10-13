<x-app-layout>
    <x-slot name="header">
        <x-table.page-header :title="$taxRate->name">
            <x-slot name="badge">
                @if ($taxRate->is_default)<x-status-badge status="sent" label="Default" />@endif
                <x-status-badge :status="$taxRate->is_active ? 'active' : 'inactive'" :label="$taxRate->is_active ? 'Active' : 'Not in use'" />
            </x-slot>
            @can('delete tax-rates')
                <x-slot name="more">
                    <x-table.menu-item danger :post="route('tax-rates.destroy', $taxRate)" method="DELETE" confirm="Delete this?">Delete</x-table.menu-item>
                </x-slot>
            @endcan
            @can('edit tax-rates')
                <x-slot name="actions"><a href="{{ route('tax-rates.edit', $taxRate) }}" class="btn-secondary">Edit</a></x-slot>
            @endcan
        </x-table.page-header>
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
                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">VAT treatment</label>
                    <p class="mt-1 text-lg text-gray-900 dark:text-gray-100">{{ \App\Services\Accounting\VatTreatment::label($taxRate->vat_treatment) }}</p>
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
                            <p class="text-xl font-semibold text-brand-700 dark:text-brand-200">@money($taxRate->calculateTax(100))</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $taxRate->type == 'exclusive' ? 'Total with tax' : 'Net amount' }}</p>
                            <p class="text-xl font-semibold text-green-700 dark:text-green-400">
                                @money($taxRate->type == 'exclusive' ? $taxRate->getGrossAmount(100) : $taxRate->getNetAmount(100))
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
