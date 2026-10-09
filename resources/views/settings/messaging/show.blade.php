<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">SMS &amp; WhatsApp</h2>
            <a href="{{ route('settings.messaging.messages') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">All messages sent</a>
        </div>
    </x-slot>

    @php
        $titles = [
            'invoice_sent' => ['Invoice sent', 'When you send an invoice by email, also send a short text.'],
            'payment_reminder' => ['Reminder before the due date', null],
            'overdue' => ['Overdue reminders', null],
            'payment_received' => ['Payment received', 'A thank-you receipt when you record a payment on an invoice. Goes at any hour.'],
        ];
        $timing = \App\Models\NotificationSetting::getForTenant(auth()->user()->tenant_id);
        $overdueDays = app(\App\Services\Messaging\CustomerMessenger::class)->overdueDays(max(1, (int) $timing->overdue_reminder_days));
        $titles['payment_reminder'][1] = 'Sent '.$timing->payment_reminder_days_before.' '.\Illuminate\Support\Str::plural('day', $timing->payment_reminder_days_before).' before the due date (the same timing as the email reminders).';
        $titles['overdue'][1] = 'Sent '.implode(', ', $overdueDays).' days after the due date, then no more. Uses the email "Remind every" setting.';
        $canEdit = auth()->user()->can('edit settings');
    @endphp

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @unless($usage['sms']['live'] && $usage['whatsapp']['live'])
                <div class="rounded-lg border border-yellow-300 bg-yellow-50 dark:bg-yellow-900/30 dark:border-yellow-700 p-4 text-sm text-yellow-800 dark:text-yellow-200" data-testid="not-set-up">
                    @if(! $usage['sms']['live'] && ! $usage['whatsapp']['live'])
                        <p class="font-medium">Not set up yet — the MyBooks team needs to add SMS keys.</p>
                        <p class="mt-1">You can choose your messages now; nothing is sent until then.</p>
                    @else
                        <p class="font-medium">{{ $usage['sms']['live'] ? 'WhatsApp' : 'SMS' }} is not set up yet — the MyBooks team needs to add the keys. Only {{ $usage['sms']['live'] ? 'SMS' : 'WhatsApp' }} messages are sent for now.</p>
                    @endif
                </div>
            @endunless

            {{-- This month's use of the plan's allowance --}}
            <x-card class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">This month</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Your plan includes a number of SMS and WhatsApp messages each month. A long SMS counts once for every 160 characters. When they run out, reminders go by email only until next month.</p>
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4" data-testid="usage">
                    @foreach(['sms' => 'SMS', 'whatsapp' => 'WhatsApp'] as $channel => $label)
                        @php
                            $u = $usage[$channel];
                            $pct = $u['limit'] > 0 ? min(100, round($u['used'] / $u['limit'] * 100)) : 100;
                            $out = $u['used'] >= $u['limit'];
                        @endphp
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <div class="flex items-baseline justify-between">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</span>
                                <span class="text-sm text-gray-900 dark:text-gray-100"><span class="text-lg font-semibold">{{ number_format($u['used']) }}</span> of {{ number_format($u['limit']) }}</span>
                            </div>
                            <div class="mt-2 h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                <div class="h-2 rounded-full {{ $out ? 'bg-red-500' : ($pct >= 80 ? 'bg-yellow-500' : 'bg-indigo-600') }}" style="width: {{ $pct }}%"></div>
                            </div>
                            <p class="mt-2 text-xs {{ $out ? 'text-red-600 dark:text-red-400 font-medium' : 'text-gray-500 dark:text-gray-400' }}">
                                @if($u['limit'] === 0)
                                    Your plan doesn't include {{ $label }} messages.
                                @else
                                    {{ $u['summary'] }}@if($out) Reminders go by email only until next month.@endif
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            </x-card>

            <form action="{{ route('settings.messaging.update') }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')

                @foreach($titles as $type => [$title, $help])
                    @php $initial = old($type.'_text', $settings->text($type)); @endphp
                    <x-card class="p-6" id="type-{{ $type }}">
                        <div x-data="smsPreview(@js($initial), @js($type === 'overdue' ? $overdueSample : $sample))">
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $title }}</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $help }}</p>
                                </div>
                                <div class="flex gap-4 shrink-0">
                                    @foreach(['sms' => 'SMS', 'whatsapp' => 'WhatsApp'] as $channel => $label)
                                        <input type="hidden" name="{{ $type }}_{{ $channel }}" value="0">
                                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                            <input type="checkbox" name="{{ $type }}_{{ $channel }}" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700"
                                                @checked(old($type.'_'.$channel, $settings->channelOn($type, $channel))) @disabled(! $canEdit || ! $canSetUp)>
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-field :name="$type.'_text'" :id="$type.'_text'" label="SMS wording" type="textarea" rows="4" :value="$initial"
                                        x-model="text" maxlength="459" :disabled="! $canEdit" />
                                    <p class="form-help">Start with {business}: the SMS comes from "{{ config('services.termii.sender_id') }}", so this tells your customer who it is from.
                                        <button type="button" class="text-indigo-600 dark:text-indigo-400 hover:underline" @click="text = @js($defaults[$type])">Use the standard wording</button></p>
                                </div>
                                <div>
                                    <p class="form-label">Preview</p>
                                    <div class="rounded-2xl rounded-tl-sm bg-gray-100 dark:bg-gray-700 p-3 text-sm text-gray-900 dark:text-gray-100 whitespace-pre-line break-words" data-testid="preview-{{ $type }}" x-text="rendered"></div>
                                    <p class="mt-1 text-xs" :class="pages > 1 ? 'text-yellow-700 dark:text-yellow-300' : 'text-gray-500 dark:text-gray-400'" data-testid="count-{{ $type }}">
                                        <span x-text="length"></span> characters · <span x-text="pages === 1 ? '1 SMS' : 'counts as ' + pages + ' SMS'"></span>
                                        <span x-show="unicode" x-cloak> · special characters make each SMS hold only 70</span>
                                    </p>
                                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400"><span class="font-medium">WhatsApp</span> uses fixed, approved wording: “{{ $whatsappPreview[$type] }}”</p>
                                </div>
                            </div>
                        </div>
                    </x-card>
                @endforeach

                <x-card class="p-6">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">You can use</h3>
                    <dl class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1 text-sm">
                        @foreach($placeholders as $code => $meaning)
                            <div class="flex gap-2"><dt class="font-mono text-indigo-700 dark:text-indigo-300">{{ $code }}</dt><dd class="text-gray-600 dark:text-gray-400">{{ $meaning }}</dd></div>
                        @endforeach
                    </dl>
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Messages only go to customers with a mobile number who haven't opted out (on the customer's page). No reminders are sent between 9pm and 7am; they wait until 7am. Emails are not affected by these settings.</p>
                </x-card>

                @if($canEdit)
                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary">Save</button>
                    </div>
                @endif
            </form>

            @if($canEdit)
                <x-card class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Send a test</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Sends a short test message to your own phone. It counts towards this month's allowance.</p>
                    <form action="{{ route('settings.messaging.test') }}" method="POST" class="mt-4 flex flex-col sm:flex-row sm:items-end gap-3">
                        @csrf
                        <div class="sm:flex-1">
                            <x-field name="test_phone" label="Your mobile number" type="tel" :value="old('test_phone', auth()->user()->phone)" placeholder="0803 123 4567" required />
                        </div>
                        <div>
                            <x-field name="test_channel" label="By" type="select">
                                <option value="sms" @selected(old('test_channel') !== 'whatsapp')>SMS</option>
                                <option value="whatsapp" @selected(old('test_channel') === 'whatsapp')>WhatsApp</option>
                            </x-field>
                        </div>
                        <button type="submit" class="btn-primary sm:mb-0.5">Send test</button>
                    </form>
                </x-card>
            @endif

            <x-card class="p-6">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Latest messages</h3>
                    <a href="{{ route('settings.messaging.messages') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">See all</a>
                </div>
                @include('settings.messaging._list', ['messages' => $recent, 'showCustomer' => true])
            </x-card>
        </div>
    </div>

    @push('scripts')
    <script nonce="{{ app('csp-nonce') }}">
        // Live SMS preview with the page count (session 16). Same rules as
        // App\Support\SmsText: plain (GSM) text holds 160 a page (153 when
        // longer), anything else 70 (67).
        (function () {
            const basic = "@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
            const extended = "^{}\\[~]|€\f";
            const swaps = {'’': "'", '‘': "'", '‛': "'", '′': "'", '`': "'", '“': '"', '”': '"', '″': '"', '–': '-', '—': '-', '−': '-', '…': '...', '•': '-', '₦': 'N', ' ': ' ', '\t': ' '};
            const clean = (t) => Array.from(t).map((c) => swaps[c] ?? c).join('').replace(/ {2,}/g, ' ').trim();
            window.smsPreview = function (initial, sample) {
                return {
                    text: initial,
                    get rendered() {
                        let t = this.text || '';
                        for (const [key, value] of Object.entries(sample)) {
                            t = t.split('{' + key + '}').join(value);
                        }
                        return clean(t);
                    },
                    get unicode() {
                        return Array.from(this.rendered).some((c) => !basic.includes(c) && !extended.includes(c));
                    },
                    get length() {
                        const chars = Array.from(this.rendered);
                        if (this.unicode) {
                            return chars.reduce((n, c) => n + (c.codePointAt(0) > 0xFFFF ? 2 : 1), 0);
                        }
                        return chars.reduce((n, c) => n + (extended.includes(c) ? 2 : 1), 0);
                    },
                    get pages() {
                        const [single, multi] = this.unicode ? [70, 67] : [160, 153];
                        return this.length <= single ? 1 : Math.ceil(this.length / multi);
                    },
                };
            };
        })();
    </script>
    @endpush
</x-app-layout>
