{{-- Stock per warehouse for one item (session 12). $byWarehouse: Inventory rows with warehouse. --}}
@if($byWarehouse->isNotEmpty())
<x-card class="mb-6" title="Stock by warehouse">
    <div class="mt-3 pb-2 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th scope="col" class="px-6 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Warehouse</th>
                    <th scope="col" class="px-6 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">On hand</th>
                    <th scope="col" class="px-6 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase hidden sm:table-cell">Reserved</th>
                    <th scope="col" class="px-6 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Available</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($byWarehouse as $row)
                    <tr>
                        <td class="px-6 py-2 text-gray-900 dark:text-gray-100">
                            @if($row->warehouse && Route::has('warehouses.show'))
                                <a href="{{ route('warehouses.show', $row->warehouse) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $row->warehouse->name }}</a>
                            @else
                                {{ $row->warehouse->name ?? '-' }}
                            @endif
                            @if($row->warehouse?->is_default)<span class="ml-1 text-xs text-gray-500 dark:text-gray-400">(default)</span>@endif
                        </td>
                        <td class="px-6 py-2 text-right text-gray-900 dark:text-gray-100">{{ rtrim(rtrim(number_format((float) $row->quantity, 4), '0'), '.') }}</td>
                        <td class="px-6 py-2 text-right text-orange-600 dark:text-orange-400 hidden sm:table-cell">{{ rtrim(rtrim(number_format((float) $row->reserved_quantity, 4), '0'), '.') }}</td>
                        <td class="px-6 py-2 text-right text-green-700 dark:text-green-400">{{ rtrim(rtrim(number_format((float) $row->quantity - (float) $row->reserved_quantity, 4), '0'), '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 dark:bg-gray-700/50 font-semibold">
                <tr>
                    <td class="px-6 py-2 text-gray-900 dark:text-gray-100">All warehouses</td>
                    <td class="px-6 py-2 text-right text-gray-900 dark:text-gray-100">{{ rtrim(rtrim(number_format((float) $byWarehouse->sum('quantity'), 4), '0'), '.') }}</td>
                    <td class="px-6 py-2 text-right text-gray-900 dark:text-gray-100 hidden sm:table-cell">{{ rtrim(rtrim(number_format((float) $byWarehouse->sum('reserved_quantity'), 4), '0'), '.') }}</td>
                    <td class="px-6 py-2 text-right text-gray-900 dark:text-gray-100">{{ rtrim(rtrim(number_format((float) $byWarehouse->sum('quantity') - (float) $byWarehouse->sum('reserved_quantity'), 4), '0'), '.') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</x-card>
@endif
