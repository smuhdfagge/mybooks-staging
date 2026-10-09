{{-- "Not set up yet" notice for the e-invoices pages (session 18). --}}
@php
    $einvSettings = \App\Models\EInvoiceSetting::forTenant(auth()->user()->tenant_id);
    $einvState = app(\App\Services\EInvoicing\EInvoiceDrivers::class)->state($einvSettings);
@endphp
@if(! $einvSettings->enabled)
    <div class="rounded-lg border border-yellow-300 bg-yellow-50 dark:bg-yellow-900/30 dark:border-yellow-700 p-4 text-sm text-yellow-800 dark:text-yellow-200" data-testid="not-set-up">
        <p class="font-medium">E-invoicing is switched off for your business.</p>
        <p class="mt-1">Nothing is sent to NRS. @can('view e-invoices')<a href="{{ route('settings.e-invoicing') }}" class="underline">Open the settings</a> to set it up.@endcan</p>
    </div>
@elseif(! in_array($einvState, ['ready', 'simulated'], true))
    <div class="rounded-lg border border-yellow-300 bg-yellow-50 dark:bg-yellow-900/30 dark:border-yellow-700 p-4 text-sm text-yellow-800 dark:text-yellow-200" data-testid="not-set-up">
        <p class="font-medium">Not set up yet: {{ $einvState === 'needs_address' ? 'the MyBooks team still has to add the NRS address.' : 'add your NRS keys in the settings.' }}</p>
        <p class="mt-1">Until then nothing is sent to NRS. @can('view e-invoices')<a href="{{ route('settings.e-invoicing') }}" class="underline">Open the settings</a>.@endcan</p>
    </div>
@elseif($einvState === 'simulated')
    <div class="rounded-lg border border-brand-300 bg-brand-50 dark:bg-brand-900/30 dark:border-brand-700 p-4 text-sm text-brand-800 dark:text-brand-200" data-testid="simulated">
        Test mode: documents are marked accepted on this server and nothing is sent to NRS.
    </div>
@endif
