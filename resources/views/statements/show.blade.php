{{--
    Customer or supplier statement on screen (session 10), with print, PDF
    and email. Also the Reports > Customer / Supplier Statement page, which
    adds a picker ($pickList).
--}}
@php
    $isCustomer = $side === \App\Services\Statements\Subledger::CUSTOMERS;
    $base = $isCustomer ? 'customers' : 'vendors';
    $who = $isCustomer ? 'customer' : 'supplier';
    $s = $statement;
    $query = ['type' => $type, 'from' => $type === 'activity' ? $from : null, 'to' => $to];
    $secondary = 'inline-flex items-center justify-center px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-xs font-semibold uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700';
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $isCustomer ? 'Customer statement' : 'Supplier statement' }}@if($party) · {{ $party->name }}@endif
                </h2>
                @if($s)
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $s->isActivity() ? 'Activity' : 'Open items' }} · {{ $s->periodText() }}</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                @if($party)
                    <a href="{{ route($base.'.show', $party) }}" class="{{ $secondary }}">Back to {{ $who }}</a>
                @else
                    <a href="{{ route('reports.index') }}" class="{{ $secondary }}">Back to reports</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-error-summary />

            <x-card class="p-4 sm:p-6">
                <form method="GET" action="{{ url()->current() }}" x-data="{ type: @js($type) }" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
                    @if($pickList !== null)
                        <div class="sm:col-span-2 lg:col-span-1">
                            <x-field :name="$isCustomer ? 'customer_id' : 'vendor_id'" :label="$isCustomer ? 'Customer' : 'Supplier'" type="select" required>
                                <option value="">Choose…</option>
                                @foreach($pickList as $option)
                                    <option value="{{ $option->id }}" @selected($party && $party->id === $option->id)>{{ $option->name }}{{ $option->company_name && $option->company_name !== $option->name ? ' ('.$option->company_name.')' : '' }}</option>
                                @endforeach
                            </x-field>
                        </div>
                    @endif
                    <div>
                        <x-field name="type" label="Statement type" type="select" x-model="type">
                            @foreach(\App\Services\Statements\Statement::TYPES as $value => $label)
                                <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                            @endforeach
                        </x-field>
                    </div>
                    <div x-show="type === 'activity'">
                        <x-field name="from" label="From" type="date" :value="$from" />
                    </div>
                    <div>
                        <label for="to" class="form-label"><span x-text="type === 'activity' ? 'To' : 'As at'">To</span></label>
                        <input type="date" name="to" id="to" value="{{ $to }}" class="form-control">
                    </div>
                    <div>
                        <button type="submit" class="btn-primary w-full justify-center">Show statement</button>
                    </div>
                </form>
            </x-card>

            @if($s)
                @if($features)
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route($base.'.statement.print', [$party] + $query) }}" target="_blank" rel="noopener" class="{{ $secondary }}">Print</a>
                        <a href="{{ route($base.'.statement.pdf', [$party] + $query) }}" class="{{ $secondary }}">Download PDF</a>
                        @can('send invoices')
                            <button type="button" data-open-modal="email-statement" class="btn-primary">Email statement</button>
                        @endcan
                    </div>
                @endif

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                    @if($s->isActivity())
                        <x-card class="p-4">
                            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">Opening balance</p>
                            <p class="text-lg sm:text-2xl font-semibold text-gray-900 dark:text-gray-100">@money($s->opening)</p>
                        </x-card>
                        <x-card class="p-4">
                            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">{{ $s->chargesHeading() }}</p>
                            <p class="text-lg sm:text-2xl font-semibold text-gray-900 dark:text-gray-100">@money($s->totalCharges)</p>
                        </x-card>
                        <x-card class="p-4">
                            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">{{ $s->creditsHeading() }}</p>
                            <p class="text-lg sm:text-2xl font-semibold text-green-700 dark:text-green-400">@money($s->totalCredits)</p>
                        </x-card>
                    @else
                        <x-card class="p-4">
                            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">{{ $isCustomer ? 'Unpaid invoices' : 'Unpaid bills' }}</p>
                            <p class="text-lg sm:text-2xl font-semibold text-gray-900 dark:text-gray-100">@money(array_sum(array_column($s->open['items'], 'amount')))</p>
                        </x-card>
                        <x-card class="p-4">
                            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">Credits not yet used</p>
                            <p class="text-lg sm:text-2xl font-semibold text-green-700 dark:text-green-400">@money(array_sum(array_column($s->open['credits'], 'amount')))</p>
                        </x-card>
                        <x-card class="p-4">
                            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">Overdue</p>
                            <p class="text-lg sm:text-2xl font-semibold text-red-600 dark:text-red-300">@money($s->ageing['1_30'] + $s->ageing['31_60'] + $s->ageing['61_90'] + $s->ageing['over_90'])</p>
                        </x-card>
                    @endif
                    <x-card class="p-4 ring-1 ring-brand-200 dark:ring-brand-800">
                        <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">{{ $s->isActivity() ? 'Closing balance' : $s->balanceText() }}</p>
                        <p class="text-lg sm:text-2xl font-semibold text-brand-600 dark:text-brand-300">@money($s->closing)</p>
                        @if($s->isActivity())<p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $s->balanceText() }}</p>@endif
                    </x-card>
                </div>

                @if($s->isActivity())
                    <x-card>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-3 sm:px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                                        <th class="px-3 sm:px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Details</th>
                                        <th class="hidden sm:table-cell px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $s->chargesHeading() }}</th>
                                        <th class="hidden sm:table-cell px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $s->creditsHeading() }}</th>
                                        <th class="px-3 sm:px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Balance</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr class="bg-gray-50 dark:bg-gray-900/30 italic">
                                        <td class="px-3 sm:px-4 py-2 whitespace-nowrap text-gray-600 dark:text-gray-400 text-xs sm:text-sm">{{ \Carbon\Carbon::parse($s->from)->format('j M y') }}</td>
                                        <td class="px-3 sm:px-4 py-2 text-gray-600 dark:text-gray-400">Balance brought forward</td>
                                        <td class="hidden sm:table-cell"></td><td class="hidden sm:table-cell"></td>
                                        <td class="px-3 sm:px-4 py-2 text-right whitespace-nowrap text-gray-900 dark:text-gray-100">@money($s->opening)</td>
                                    </tr>
                                    @forelse($s->rows as $row)
                                        <tr>
                                            <td class="px-3 sm:px-4 py-2 whitespace-nowrap text-gray-700 dark:text-gray-300 text-xs sm:text-sm">{{ \Carbon\Carbon::parse($row['date'])->format('j M y') }}</td>
                                            <td class="px-3 sm:px-4 py-2 text-gray-900 dark:text-gray-100">
                                                @if($row['url'])<a href="{{ $row['url'] }}" class="text-brand-600 dark:text-brand-300 hover:underline">{{ $row['label'] }}</a>@else{{ $row['label'] }}@endif
                                                @if($row['due_date'] && $row['charge'] > 0)<span class="block text-xs text-gray-500 dark:text-gray-400">Due {{ \Carbon\Carbon::parse($row['due_date'])->format('j M Y') }}</span>@endif
                                                {{-- Amounts under the details on phones --}}
                                                <span class="sm:hidden block text-xs mt-0.5">
                                                    @if($row['charge'] > 0)<span class="whitespace-nowrap text-gray-700 dark:text-gray-300">+@money($row['charge'])</span>@endif
                                                    @if($row['credit'] > 0)<span class="whitespace-nowrap text-green-700 dark:text-green-400 ml-1">-@money($row['credit'])</span>@endif
                                                </span>
                                            </td>
                                            <td class="hidden sm:table-cell px-4 py-2 text-right whitespace-nowrap text-gray-900 dark:text-gray-100">{{ $row['charge'] > 0 ? \App\Support\Money::format($row['charge']) : '' }}</td>
                                            <td class="hidden sm:table-cell px-4 py-2 text-right whitespace-nowrap text-green-700 dark:text-green-400">{{ $row['credit'] > 0 ? \App\Support\Money::format($row['credit']) : '' }}</td>
                                            <td class="px-3 sm:px-4 py-2 text-right whitespace-nowrap font-medium text-gray-900 dark:text-gray-100">@money($row['balance'])</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Nothing in this period.</td></tr>
                                    @endforelse
                                </tbody>
                                <tfoot class="bg-gray-50 dark:bg-gray-700 font-semibold">
                                    <tr>
                                        <td class="px-3 sm:px-4 py-3"></td>
                                        <td class="px-3 sm:px-4 py-3 text-gray-900 dark:text-gray-100">Closing balance</td>
                                        <td class="hidden sm:table-cell px-4 py-3 text-right whitespace-nowrap">@money($s->totalCharges)</td>
                                        <td class="hidden sm:table-cell px-4 py-3 text-right whitespace-nowrap text-green-700 dark:text-green-400">@money($s->totalCredits)</td>
                                        <td class="px-3 sm:px-4 py-3 text-right whitespace-nowrap text-brand-600 dark:text-brand-300">@money($s->closing)</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </x-card>
                @else
                    <x-card :title="$isCustomer ? 'Unpaid invoices' : 'Unpaid bills'">
                        <div class="overflow-x-auto mt-3">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-3 sm:px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Details</th>
                                        <th class="hidden sm:table-cell px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Due</th>
                                        <th class="px-3 sm:px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Days overdue</th>
                                        <th class="hidden sm:table-cell px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Original</th>
                                        <th class="px-3 sm:px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Still due</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @forelse($s->open['items'] as $item)
                                        <tr>
                                            <td class="px-3 sm:px-4 py-2 text-gray-900 dark:text-gray-100">
                                                @if($item['url'])<a href="{{ $item['url'] }}" class="text-brand-600 dark:text-brand-300 hover:underline">{{ $item['label'] }}</a>@else{{ $item['label'] }}@endif
                                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ \Carbon\Carbon::parse($item['date'])->format('j M Y') }}<span class="sm:hidden"> · due {{ \Carbon\Carbon::parse($item['due_date'])->format('j M Y') }}</span></span>
                                            </td>
                                            <td class="hidden sm:table-cell px-4 py-2 whitespace-nowrap text-gray-700 dark:text-gray-300">{{ \Carbon\Carbon::parse($item['due_date'])->format('j M Y') }}</td>
                                            <td class="px-3 sm:px-4 py-2 text-right {{ $item['days_overdue'] > 0 ? 'text-red-600 dark:text-red-300 font-medium' : 'text-gray-500 dark:text-gray-400' }}">{{ $item['days_overdue'] > 0 ? $item['days_overdue'] : 'Not yet due' }}</td>
                                            <td class="hidden sm:table-cell px-4 py-2 text-right whitespace-nowrap text-gray-700 dark:text-gray-300">@money($item['total'])</td>
                                            <td class="px-3 sm:px-4 py-2 text-right whitespace-nowrap font-medium text-gray-900 dark:text-gray-100">@money($item['amount'])</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Nothing unpaid.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-card>
                    @if(count($s->open['credits']))
                        <x-card title="Payments and credits not yet used">
                            <ul class="divide-y divide-gray-200 dark:divide-gray-700 mt-3">
                                @foreach($s->open['credits'] as $credit)
                                    <li class="px-4 sm:px-6 py-3 flex justify-between gap-3 text-sm">
                                        <span class="text-gray-900 dark:text-gray-100">
                                            {{ $credit['label'] }} ·
                                            @if($credit['url'])<a href="{{ $credit['url'] }}" class="text-brand-600 dark:text-brand-300 hover:underline">{{ $credit['reference'] }}</a>@else{{ $credit['reference'] }}@endif
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ \Carbon\Carbon::parse($credit['date'])->format('j M Y') }}</span>
                                        </span>
                                        <span class="whitespace-nowrap text-green-700 dark:text-green-400 font-medium">-@money($credit['amount'])</span>
                                    </li>
                                @endforeach
                            </ul>
                        </x-card>
                    @endif
                @endif

                <x-card title="Ageing as at {{ \Carbon\Carbon::parse($s->to)->format('j M Y') }}">
                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-px bg-gray-200 dark:bg-gray-700 m-4 sm:m-6 mt-3 rounded-md overflow-hidden">
                        @foreach(\App\Services\Statements\Subledger::AGEING_LABELS as $key => $label)
                            <div class="p-3 {{ $key === 'total' ? 'col-span-2 sm:col-span-1 bg-brand-50 dark:bg-brand-900/40' : 'bg-white dark:bg-gray-800' }}">
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</p>
                                <p class="text-sm sm:text-base font-semibold {{ in_array($key, ['31_60', '61_90', 'over_90'], true) && $s->ageing[$key] > 0 ? 'text-red-600 dark:text-red-300' : 'text-gray-900 dark:text-gray-100' }}">@money($s->ageing[$key])</p>
                            </div>
                        @endforeach
                    </div>
                </x-card>

                @if($features)
                    @can('send invoices')
                        <x-modal name="email-statement" title="Email statement" maxWidth="lg">
                            <form method="POST" action="{{ route($base.'.statement.email', $party) }}" class="p-6 space-y-4">
                                @csrf
                                <input type="hidden" name="type" value="{{ $type }}">
                                @if($type === 'activity')<input type="hidden" name="from" value="{{ $from }}">@endif
                                <input type="hidden" name="to" value="{{ $to }}">
                                @if($party->email)
                                    <p class="text-sm text-gray-600 dark:text-gray-400">The statement goes to <strong>{{ $party->email }}</strong> with a PDF copy attached.</p>
                                @else
                                    <p class="text-sm text-amber-700 dark:text-amber-300">This {{ $who }} has no email address saved. Add one on their page first.</p>
                                @endif
                                <div><x-field name="subject" label="Subject" :value="old('subject', $emailSubject)" required maxlength="200" /></div>
                                <div><x-field name="message" label="Message" type="textarea" rows="6" :value="old('message', $emailMessage)" required /></div>
                                <div class="flex justify-end gap-3">
                                    <button type="button" data-close-modal="email-statement" class="{{ $secondary }}">Cancel</button>
                                    <button type="submit" class="btn-primary" @disabled(! $party->email)>Send</button>
                                </div>
                            </form>
                        </x-modal>
                    @endcan
                @endif
            @elseif($pickList !== null)
                <x-card class="p-6 text-sm text-gray-600 dark:text-gray-400">Choose a {{ $who }} to see their statement.</x-card>
            @endif
        </div>
    </div>
</x-app-layout>
