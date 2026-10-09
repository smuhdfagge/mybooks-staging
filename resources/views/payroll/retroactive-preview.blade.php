<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Retroactive Pay Adjustment Preview') }}
            </h2>
            <a href="{{ route('payroll.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                &larr; Back to Payroll
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Employee & Summary --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $employee->full_name }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Effective from: {{ \Carbon\Carbon::parse($effectiveFrom)->format('M d, Y') }}
                            &bull; {{ count($adjustments) }} payroll periods affected
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Adjustment</p>
                        <p class="text-2xl font-bold {{ $totalAdjustment >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ number_format($totalAdjustment, 2) }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Adjustment Details --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Period Breakdown</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Period</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Payroll #</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Old Gross</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">New Gross</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Old Net</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">New Net</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Difference</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($adjustments as $adj)
                                <tr>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                        {{ $adj['payroll']->pay_period_start->format('M Y') }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                        {{ $adj['payroll']->payroll_number }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">
                                        {{ number_format($adj['old_gross'], 2) }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">
                                        {{ number_format($adj['new_gross'], 2) }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">
                                        {{ number_format($adj['old_net'], 2) }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">
                                        {{ number_format($adj['new_net'], 2) }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-right font-medium {{ $adj['difference'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $adj['difference'] >= 0 ? '+' : '' }}{{ number_format($adj['difference'], 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-50 dark:bg-gray-700">
                                <td colspan="6" class="px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-100 text-right">
                                    Total Net Adjustment
                                </td>
                                <td class="px-4 py-2 text-sm text-right font-bold {{ $totalAdjustment >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $totalAdjustment >= 0 ? '+' : '' }}{{ number_format($totalAdjustment, 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                @if($totalAdjustment != 0)
                    <div class="mt-6 flex justify-end">
                        <form action="{{ route('payroll.retroactive-adjustment') }}" method="POST" data-confirm="This will create an adjustment payroll record. Continue?">
                            @csrf
                            <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                            <input type="hidden" name="effective_from" value="{{ $effectiveFrom }}">
                            <input type="hidden" name="recalculate" value="1">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 transition">
                                Create Adjustment Payroll ({{ $totalAdjustment >= 0 ? '+' : '' }}{{ number_format($totalAdjustment, 2) }})
                            </button>
                        </form>
                    </div>
                @else
                    <div class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                        No adjustment needed — current salary structure matches the payroll records.
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
