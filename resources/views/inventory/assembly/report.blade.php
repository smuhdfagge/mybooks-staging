@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
    $groups = collect($rows)->groupBy('kind');
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Production report</h2>
            <a href="{{ route('assembly-orders.index') }}" class="inline-flex items-center justify-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700">All assembly orders</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-card>
                <form method="GET" class="p-4 sm:p-6 grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                    <div><x-field name="from" label="From" type="date" :value="$from->toDateString()" /></div>
                    <div><x-field name="to" label="To" type="date" :value="$to->toDateString()" /></div>
                    <div><button type="submit" class="btn-primary w-full sm:w-auto">Show</button></div>
                </form>
            </x-card>

            @foreach(['build' => 'Made', 'breakdown' => 'Broken down'] as $kind => $heading)
                @continue(! $groups->has($kind))
                <x-card>
                    <div class="p-4 sm:p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">{{ $heading }}, {{ $from->format('M d, Y') }} to {{ $to->format('M d, Y') }}</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead><tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                                    <th class="px-3 py-2 text-left">Item</th>
                                    <th class="px-3 py-2 text-right hidden sm:table-cell">Orders</th>
                                    <th class="px-3 py-2 text-right">Quantity</th>
                                    @if($kind === 'build')
                                        <th class="px-3 py-2 text-right hidden md:table-cell">Components</th>
                                        <th class="px-3 py-2 text-right hidden md:table-cell">Extra costs</th>
                                    @endif
                                    <th class="px-3 py-2 text-right">Total cost</th>
                                    <th class="px-3 py-2 text-right hidden sm:table-cell">Each</th>
                                </tr></thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-900 dark:text-gray-100">
                                    @foreach($groups[$kind] as $row)
                                        <tr>
                                            <td class="px-3 py-2"><a href="{{ route('inventory.show', $row->item_id) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $row->item_name }}</a></td>
                                            <td class="px-3 py-2 text-right hidden sm:table-cell">{{ $row->orders }}</td>
                                            <td class="px-3 py-2 text-right whitespace-nowrap">{{ $fmt($row->quantity) }} {{ $row->unit }}</td>
                                            @if($kind === 'build')
                                                <td class="px-3 py-2 text-right whitespace-nowrap hidden md:table-cell">@money($row->components_cost)</td>
                                                <td class="px-3 py-2 text-right whitespace-nowrap hidden md:table-cell">@money($row->extra_cost)</td>
                                            @endif
                                            <td class="px-3 py-2 text-right whitespace-nowrap font-medium">@money($row->total_cost)</td>
                                            <td class="px-3 py-2 text-right whitespace-nowrap hidden sm:table-cell">@money((float) $row->quantity > 0 ? (float) $row->total_cost / (float) $row->quantity : 0)</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot><tr class="font-semibold text-gray-900 dark:text-gray-100 border-t-2 border-gray-300 dark:border-gray-600">
                                    <td class="px-3 py-2">Total</td>
                                    <td class="px-3 py-2 text-right hidden sm:table-cell">{{ $groups[$kind]->sum('orders') }}</td>
                                    <td class="px-3 py-2"></td>
                                    @if($kind === 'build')
                                        <td class="px-3 py-2 text-right whitespace-nowrap hidden md:table-cell">@money($groups[$kind]->sum('components_cost'))</td>
                                        <td class="px-3 py-2 text-right whitespace-nowrap hidden md:table-cell">@money($groups[$kind]->sum('extra_cost'))</td>
                                    @endif
                                    <td class="px-3 py-2 text-right whitespace-nowrap">@money($groups[$kind]->sum('total_cost'))</td>
                                    <td class="px-3 py-2 hidden sm:table-cell"></td>
                                </tr></tfoot>
                            </table>
                        </div>
                    </div>
                </x-card>
            @endforeach
            @if($groups->isEmpty())
                <x-card><div class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">Nothing was made between {{ $from->format('M d, Y') }} and {{ $to->format('M d, Y') }}.</div></x-card>
            @endif
        </div>
    </div>
</x-app-layout>
