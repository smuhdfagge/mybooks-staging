<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $recurrentBill->profile_name }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ ucfirst($recurrentBill->frequency) }} • {{ $recurrentBill->vendor->name ?? 'Unknown Vendor' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form action="{{ route('recurrent-bills.toggle', $recurrentBill) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-4 py-2 {{ $recurrentBill->status === 'active' ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-green-600 hover:bg-green-700' }} border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition">
                        @if($recurrentBill->status === 'active')
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Pause
                        @else
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Activate
                        @endif
                    </button>
                </form>
                <a href="{{ route('recurrent-bills.edit', $recurrentBill) }}" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit
                </a>
                <a href="{{ route('recurrent-bills.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back
                </a>
            </div>
        </div>
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
                    <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $recurrentBill->next_bill_date?->format('M d, Y') ?? '—' }}</div>
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
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $recurrentBill->start_date->format('M d, Y') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">End Date</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $recurrentBill->end_date?->format('M d, Y') ?? 'Indefinite' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Next Bill Date</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $recurrentBill->next_bill_date?->format('M d, Y') ?? '—' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Bill Items -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                Bill Items
                            </h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead>
                                        <tr>
                                            <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider pb-2">Description</th>
                                            <th class="text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider pb-2">Qty</th>
                                            <th class="text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider pb-2">Price</th>
                                            <th class="text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider pb-2">Tax</th>
                                            <th class="text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider pb-2">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($recurrentBill->items as $item)
                                            <tr>
                                                <td class="py-3 text-sm text-gray-900 dark:text-gray-100">
                                                    {{ $item->description }}
                                                    @if($item->item)
                                                        <span class="text-gray-500 dark:text-gray-400 text-xs">({{ $item->item->name }})</span>
                                                    @endif
                                                </td>
                                                <td class="py-3 text-sm text-gray-900 dark:text-gray-100 text-right">{{ number_format($item->quantity, 2) }}</td>
                                                <td class="py-3 text-sm text-gray-900 dark:text-gray-100 text-right">@money($item->unit_price)</td>
                                                <td class="py-3 text-sm text-gray-500 dark:text-gray-400 text-right">{{ $item->tax_rate }}%</td>
                                                <td class="py-3 text-sm font-medium text-gray-900 dark:text-gray-100 text-right">@money($item->total)</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="border-t-2 border-gray-300 dark:border-gray-600">
                                        <tr>
                                            <td colspan="4" class="py-2 text-sm font-medium text-gray-700 dark:text-gray-300 text-right">Subtotal</td>
                                            <td class="py-2 text-sm text-gray-900 dark:text-gray-100 text-right">@money($recurrentBill->subtotal)</td>
                                        </tr>
                                        <tr>
                                            <td colspan="4" class="py-2 text-sm font-medium text-gray-700 dark:text-gray-300 text-right">Tax</td>
                                            <td class="py-2 text-sm text-gray-900 dark:text-gray-100 text-right">@money($recurrentBill->tax_amount)</td>
                                        </tr>
                                        <tr>
                                            <td colspan="4" class="py-2 text-lg font-bold text-gray-900 dark:text-gray-100 text-right">Total</td>
                                            <td class="py-2 text-lg font-bold text-gray-900 dark:text-gray-100 text-right">@money($recurrentBill->total)</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
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
                                            <span class="text-gray-500 dark:text-gray-400">{{ $bill->bill_date->format('M d, Y') }}</span>
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

                    <!-- Danger Zone -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-red-200 dark:border-red-800">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-red-600 dark:text-red-400 mb-4">Danger Zone</h3>
                            <form action="{{ route('recurrent-bills.destroy', $recurrentBill) }}" method="POST" data-confirm="Are you sure you want to delete this profile? This action cannot be undone.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    Delete Profile
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
