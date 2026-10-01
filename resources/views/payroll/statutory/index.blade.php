<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ __('Statutory Remittances') }}</h2>
            <div class="flex flex-wrap gap-2">
                @can('manage statutory-settings')
                    <a href="{{ route('payroll.statutory.settings') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest hover:bg-gray-50 transition">Settings</a>
                @endcan
                <a href="{{ route('payroll.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">&larr; Back to Payroll</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form method="GET" action="{{ route('payroll.statutory.index') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <x-field name="month" label="Pay month" type="month" :value="$month->format('Y-m')" />
                </div>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">Show</button>
            </form>

            @unless($auto)
                <div class="p-4 rounded-lg bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700 text-sm text-yellow-800 dark:text-yellow-200">
                    Payroll does not yet work out pension, NHF, NSITF and ITF from your statutory settings, so these schedules only show deductions set up by hand.
                    @can('manage statutory-settings')
                        <a href="{{ route('payroll.statutory.settings') }}" class="underline font-medium">Turn it on in Statutory settings.</a>
                    @endcan
                </div>
            @endunless

            <x-card>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <caption class="sr-only">Statutory remittances for {{ $month->format('F Y') }}</caption>
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Schedule</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Owed for {{ $month->format('M Y') }}</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">In the ledger</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Difference</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Due</th>
                                <th scope="col" class="px-4 py-3"><span class="sr-only">Open</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($summaries as $key => $s)
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $s['title'] }}
                                        <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">{{ count($s['groups']) }} {{ $key === 'paye' ? 'state(s)' : ($key === 'pension' ? 'PFA(s)' : 'payee') }}, {{ $s['employees'] }} employee(s)</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">@money($s['total'])</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300">@money($s['ledger']['posted'])</td>
                                    <td class="px-4 py-3 text-sm text-right {{ abs($s['ledger']['difference']) >= 0.01 ? 'text-red-600 font-semibold' : 'text-green-700 dark:text-green-400' }}">@money($s['ledger']['difference'])</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $s['due_date']->format('j M Y') }}</td>
                                    <td class="px-4 py-3 text-sm text-right">
                                        <a href="{{ route('payroll.statutory.show', ['schedule' => $key, 'month' => $month->format('Y-m')]) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Open</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
            <p class="text-xs text-gray-500 dark:text-gray-400">Schedules come from approved and paid payroll whose pay period ends in the month. "In the ledger" is what was posted to the liability account in that month (remittances left out); it should equal the schedule.</p>
        </div>
    </div>
</x-app-layout>
