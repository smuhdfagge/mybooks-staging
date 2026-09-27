<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Salary Structure - {{ $salaryStructure->name }}
            </h2>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('salary-structures.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to List
                </a>
                <a href="{{ route('salary-structures.edit', $salaryStructure) }}" class="inline-flex items-center px-4 py-2 bg-yellow-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-600 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Edit
                </a>
            </div>
        </div>
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
                    <div class="text-xl font-bold text-green-600 dark:text-green-400 mt-1">+{{ number_format($salaryStructure->total_allowances, 2) }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Deductions</div>
                    <div class="text-xl font-bold text-red-600 dark:text-red-400 mt-1">-{{ number_format($salaryStructure->total_deductions, 2) }}</div>
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
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $salaryStructure->effective_from->format('M d, Y') }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Version</span>
                        <p>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100">v{{ $salaryStructure->version ?? 1 }}</span>
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
                    <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    Allowances
                </h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Name</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Type</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Rate/Amount</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Calculated</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Taxable</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($salaryStructure->allowances as $item)
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $item->name }}</td>
                                <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $item->amount_type === 'percentage' ? '% of Basic' : 'Fixed' }}</td>
                                <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">
                                    {{ $item->amount_type === 'percentage' ? $item->amount . '%' : number_format($item->amount, 2) }}
                                </td>
                                <td class="px-4 py-2 text-sm text-right font-medium text-green-600 dark:text-green-400">
                                    {{ number_format($item->calculated_amount, 2) }}
                                </td>
                                <td class="px-4 py-2 text-sm text-center">
                                    @if($item->is_taxable)
                                        <span class="text-yellow-600 dark:text-yellow-400">Yes</span>
                                    @else
                                        <span class="text-gray-400">No</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-50 dark:bg-gray-700/50">
                                <td colspan="3" class="px-4 py-2 text-sm font-medium text-gray-900 dark:text-gray-100 text-right">Total Allowances</td>
                                <td class="px-4 py-2 text-sm font-bold text-right text-green-600 dark:text-green-400">{{ number_format($salaryStructure->total_allowances, 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            @endif

            <!-- Deductions Breakdown -->
            @if($salaryStructure->deductions->count() > 0)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                    Deductions
                </h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Name</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Type</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Rate/Amount</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Calculated</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($salaryStructure->deductions as $item)
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $item->name }}</td>
                                <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $item->amount_type === 'percentage' ? '% of Gross' : 'Fixed' }}</td>
                                <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">
                                    {{ $item->amount_type === 'percentage' ? $item->amount . '%' : number_format($item->amount, 2) }}
                                </td>
                                <td class="px-4 py-2 text-sm text-right font-medium text-red-600 dark:text-red-400">
                                    {{ number_format($item->calculated_amount, 2) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-50 dark:bg-gray-700/50">
                                <td colspan="3" class="px-4 py-2 text-sm font-medium text-gray-900 dark:text-gray-100 text-right">Total Deductions</td>
                                <td class="px-4 py-2 text-sm font-bold text-right text-red-600 dark:text-red-400">{{ number_format($salaryStructure->total_deductions, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
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
                    <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Version History
                </h3>
                <div class="space-y-4">
                    @foreach($salaryStructure->versions as $version)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4" x-data="{ open: false }">
                        <div class="flex items-center justify-between cursor-pointer" @click="open = !open">
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
                                <span>{{ $version->created_at->format('M d, Y H:i') }}</span>
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
