<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Back pay preview" description="What changes for past months if the new salary applies from the date you chose. Amounts in ₦." />
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Employee & Summary --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $employee->full_name }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Effective from: {{ \Carbon\Carbon::parse($effectiveFrom)->format('j M Y') }}
                            &bull; {{ count($adjustments) }} payroll periods affected
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Adjustment</p>
                        <p class="text-2xl font-bold {{ $totalAdjustment >= 0 ? 'text-green-700' : 'text-red-600' }}">
                            {{ number_format($totalAdjustment, 2) }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Adjustment Details --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Period Breakdown</h3>
                <x-table caption="Month by month">
<x-slot name="head">
                                <x-table.th>Period</x-table.th>
                                <x-table.th>Payroll #</x-table.th>
                                <x-table.th num>Old gross</x-table.th>
                                <x-table.th num>New gross</x-table.th>
                                <x-table.th num>Old net</x-table.th>
                                <x-table.th num>New net</x-table.th>
                                <x-table.th num>Difference</x-table.th>
</x-slot>

                            @foreach($adjustments as $adj)
                                <tr>
                                    <td>
                                        {{ $adj['payroll']->pay_period_start->format('M Y') }}
                                    </td>
                                    <td>
                                        {{ $adj['payroll']->payroll_number }}
                                    </td>
                                    <td class="num">
                                        @fig($adj['old_gross'], false)
                                    </td>
                                    <td class="num">
                                        @fig($adj['new_gross'], false)
                                    </td>
                                    <td class="num">
                                        @fig($adj['old_net'], false)
                                    </td>
                                    <td class="num">
                                        @fig($adj['new_net'], false)
                                    </td>
                                    <td class="num font-medium {{ $adj['difference'] >= 0 ? 'text-green-700' : 'text-red-600' }}">
                                        {{ $adj['difference'] >= 0 ? '+' : '' }}@fig($adj['difference'], false)
                                    </td>
                                </tr>
                            @endforeach
<x-slot name="foot">
                            <tr>
                                <td colspan="6" class="font-semibold num">
                                    Total Net Adjustment
                                </td>
                                <td class="num font-bold {{ $totalAdjustment >= 0 ? 'text-green-700' : 'text-red-600' }}">
                                    {{ $totalAdjustment >= 0 ? '+' : '' }}@fig($totalAdjustment, false)
                                </td>
                            </tr>
</x-slot>
</x-table>

                @if($totalAdjustment != 0)
                    <div class="mt-6 flex justify-end">
                        <form action="{{ route('payroll.retroactive-adjustment') }}" method="POST" data-confirm="This will create an adjustment payroll record. Continue?">
                            @csrf
                            <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                            <input type="hidden" name="effective_from" value="{{ $effectiveFrom }}">
                            <input type="hidden" name="recalculate" value="1">
                            <button type="submit" class="btn-primary">
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
