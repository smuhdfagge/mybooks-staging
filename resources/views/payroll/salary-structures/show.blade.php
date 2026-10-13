<x-app-layout>
    <x-slot name="header">
        <x-table.page-header>
            <x-slot name="title">Salary structure: {{ $salaryStructure->name }}</x-slot>
            <x-slot name="actions">
                <a href="{{ route('salary-structures.edit', $salaryStructure) }}" class="btn-secondary"> Edit </a>
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Basic Salary</div>
                    <div class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($salaryStructure->basic_salary, 2) }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Allowances</div>
                    <div class="text-xl font-bold text-green-700 dark:text-green-400 mt-1">+{{ number_format($salaryStructure->total_allowances, 2) }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Deductions</div>
                    <div class="text-xl font-bold text-red-600 dark:text-red-300 mt-1">-{{ number_format($salaryStructure->total_deductions, 2) }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Net Salary</div>
                    <div class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($salaryStructure->net_salary, 2) }}</div>
                </div>
            </div>

            <!-- Structure Details -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Structure Details</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Name</span>
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $salaryStructure->name }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Effective From</span>
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $salaryStructure->effective_from->format('j M Y') }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Version</span>
                        <p>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-brand-100 text-brand-800 dark:bg-brand-800 dark:text-brand-100">v{{ $salaryStructure->version ?? 1 }}</span>
                        </p>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Status</span>
                        <p>
                            @if($salaryStructure->is_active)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">Active</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Inactive</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            <!-- Allowances Breakdown -->
            @if($salaryStructure->allowances->count() > 0)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    Allowances
                </h3>
                <x-table caption="Allowances">
<x-slot name="head">
                                <x-table.th>Name</x-table.th>
                                <x-table.th>Type</x-table.th>
                                <x-table.th num>Rate/Amount</x-table.th>
                                <x-table.th num>Calculated</x-table.th>
                                <x-table.th class="text-center">Taxable</x-table.th>
</x-slot>

                            @foreach($salaryStructure->allowances as $item)
                            <tr>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->amount_type === 'percentage' ? '% of Basic' : 'Fixed' }}</td>
                                <td class="num">
                                    {{ $item->amount_type === 'percentage' ? $item->amount . '%' : number_format($item->amount, 2) }}
                                </td>
                                <td class="num font-medium text-green-700 dark:text-green-400">
                                    @fig($item->calculated_amount, false)
                                </td>
                                <td class="text-center">
                                    @if($item->is_taxable)
                                        <span class="text-yellow-700 dark:text-yellow-400">Yes</span>
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400">No</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
<x-slot name="foot">
                            <tr>
                                <td colspan="3" class="font-medium num">Total Allowances</td>
                                <td class="font-bold num text-green-700 dark:text-green-400">@fig($salaryStructure->total_allowances, false)</td>
                                <td></td>
                            </tr>
</x-slot>
</x-table>
            </div>
            @endif

            <!-- Deductions Breakdown -->
            @if($salaryStructure->deductions->count() > 0)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                    Deductions
                </h3>
                <x-table caption="Deductions">
<x-slot name="head">
                                <x-table.th>Name</x-table.th>
                                <x-table.th>Type</x-table.th>
                                <x-table.th num>Rate/Amount</x-table.th>
                                <x-table.th num>Calculated</x-table.th>
</x-slot>

                            @foreach($salaryStructure->deductions as $item)
                            <tr>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->amount_type === 'percentage' ? '% of Gross' : 'Fixed' }}</td>
                                <td class="num">
                                    {{ $item->amount_type === 'percentage' ? $item->amount . '%' : number_format($item->amount, 2) }}
                                </td>
                                <td class="num font-medium text-red-600 dark:text-red-300">
                                    @fig($item->calculated_amount, false)
                                </td>
                            </tr>
                            @endforeach
<x-slot name="foot">
                            <tr>
                                <td colspan="3" class="font-medium num">Total Deductions</td>
                                <td class="font-bold num text-red-600 dark:text-red-300">@fig($salaryStructure->total_deductions, false)</td>
                            </tr>
</x-slot>
</x-table>
            </div>
            @endif

            <!-- Notes -->
            @if($salaryStructure->notes)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Notes</h3>
                <p class="text-sm text-gray-700 dark:text-gray-300">{{ $salaryStructure->notes }}</p>
            </div>
            @endif

            <!-- Version History -->
            @if($salaryStructure->versions->count() > 0)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-brand-500 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Version History
                </h3>
                <div class="space-y-4">
                    @foreach($salaryStructure->versions as $version)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4" x-data="{ open: false }">
                        <div class="flex items-center justify-between cursor-pointer" role="button" tabindex="0" :aria-expanded="open.toString()" @click="open = !open" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">v{{ $version->version }}</span>
                                <span class="text-sm text-gray-900 dark:text-gray-100">{{ $version->name }}</span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">— Basic: {{ number_format($version->basic_salary, 2) }}</span>
                            </div>
                            <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                @if($version->change_reason)
                                    <span class="italic">{{ Str::limit($version->change_reason, 40) }}</span>
                                @endif
                                <span>{{ $version->changedByUser?->name ?? 'System' }}</span>
                                <span>{{ $version->created_at->format('j M Y, H:i') }}</span>
                                <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </div>
                        </div>
                        <div x-show="open" x-cloak class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                            @if(!empty($version->items))
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    @php
                                        $vAllowances = collect($version->items)->where('type', 'allowance');
                                        $vDeductions = collect($version->items)->where('type', 'deduction');
                                    @endphp
                                    @if($vAllowances->count())
                                    <div>
                                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Allowances</p>
                                        @foreach($vAllowances as $item)
                                        <div class="flex justify-between text-sm text-gray-700 dark:text-gray-300">
                                            <span>{{ $item['name'] }}</span>
                                            <span>{{ $item['amount_type'] === 'percentage' ? $item['amount'] . '%' : number_format($item['amount'], 2) }}</span>
                                        </div>
                                        @endforeach
                                    </div>
                                    @endif
                                    @if($vDeductions->count())
                                    <div>
                                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Deductions</p>
                                        @foreach($vDeductions as $item)
                                        <div class="flex justify-between text-sm text-gray-700 dark:text-gray-300">
                                            <span>{{ $item['name'] }}</span>
                                            <span>{{ $item['amount_type'] === 'percentage' ? $item['amount'] . '%' : number_format($item['amount'], 2) }}</span>
                                        </div>
                                        @endforeach
                                    </div>
                                    @endif
                                </div>
                            @else
                                <p class="text-sm text-gray-500 dark:text-gray-400 italic">No item details recorded.</p>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
