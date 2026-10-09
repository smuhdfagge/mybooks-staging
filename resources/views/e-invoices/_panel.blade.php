{{--
    E-invoice panel for an invoice or a credit note (session 18): status with
    NRS, Send / Retry, IRN, QR and any error. Pass the document as $document.
    Shown only when the feature is on, the business has switched
    e-invoicing on, the person may view e-invoices, and the document is posted.
--}}
@php
    $isInvoice = $document instanceof \App\Models\Invoice;
    $eiSettings = \App\Models\EInvoiceSetting::forTenant($document->tenant_id);
    $eiPosted = $isInvoice ? ! in_array($document->status, ['draft', 'cancelled'], true) : in_array($document->status, ['open', 'closed'], true);
@endphp
@if(config('mybooks.features.e_invoicing') && $eiSettings->enabled && $eiPosted && auth()->user()->can('view e-invoices'))
    @php
        $eiState = app(\App\Services\EInvoicing\EInvoiceDrivers::class)->state($eiSettings);
        $eiLive = in_array($eiState, ['ready', 'simulated'], true);
        $sub = \App\Models\EInvoiceSubmission::forDocument($document);
        $st = $sub?->status ?? 'not_submitted';
        $stEnum = \App\Enums\EInvoiceStatus::tryFrom($st);
        $payloads = app(\App\Services\EInvoicing\PayloadBuilder::class);
        $kind = $payloads->kind($document);
        $mustReport = $payloads->mustReport($document);
        $route = $isInvoice ? route('e-invoices.invoice.submit', $document) : route('e-invoices.credit-note.submit', $document);
    @endphp
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6" id="e-invoice" data-testid="e-invoice-panel">
        <div class="p-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">E-invoice (NRS)</h3>
                    <x-status-badge :status="$st" :label="$stEnum?->label()" data-testid="e-invoice-status" />
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ strtoupper($kind) }}</span>
                </div>
                @can('submit e-invoices')
                    @if($stEnum?->canSubmit() && $eiLive)
                        <form action="{{ $route }}" method="POST">
                            @csrf
                            <button type="submit" class="btn-primary">{{ $st === 'not_submitted' ? 'Send to NRS' : 'Retry' }}</button>
                        </form>
                    @elseif($st === 'pending' && $sub)
                        <form action="{{ route('e-invoices.check', $sub) }}" method="POST">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-xs font-semibold uppercase tracking-widest">Check with NRS</button>
                        </form>
                    @endif
                @endcan
            </div>

            @unless($eiLive)
                <p class="mt-3 text-sm text-yellow-800 dark:text-yellow-200" data-testid="not-set-up">Not set up yet: {{ $eiState === 'needs_address' ? 'the MyBooks team still has to add the NRS address.' : 'add your NRS keys in the e-invoicing settings.' }} Nothing is sent until then.</p>
            @endunless

            @if($st === 'accepted' && $sub)
                <div class="mt-4 flex flex-col sm:flex-row gap-4 sm:items-start">
                    @if($qr = $sub->qrSrc())
                        <img src="{{ $qr }}" alt="NRS QR code for {{ $sub->irn }}" width="140" height="140" class="h-36 w-36 shrink-0 rounded border border-gray-200 bg-white p-1" data-testid="e-invoice-qr">
                    @endif
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm min-w-0">
                        <div class="sm:col-span-2 min-w-0"><dt class="text-gray-500 dark:text-gray-400">IRN</dt><dd class="font-mono text-gray-900 dark:text-gray-100 break-all" data-testid="e-invoice-irn">{{ $sub->irn }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Accepted</dt><dd class="text-gray-900 dark:text-gray-100">{{ $sub->accepted_at?->format('d M Y, H:i') }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Environment</dt><dd class="text-gray-900 dark:text-gray-100">{{ ucfirst((string) $sub->environment) }}</dd></div>
                        @if($sub->csid)<div class="sm:col-span-2 min-w-0"><dt class="text-gray-500 dark:text-gray-400">NRS stamp (CSID)</dt><dd class="font-mono text-xs text-gray-700 dark:text-gray-300 break-all">{{ \Illuminate\Support\Str::limit($sub->csid, 90) }}</dd></div>@endif
                    </dl>
                </div>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">NRS has cleared this {{ $isInvoice ? 'invoice' : 'credit note' }}, so it can no longer be changed or cancelled. {{ $isInvoice ? 'To correct it, issue a credit note.' : '' }}</p>
            @elseif($st === 'pending')
                <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">Sent to NRS, waiting for its answer. MyBooks asks again every hour; you can also use "Check with NRS".</p>
            @elseif($st === 'rejected')
                <p class="mt-3 text-sm text-red-700 dark:text-red-300" data-testid="e-invoice-error">{{ $sub->last_error }}</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Fix what NRS points out{{ $isInvoice ? ' (edit the invoice or the customer)' : '' }}, then press Retry.</p>
            @elseif($st === 'failed')
                <p class="mt-3 text-sm text-red-700 dark:text-red-300" data-testid="e-invoice-error">{{ $sub->last_error }}</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    @if($sub->next_retry_at) MyBooks will try again after {{ $sub->next_retry_at->format('d M, H:i') }}, or press Retry now.
                    @else Automatic retries have stopped after {{ $sub->attempts }} tries. Press Retry when NRS is back.@endif
                </p>
            @else
                @if($sub?->last_error)<p class="mt-3 text-sm text-red-700 dark:text-red-300" data-testid="e-invoice-error">{{ $sub->last_error }}</p>@endif
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    @if($kind === 'b2b') This customer has a TIN, so NRS must clear the {{ $isInvoice ? 'invoice' : 'credit note' }} before it is valid.
                    @elseif($mustReport) This customer has no TIN (B2C) and the amount is above ₦{{ number_format((float) config('mybooks.einvoicing.b2c_threshold')) }}, so it must be reported to NRS within {{ config('mybooks.einvoicing.b2c_report_hours') }} hours.
                    @else This customer has no TIN (B2C) and the amount is under ₦{{ number_format((float) config('mybooks.einvoicing.b2c_threshold')) }}, so NRS does not need it. You can still send it.@endif
                </p>
            @endif
        </div>
    </div>
@endif
