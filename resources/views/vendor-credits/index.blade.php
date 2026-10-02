<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Supplier Credits</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Goods sent back to suppliers and credit notes they have given you.</p>
            </div>
            @can('create bills')
                <a href="{{ route('vendor-credits.create') }}" class="btn-primary">New supplier credit</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <x-card class="p-4">
                <form method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                    <div>
                        <x-field name="search" label="Search" :value="$search" placeholder="Number, reference or supplier" />
                    </div>
                    <div>
                        <x-field name="status" label="Status" type="select">
                            <option value="">All</option>
                            @foreach($statuses as $s)
                                <option value="{{ $s->value }}" @selected($status === $s->value)>{{ $s === \App\Enums\VendorCreditStatus::Closed ? 'Used up' : $s->label() }}</option>
                            @endforeach
                        </x-field>
                    </div>
                    <div><button class="btn-primary w-full sm:w-auto">Filter</button></div>
                </form>
            </x-card>

            <x-card>
                @if($credits->isEmpty())
                    <p class="p-6 text-sm text-gray-500 dark:text-gray-400">No supplier credits yet. When you send goods back to a supplier, or they give you a credit note, record it here.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Number</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Supplier</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase hidden md:table-cell">Bill</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Total</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Left to use</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($credits as $credit)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-3 whitespace-nowrap"><a href="{{ route('vendor-credits.show', $credit) }}" class="text-indigo-600 dark:text-indigo-400 font-medium">{{ $credit->vendor_credit_number }}</a></td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">{{ $credit->credit_date->format('d M Y') }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $credit->vendor->name }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 hidden md:table-cell">{{ $credit->bill?->bill_number ?? '—' }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-900 dark:text-gray-100">@money($credit->total)</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-900 dark:text-gray-100">@money($credit->balance)</td>
                                        <td class="px-4 py-3">@include('vendor-credits._status', ['status' => $credit->status])</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4">{{ $credits->links() }}</div>
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>
