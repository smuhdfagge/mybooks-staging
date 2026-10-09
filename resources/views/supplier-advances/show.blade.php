<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Supplier advance {{ $advance->payment_number }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <a href="{{ route('vendors.show', $advance->vendor) }}" class="text-brand-600 dark:text-brand-300">{{ $advance->vendor->name }}</a>
                    · paid {{ $advance->payment_date->format('d M Y') }}{{ $advance->bank ? ' from '.$advance->bank->name : '' }}
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('payments-made.show', $advance) }}" class="text-sm text-brand-600 dark:text-brand-300">Payment details</a>
                <a href="{{ route('supplier-advances.index') }}" class="text-sm text-brand-600 dark:text-brand-300">Back</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-error-summary />
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-card class="p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Paid in advance</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">@money($advance->amount)</p>
                    @if((float) $advance->wht_amount > 0)
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Plus WHT withheld @money($advance->wht_amount): credit of @money($advance->settledAmount())</p>
                    @endif
                </x-card>
                <x-card class="p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Not yet used</p>
                    <p class="text-2xl font-semibold text-brand-600 dark:text-brand-300">@money($advance->unused_amount)</p>
                </x-card>
            </div>

            @if((float) $advance->unused_amount > 0)
                @can('create payments-made')
                <x-card title="Use against a bill" class="pb-6">
                    <div class="px-6 pt-2">
                        @if($openBills->isEmpty())
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $advance->vendor->name }} has no unpaid bills yet. When their bill arrives, come back here to use this advance.</p>
                        @else
                            <form method="POST" action="{{ route('supplier-advances.apply', $advance) }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end"
                                  x-data="{ bills: @js($openBills->mapWithKeys(fn ($b) => [$b->id => (float) $b->balance_due])), bill: '', amount: '' }">
                                @csrf
                                <div>
                                    <x-field name="bill_id" label="Bill" type="select" x-model="bill" x-on:change="amount = Math.min(bills[bill] || 0, {{ (float) $advance->unused_amount }}).toFixed(2)" required>
                                        <option value="">Choose a bill</option>
                                        @foreach($openBills as $bill)
                                            <option value="{{ $bill->id }}">{{ $bill->bill_number }} · owes {{ \App\Support\Money::format($bill->balance_due) }}</option>
                                        @endforeach
                                    </x-field>
                                </div>
                                <div><x-field name="amount" label="Amount" type="number" step="0.01" min="0.01" x-model="amount" required /></div>
                                <div><x-field name="application_date" label="Date" type="date" :value="now()->toDateString()" required /></div>
                                <div class="sm:col-span-3"><button class="btn-primary">Use advance</button></div>
                            </form>
                        @endif
                    </div>
                </x-card>
                @endcan
            @endif

            <x-card title="Used against bills">
                @if($advance->advanceApplications->isEmpty())
                    <p class="p-6 pt-3 text-sm text-gray-500 dark:text-gray-400">Not used yet.</p>
                @else
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700 p-6 pt-3 text-sm text-gray-900 dark:text-gray-100">
                        @foreach($advance->advanceApplications as $application)
                            <li class="py-2 flex justify-between gap-3">
                                <span>{{ $application->application_date->format('d M Y') }} · bill <a href="{{ route('bills.show', $application->bill) }}" class="text-brand-600 dark:text-brand-300">{{ $application->bill->bill_number }}</a>
                                    @if($application->appliedPayment) (payment <a href="{{ route('payments-made.show', $application->appliedPayment) }}" class="text-brand-600 dark:text-brand-300">{{ $application->appliedPayment->payment_number }}</a>)@endif
                                </span>
                                <span class="font-medium">@money($application->amount)</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>
