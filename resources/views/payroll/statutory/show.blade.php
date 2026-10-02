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
            @if($errors->any())
                <div role="alert" class="p-4 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 text-sm text-red-700 dark:text-red-200">
                    @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-card class="p-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Schedule total</p>
                    <p class="text-xl font-semibold text-gray-900 dark:text-gray-100">@money($data['total'])</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Remitted @money($data['remitted']) &middot; outstanding @money($data['outstanding'])</p>
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
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            Owed: <strong>@money($group['amount'])</strong>
                            &middot; Remitted: <strong>@money($group['remitted'])</strong>
                            &middot; Outstanding: <strong class="{{ $group['outstanding'] > 0.004 ? 'text-red-600' : 'text-green-700 dark:text-green-400' }}">@money(max(0, $group['outstanding']))</strong>
                            @if($group['amount'] > 0 && $group['outstanding'] <= 0.004)
                                <span class="ml-1 inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Remitted</span>
                            @endif
                        </p>
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

                    @if($group['remittances']->isNotEmpty())
                        <div class="px-6 pb-4">
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">Payments made</h4>
                            <ul class="text-sm text-gray-700 dark:text-gray-300 space-y-1">
                                @foreach($group['remittances'] as $r)
                                    <li>{{ $r->paid_on->format('j M Y') }}: @money($r->amount) &middot; ref {{ $r->reference ?: 'none' }} &middot; journal {{ $r->journal?->journal_number }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @can('record statutory-remittances')
                        @if($group['outstanding'] > 0.004 && $group['key'] !== 'none')
                            <form method="POST" action="{{ route('payroll.statutory.remit', $data['schedule']) }}" class="px-6 pb-6 grid grid-cols-1 sm:grid-cols-6 gap-3 items-end border-t border-gray-100 dark:border-gray-700 pt-4">
                                @csrf
                                <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                                <input type="hidden" name="group_key" value="{{ $group['key'] }}">
                                <div>
                                    <label for="amount-{{ $group['key'] }}" class="form-label">Amount</label>
                                    <input id="amount-{{ $group['key'] }}" type="number" name="amount" step="0.01" min="0.01" max="{{ $group['outstanding'] }}" value="{{ number_format($group['outstanding'], 2, '.', '') }}" class="form-control" required>
                                </div>
                                <div>
                                    <label for="paid_on-{{ $group['key'] }}" class="form-label">Date paid</label>
                                    <input id="paid_on-{{ $group['key'] }}" type="date" name="paid_on" value="{{ now()->toDateString() }}" class="form-control" required>
                                </div>
                                <div>
                                    <label for="bank_id-{{ $group['key'] }}" class="form-label">From bank</label>
                                    <select id="bank_id-{{ $group['key'] }}" name="bank_id" class="form-control">
                                        <option value="">Default account</option>
                                        @foreach($banks as $bank)
                                            <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="payment_method-{{ $group['key'] }}" class="form-label">Paid by</label>
                                    <select id="payment_method-{{ $group['key'] }}" name="payment_method" class="form-control">
                                        @foreach($methods as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="reference-{{ $group['key'] }}" class="form-label">Reference</label>
                                    <input id="reference-{{ $group['key'] }}" type="text" name="reference" maxlength="100" class="form-control" placeholder="Receipt / RRR no.">
                                </div>
                                <div>
                                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">Record payment</button>
                                </div>
                            </form>
                        @elseif($group['key'] === 'none')
                            <p class="px-6 pb-4 text-sm text-yellow-700 dark:text-yellow-300">Set the {{ $data['schedule'] === 'paye' ? 'PAYE state' : 'PFA' }} on these employees before recording a payment.</p>
                        @endif
                    @endcan
                </x-card>
            @empty
                <x-card class="p-6 text-sm text-gray-500 dark:text-gray-400">Nothing owed for {{ $month->format('F Y') }}: no approved payroll with this contribution.</x-card>
            @endforelse
        </div>
    </div>
</x-app-layout>
