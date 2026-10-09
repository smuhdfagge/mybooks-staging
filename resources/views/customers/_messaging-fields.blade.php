{{--
    SMS / WhatsApp switches on the customer form (session 16).
    $party: the customer being edited (null when creating).
--}}
@if(\App\Http\Middleware\EnsureFeatureEnabled::enabled('sms_whatsapp'))
    @php $mobile = \App\Support\PhoneNumber::normalise($party?->phone); @endphp
    <div class="mb-8">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">SMS &amp; WhatsApp</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
            @if($party && $party->phone)
                @if($mobile)
                    Reminders and receipts go to {{ $mobile }}, if switched on in Settings &gt; SMS &amp; WhatsApp.
                @else
                    The phone number above is not a mobile number, so no SMS or WhatsApp can go to it.
                @endif
            @else
                Reminders and receipts go to the phone number above (a Nigerian mobile like 0803 123 4567, or + and the country code).
            @endif
        </p>
        <div class="flex flex-col sm:flex-row gap-3 sm:gap-8">
            @foreach(['sms_opt_out' => 'Does not want SMS messages', 'whatsapp_opt_out' => 'Does not want WhatsApp messages'] as $field => $label)
                <div>
                    <input type="hidden" name="{{ $field }}" value="0">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="{{ $field }}" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700" @checked(old($field, $party?->{$field}))>
                        {{ $label }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>
@endif
