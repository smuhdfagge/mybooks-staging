<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ $data['title'] }} &middot; {{ $month->format('F Y') }}</h2>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('payroll.statutory.export', ['schedule' => $data['schedule'], 'month' => $month->format('Y-m'), 'format' => 'pdf']) }}" class="inline-flex items-center px-3 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700">PDF</a>
                <a href="{{ route('payroll.statutory.export', ['schedule' => $data['schedule'], 'month' => $month->format('Y-m'), 'format' => 'csv']) }}" class="inline-flex items-center px-3 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700">CSV</a>
                <a href="{{ route('payroll.statutory.index', ['month' => $month->format('Y-m')]) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">&larr; All schedules</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-card class="p-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Schedule total</p>
                    <p class="text-xl font-semibold text-gray-900 dark:text-gray-100">@money($data['total'])</p>
                </x-card>
                <x-card class="p-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Ledger: {{ $data['ledger']['account_code'] }} {{ $data['ledger']['account_name'] }}, posted in {{ $month->format('M Y') }}</p>
                    <p class="text-xl font-semibold text-gray-900 dark:text-gray-100">@money($data['ledger']['posted'])</p>
                    <p class="text-xs mt-1 {{ abs($data['ledger']['difference']) >= 0.01 ? 'text-red-600 font-semibold' : 'text-green-700 dark:text-green-400' }}">
                        Difference: @money($data['ledger']['difference']) {{ abs($data['ledger']['difference']) >= 0.01 ? '(check journals posted to this account)' : '(agrees)' }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Account balance today: @money($data['ledger']['balance'])</p>
                </x-card>
                <x-card class="p-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Due</p>
                    <p class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $data['due_date']->format('j M Y') }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $data['due_rule'] }}</p>
                </x-card>
            </div>

            @if(isset($data['extra']['year_to_date']))
                <x-card class="p-4 text-sm text-gray-700 dark:text-gray-300">
                    ITF is paid once a year. {{ $month->format('Y') }} so far: payroll @money($data['extra']['year_payroll']), ITF @money($data['extra']['year_to_date']).
                </x-card>
            @endif

            @forelse($data['groups'] as $group)
                <x-card>
                    <div class="px-6 pt-5 flex flex-wrap justify-between gap-2">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $group['label'] }}</h3>
                        <p class="text-sm text-gray-700 dark:text-gray-300">Owed: <strong>@money($group['amount'])</strong></p>
                    </div>
                    <div class="overflow-x-auto p-4">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    @foreach($data['columns'] as $key => $label)
                                        <th scope="col" class="px-4 py-2 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase {{ in_array($key, ['employee', 'staff_no', 'tin', 'rsa_pin', 'nhf_number']) ? 'text-left' : 'text-right' }}">{{ $label }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($group['rows'] as $row)
                                    <tr>
                                        @foreach($data['columns'] as $key => $label)
                                            @if(in_array($key, ['employee', 'staff_no', 'tin', 'rsa_pin', 'nhf_number']))
                                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $row[$key] ?? '' }}</td>
                                            @else
                                                <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">@money($row[$key] ?? 0)</td>
                                            @endif
                                        @endforeach
                                    </tr>
                                @endforeach
                                <tr class="bg-gray-50 dark:bg-gray-700 font-semibold">
                                    <td colspan="{{ count($data['columns']) - 1 }}" class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">Total {{ $group['label'] }}</td>
                                    <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">@money($group['amount'])</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @empty
                <x-card class="p-6 text-sm text-gray-500 dark:text-gray-400">Nothing owed for {{ $month->format('F Y') }}: no approved payroll with this contribution.</x-card>
            @endforelse
        </div>
    </div>
</x-app-layout>
