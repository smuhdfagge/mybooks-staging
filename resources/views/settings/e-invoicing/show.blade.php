<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">E-invoicing (NRS)</h2>
            <a href="{{ route('e-invoices.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">See e-invoices</a>
        </div>
    </x-slot>

    @php
        $stateText = [
            'ready' => ['green', 'Ready. Documents are sent to NRS ('.ucfirst($settings->environment).').'],
            'simulated' => ['blue', 'Test mode on this server: documents are marked accepted here and nothing is sent to NRS.'],
            'needs_keys' => ['yellow', 'Not set up yet. Add your NRS keys below. Until then nothing is sent to NRS.'],
            'needs_address' => ['yellow', 'Not set up yet. Your keys are saved, but the MyBooks team still has to add the NRS address for the '.$settings->environment.' environment. Until then nothing is sent.'],
        ][$state];
        $colours = [
            'green' => 'border-green-300 bg-green-50 text-green-800 dark:bg-green-900/30 dark:border-green-700 dark:text-green-200',
            'blue' => 'border-blue-300 bg-blue-50 text-blue-800 dark:bg-blue-900/30 dark:border-blue-700 dark:text-blue-200',
            'yellow' => 'border-yellow-300 bg-yellow-50 text-yellow-800 dark:bg-yellow-900/30 dark:border-yellow-700 dark:text-yellow-200',
        ];
        $secretHint = fn ($value) => $value ? 'Saved ('.\App\Models\EInvoiceSetting::mask($value).'). Leave empty to keep it.' : 'Not saved yet.';
    @endphp

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-error-summary />

            <div class="rounded-lg border p-4 text-sm {{ $colours[$stateText[0]] }}" data-testid="{{ in_array($state, ['ready', 'simulated'], true) ? 'ready' : 'not-set-up' }}">
                <p class="font-medium">{{ $stateText[1] }}</p>
                @if($settings->enabled === false)<p class="mt-1">E-invoicing is switched off for your business.</p>@endif
            </div>

            <x-card class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">What this does</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">The Nigeria Revenue Service (NRS) is bringing in e-invoicing. A business customer's invoice (one with a TIN) is sent to NRS, which returns an invoice reference number (IRN) and a QR code; the invoice only counts once NRS has accepted it. Sales to people without a TIN above ₦{{ number_format((float) config('mybooks.einvoicing.b2c_threshold')) }} are reported to NRS within {{ config('mybooks.einvoicing.b2c_report_hours') }} hours. MyBooks sends the invoice and credit note details for you and prints the IRN and QR code on the invoice. Sending never changes your accounts.</p>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Dates vary by business size (reported: large businesses from 1 July 2026, small businesses from 1 July 2027). Ask your tax adviser when it applies to you.</p>
                <p class="mt-2 text-sm {{ filled($tin) ? 'text-gray-600 dark:text-gray-400' : 'text-red-700 dark:text-red-300' }}">
                    Your business TIN:
                    @if(filled($tin))<strong class="font-mono">{{ $tin }}</strong> (from your <a href="{{ route('settings.company') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">company profile</a>).
                    @else not set. <a href="{{ route('settings.company') }}" class="underline">Add it in your company profile</a> before sending anything.@endif
                </p>
            </x-card>

            <form action="{{ route('settings.e-invoicing.update') }}" method="POST" class="space-y-6" autocomplete="off">
                @csrf
                @method('PUT')

                <x-card class="p-6 space-y-5">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Settings</h3>

                    <input type="hidden" name="enabled" value="0">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200">
                        <input type="checkbox" name="enabled" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700" @checked(old('enabled', $settings->enabled)) @disabled(! $canManage)>
                        Use e-invoicing in my business
                    </label>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div><x-field name="environment" label="Environment" type="select" help="Practise in the sandbox first. Only choose Live when NRS has cleared you to." :disabled="! $canManage">
                            <option value="sandbox" @selected(old('environment', $settings->environment) === 'sandbox')>Sandbox (practice)</option>
                            <option value="live" @selected(old('environment', $settings->environment) === 'live')>Live</option>
                        </x-field></div>
                        <div><x-field name="submit_mode" label="When to send" type="select" help="With manual, you press Send to NRS on each invoice or from the E-invoices list." :disabled="! $canManage">
                            <option value="manual" @selected(old('submit_mode', $settings->submit_mode) === 'manual')>Manual only</option>
                            <option value="auto" @selected(old('submit_mode', $settings->submit_mode) === 'auto')>Automatically when an invoice is sent or a credit note is posted</option>
                        </x-field></div>
                    </div>
                </x-card>

                <x-card class="p-6 space-y-5">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Your NRS keys</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">From your NRS MBS dashboard. They are kept encrypted and are never shown again in full. Leave a key empty to keep the one saved.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div><x-field name="service_id" label="Service ID" :value="old('service_id', $settings->service_id)" maxlength="8" help="8 letters or digits. It is part of every IRN." :disabled="! $canManage" /></div>
                        <div><x-field name="business_id" label="Business ID" :value="old('business_id', $settings->business_id)" :disabled="! $canManage" /></div>
                        <div><x-field name="api_key" label="API key" type="password" autocomplete="new-password" :help="$secretHint($settings->api_key)" :disabled="! $canManage" /></div>
                        <div><x-field name="api_secret" label="API secret" type="password" autocomplete="new-password" :help="$secretHint($settings->api_secret)" :disabled="! $canManage" /></div>
                    </div>
                    <x-field name="public_key" label="Public key" type="textarea" rows="3" :help="$secretHint($settings->public_key)" :disabled="! $canManage" placeholder="Paste the public key from the dashboard" />
                    <x-field name="certificate" label="Certificate" type="textarea" rows="3" :help="$secretHint($settings->certificate)" :disabled="! $canManage" placeholder="Paste the certificate from the dashboard" />

                    @if($canManage && $settings->hasKeys())
                        <input type="hidden" name="clear_keys" value="0">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" name="clear_keys" value="1" class="rounded border-gray-300 text-red-600 focus:ring-red-500 dark:border-gray-600 dark:bg-gray-700">
                            Remove all saved keys
                        </label>
                    @endif

                    <p class="text-xs text-gray-500 dark:text-gray-400">Sandbox and live keys are different. When you change environment, enter that environment's keys.</p>
                </x-card>

                @if($canManage)
                    <div class="flex justify-end"><button type="submit" class="btn-primary">Save</button></div>
                @endif
            </form>

            @if($canManage)
                <x-card class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Test the connection</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Asks NRS a simple question to check the saved keys. Nothing is sent from your books.</p>
                    @if($settings->last_tested_at)
                        <p class="mt-2 text-sm {{ $settings->last_test_ok ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }}" data-testid="last-test">
                            Last test {{ $settings->last_tested_at->format('d M Y, H:i') }}: {{ $settings->last_test_message }}
                        </p>
                    @endif
                    <form action="{{ route('settings.e-invoicing.test') }}" method="POST" class="mt-4">
                        @csrf
                        <button type="submit" class="btn-primary" @disabled(! in_array($state, ['ready', 'simulated'], true))>Test connection</button>
                        @unless(in_array($state, ['ready', 'simulated'], true))<span class="ml-2 text-sm text-gray-500 dark:text-gray-400">Save your keys first.</span>@endunless
                    </form>
                </x-card>
            @endif
        </div>
    </div>
</x-app-layout>
