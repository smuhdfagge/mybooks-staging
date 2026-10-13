<x-app-layout>
    <x-slot name="header">
        <x-table.page-header :title="$recurrentBill->profile_name" :description="ucfirst($recurrentBill->frequency).' · '.($recurrentBill->vendor->name ?? 'Unknown supplier')">
            <x-slot name="badge"><x-status-badge :status="$recurrentBill->status === 'active' ? 'active' : 'paused'" :label="$recurrentBill->status === 'active' ? 'Active' : 'Paused'" /></x-slot>
            <x-slot name="more">
                <x-table.menu-item :post="route('recurrent-bills.toggle', $recurrentBill)">{{ $recurrentBill->status === 'active' ? 'Pause' : 'Start again' }}</x-table.menu-item>
                @can('delete recurrent-bills')
                    <x-table.menu-item danger :post="route('recurrent-bills.destroy', $recurrentBill)" method="DELETE" confirm="Delete this profile? Bills already made stay.">Delete profile</x-table.menu-item>
                @endcan
            </x-slot>
            <x-slot name="actions"><a href="{{ route('recurrent-bills.edit', $recurrentBill) }}" class="btn-secondary">Edit</a></x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <!-- Status & Summary -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</div>
                    <div class="mt-1">
                        <span class="px-3 py-1 inline-flex text-sm font-semibold rounded-full {{ $recurrentBill->status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-300' }}">
                            {{ ucfirst($recurrentBill->status) }}
                        </span>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Bill Amount</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">@money($recurrentBill->total)</div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Next Bill Date</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $recurrentBill->next_bill_date?->format('j M Y') ?? '—' }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Bills Generated</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $recurrentBill->bills->count() }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Profile Details -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Schedule Info -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-brand-500 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                Schedule Details
                            </h3>
                            <dl class="grid grid-cols-2 gap-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Frequency</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ ucfirst($recurrentBill->frequency) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Start Date</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $recurrentBill->start_date->format('j M Y') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">End Date</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $recurrentBill->end_date?->format('j M Y') ?? 'Indefinite' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Next Bill Date</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $recurrentBill->next_bill_date?->format('j M Y') ?? '—' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Bill Items -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                Bill Items
                            </h3>
                            <x-table caption="Items on each bill">
<x-slot name="head">
                                            <x-table.th>Description</x-table.th>
                                            <x-table.th num>Qty</x-table.th>
                                            <x-table.th num>Price</x-table.th>
                                            <x-table.th num>Tax</x-table.th>
                                            <x-table.th num>Total</x-table.th>
</x-slot>

                                        @foreach($recurrentBill->items as $item)
                                            <tr>
                                                <td>
                                                    {{ $item->description }}
                                                    @if($item->item)
                                                        <span class="text-gray-500 dark:text-gray-400 text-xs">({{ $item->item->name }})</span>
                                                    @endif
                                                </td>
                                                <td class="num">{{ \App\Support\Figure::qty($item->quantity) }}</td>
                                                <td class="num">@fig($item->unit_price, false)</td>
                                                <td class="num">{{ \App\Support\Figure::rate($item->tax_rate) }}</td>
                                                <td class="font-medium num">@fig($item->total, false)</td>
                                            </tr>
                                        @endforeach
<x-slot name="foot">
                                        <tr>
                                            <td colspan="4" class="font-medium num">Subtotal</td>
                                            <td class="num">@fig($recurrentBill->subtotal, false)</td>
                                        </tr>
                                        <tr>
                                            <td colspan="4" class="font-medium num">Tax</td>
                                            <td class="num">@fig($recurrentBill->tax_amount, false)</td>
                                        </tr>
                                        <tr>
                                            <td colspan="4" class="text-lg font-bold num">Total</td>
                                            <td class="text-lg font-bold num">@fig($recurrentBill->total, false)</td>
                                        </tr>
</x-slot>
</x-table>
                        </div>
                    </div>

                    <!-- Notes -->
                    @if($recurrentBill->notes)
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Notes</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $recurrentBill->notes }}</p>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Vendor Info -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Vendor</h3>
                            @if($recurrentBill->vendor)
                                <div class="space-y-2">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $recurrentBill->vendor->name }}</p>
                                    @if($recurrentBill->vendor->company_name)
                                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $recurrentBill->vendor->company_name }}</p>
                                    @endif
                                    @if($recurrentBill->vendor->email)
                                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $recurrentBill->vendor->email }}</p>
                                    @endif
                                    @if($recurrentBill->vendor->phone)
                                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $recurrentBill->vendor->phone }}</p>
                                    @endif
                                    <a href="{{ route('vendors.show', $recurrentBill->vendor) }}" class="inline-flex items-center text-sm text-brand-600 dark:text-brand-300 hover:text-brand-900 mt-2">
                                        View Vendor →
                                    </a>
                                </div>
                            @else
                                <p class="text-sm text-gray-500 dark:text-gray-400">No vendor assigned</p>
                            @endif
                        </div>
                    </div>

                    <!-- Generated Bills -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Generated Bills</h3>
                            @if($recurrentBill->bills->count() > 0)
                                <ul class="space-y-2">
                                    @foreach($recurrentBill->bills->take(10) as $bill)
                                        <li class="flex justify-between items-center text-sm">
                                            <a href="{{ route('bills.show', $bill) }}" class="text-brand-600 dark:text-brand-300 hover:text-brand-900">
                                                {{ $bill->bill_number }}
                                            </a>
                                            <span class="text-gray-500 dark:text-gray-400">{{ $bill->bill_date->format('j M Y') }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                                @if($recurrentBill->bills->count() > 10)
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-3">And {{ $recurrentBill->bills->count() - 10 }} more...</p>
                                @endif
                            @else
                                <p class="text-sm text-gray-500 dark:text-gray-400">No bills generated yet.</p>
                            @endif
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
