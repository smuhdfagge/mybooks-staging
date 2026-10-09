{{--
    SMS / WhatsApp on the invoice page (session 16): what was sent about this
    invoice, and "Send reminder" while something is owed.
--}}
@php
    $invoiceMessages = \App\Models\CustomerMessage::with('creator')->where('invoice_id', $invoice->id)->latest('id')->limit(20)->get();
    $mobile = \App\Support\PhoneNumber::normalise($invoice->customer?->phone);
    $canRemind = (float) $invoice->balance_due > 0 && in_array($invoice->status, ['sent', 'unpaid', 'partial', 'overdue'], true);
@endphp
<div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6" id="text-messages">
    <div class="p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">SMS &amp; WhatsApp</h3>
            @can('send invoices')
                @if($canRemind && $mobile)
                    <form action="{{ route('invoices.messages.store', $invoice) }}" method="POST" class="flex items-center gap-2">
                        @csrf
                        <label for="reminder-channel" class="sr-only">Send by</label>
                        <select name="channel" id="reminder-channel" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm">
                            <option value="sms" @disabled($invoice->customer->sms_opt_out)>SMS{{ $invoice->customer->sms_opt_out ? ' (opted out)' : '' }}</option>
                            <option value="whatsapp" @disabled($invoice->customer->whatsapp_opt_out)>WhatsApp{{ $invoice->customer->whatsapp_opt_out ? ' (opted out)' : '' }}</option>
                        </select>
                        <button type="submit" class="btn-primary">Send reminder</button>
                    </form>
                @endif
            @endcan
        </div>
        @if($canRemind && ! $mobile)
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">To send a reminder by SMS or WhatsApp, add the customer's mobile number on
                <a href="{{ route('customers.edit', $invoice->customer) }}" class="text-brand-600 dark:text-brand-300 hover:underline">their page</a>.</p>
        @endif
        @include('settings.messaging._list', ['messages' => $invoiceMessages, 'showCustomer' => false])
    </div>
</div>
