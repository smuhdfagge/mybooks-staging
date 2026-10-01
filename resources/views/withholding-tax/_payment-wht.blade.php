{{--
    Withholding tax on a payment (shown when some was deducted).
    $payment: PaymentMade or PaymentReceived; $side: 'made' or 'received'.
--}}
@if((float) $payment->wht_amount > 0)
<div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
    <div class="p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Withholding tax</h3>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div class="flex justify-between border-b border-gray-100 dark:border-gray-700 py-1">
                <dt class="text-gray-500 dark:text-gray-400">Transaction type</dt>
                <dd class="text-gray-900 dark:text-gray-100 text-right">{{ $payment->whtCategory?->name ?? 'Not given' }}</dd>
            </div>
            <div class="flex justify-between border-b border-gray-100 dark:border-gray-700 py-1">
                <dt class="text-gray-500 dark:text-gray-400">Rate</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ rtrim(rtrim(number_format((float) $payment->wht_rate, 2), '0'), '.') }}%</dd>
            </div>
            <div class="flex justify-between border-b border-gray-100 dark:border-gray-700 py-1">
                <dt class="text-gray-500 dark:text-gray-400">Amount before VAT</dt>
                <dd class="text-gray-900 dark:text-gray-100">@money($payment->wht_base)</dd>
            </div>
            <div class="flex justify-between border-b border-gray-100 dark:border-gray-700 py-1">
                <dt class="text-gray-500 dark:text-gray-400">{{ $side === 'made' ? 'WHT withheld' : 'WHT deducted by customer' }}</dt>
                <dd class="font-semibold text-gray-900 dark:text-gray-100">@money($payment->wht_amount)</dd>
            </div>
            <div class="flex justify-between border-b border-gray-100 dark:border-gray-700 py-1">
                <dt class="text-gray-500 dark:text-gray-400">{{ $side === 'made' ? 'Paid from bank' : 'Received in bank' }}</dt>
                <dd class="text-gray-900 dark:text-gray-100">@money($payment->amount)</dd>
            </div>
            <div class="flex justify-between border-b border-gray-100 dark:border-gray-700 py-1">
                <dt class="text-gray-500 dark:text-gray-400">{{ $side === 'made' ? 'Settled on the bill' : 'Settled on the invoice' }}</dt>
                <dd class="font-semibold text-gray-900 dark:text-gray-100">@money($payment->settledAmount())</dd>
            </div>
            @if($side === 'made')
                <div class="flex justify-between border-b border-gray-100 dark:border-gray-700 py-1 sm:col-span-2">
                    <dt class="text-gray-500 dark:text-gray-400">Remit to</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{{ \App\Services\Accounting\WithholdingTax::authorityLabel((string) $payment->wht_authority, $payment->wht_state) }}</dd>
                </div>
            @endif
        </dl>
    </div>
</div>
@endif
