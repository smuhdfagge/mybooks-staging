<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Supplier Advances</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Money paid to suppliers before their bill. Use it against the bill when it comes.</p>
            </div>
            @can('create payments-made')
                <a href="{{ route('supplier-advances.create') }}" class="btn-primary">Pay an advance</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <div class="flex gap-3 text-sm">
                <a href="{{ route('supplier-advances.index') }}" class="{{ request()->boolean('unused') ? 'text-gray-500' : 'font-semibold text-indigo-600 dark:text-indigo-400' }}">All</a>
                <a href="{{ route('supplier-advances.index', ['unused' => 1]) }}" class="{{ request()->boolean('unused') ? 'font-semibold text-indigo-600 dark:text-indigo-400' : 'text-gray-500' }}">Not yet used</a>
            </div>
            <x-card>
                @if($advances->isEmpty())
                    <p class="p-6 text-sm text-gray-500 dark:text-gray-400">No supplier advances.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Payment</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Supplier</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Paid</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Not yet used</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($advances as $advance)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-3 whitespace-nowrap"><a href="{{ route('supplier-advances.show', $advance) }}" class="text-indigo-600 dark:text-indigo-400 font-medium">{{ $advance->payment_number }}</a></td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">{{ $advance->payment_date->format('d M Y') }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $advance->vendor->name }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-900 dark:text-gray-100">@money($advance->amount)</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-medium text-gray-900 dark:text-gray-100">@money($advance->unused_amount)</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4">{{ $advances->links() }}</div>
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>
