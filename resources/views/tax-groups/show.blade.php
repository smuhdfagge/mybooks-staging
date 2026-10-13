<x-app-layout>
    <x-slot name="header">
        <x-table.page-header :title="$taxGroup->name">
            <x-slot name="badge">
                @if ($taxGroup->is_default)<x-status-badge status="sent" label="Default" />@endif
                <x-status-badge :status="$taxGroup->is_active ? 'active' : 'inactive'" :label="$taxGroup->is_active ? 'Active' : 'Not in use'" />
            </x-slot>
            @can('delete tax-rates')
                <x-slot name="more">
                    <x-table.menu-item danger :post="route('tax-groups.destroy', $taxGroup)" method="DELETE" confirm="Delete this?">Delete</x-table.menu-item>
                </x-slot>
            @endcan
            @can('edit tax-rates')
                <x-slot name="actions"><a href="{{ route('tax-groups.edit', $taxGroup) }}" class="btn-secondary">Edit</a></x-slot>
            @endcan
        </x-table.page-header>
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
                    <x-table caption="Tax rates in this group">
<x-slot name="head">
                                <x-table.th>#</x-table.th>
                                <x-table.th>Name</x-table.th>
                                <x-table.th>Rate</x-table.th>
                                <x-table.th>Type</x-table.th>
                                <x-table.th>Compound</x-table.th>
</x-slot>

                            @forelse($taxGroup->taxRates as $index => $taxRate)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <a href="{{ route('tax-rates.show', $taxRate) }}" class="tbl-link">
                                            {{ $taxRate->name }}
                                            @if($taxRate->code) <span class="tbl-muted">({{ $taxRate->code }})</span> @endif
                                        </a>
                                    </td>
                                    <td class="font-medium">{{ $taxRate->formatted_rate }}</td>
                                    <td class="capitalize">{{ $taxRate->type }}</td>
                                    <td>
                                        @if($taxRate->is_compound)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-accent-100 text-accent-800 dark:bg-accent-900/50 dark:text-accent-200">Yes</span>
                                        @else
                                            <span class="text-gray-500 dark:text-gray-400 text-sm">No</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center">No tax rates in this group</td>
                                </tr>
                            @endforelse
</x-table>
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
                            <span class="font-semibold text-brand-700 dark:text-brand-200">@money($totalTax)</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-semibold text-gray-900 dark:text-gray-100">Grand Total</span>
                            <span class="font-semibold text-green-700 dark:text-green-400">@money(100 + $totalTax)</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Created: {{ $taxGroup->created_at->format('j M Y, H:i') }} • Last Updated: {{ $taxGroup->updated_at->format('j M Y, H:i') }}
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
