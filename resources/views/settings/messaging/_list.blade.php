{{--
    SMS / WhatsApp messages (session 16), as a list that reads well on a
    phone. $messages: CustomerMessage list; $showCustomer: show the customer
    and invoice (off on the invoice page). Numbers are masked.
--}}
@php $tz = config('mybooks.messaging.timezone'); @endphp
<ul class="mt-3 divide-y divide-gray-200 dark:divide-gray-700" data-testid="message-list">
    @forelse($messages as $message)
        <li class="py-3 text-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2 text-gray-900 dark:text-gray-100">
                    <span class="font-medium">{{ $message->typeLabel() }}</span>
                    <span class="text-gray-500 dark:text-gray-400">· {{ $message->channelLabel() }} to {{ $message->maskedTo() }}</span>
                    @if($showCustomer && $message->customer)
                        <span class="text-gray-500 dark:text-gray-400">· {{ $message->customer->name }}</span>
                    @endif
                    @if($showCustomer && $message->invoice)
                        <a href="{{ route('invoices.show', $message->invoice) }}" class="text-brand-600 dark:text-brand-300 hover:underline">{{ $message->invoice->invoice_number }}</a>
                    @endif
                </div>
                <x-status-badge :status="$message->status" />
            </div>
            <p class="mt-1 text-gray-600 dark:text-gray-400 break-words">{{ $message->body }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ $message->created_at->setTimezone($tz)->format('j M Y, g:ia') }}
                @if($message->status === 'queued' && $message->send_after) · goes at {{ $message->send_after->setTimezone($tz)->format('j M, g:ia') }} @endif
                @if($message->channel === 'sms' && $message->segments > 1) · counts as {{ $message->segments }} SMS @endif
                @if($message->creator) · by {{ $message->creator->name }} @endif
                @if($message->status === 'failed' && $message->error) · <span class="text-red-600 dark:text-red-400">{{ $message->error }}</span> @endif
            </p>
        </li>
    @empty
        <li class="py-3 text-sm text-gray-500 dark:text-gray-400">No SMS or WhatsApp messages yet.</li>
    @endforelse
</ul>
