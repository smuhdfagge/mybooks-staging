{{--
    Coloured status label for documents: <x-status-badge :status="$quotation->status" />
    Colours follow the rebrand rule (badge-* in app.css): green done, red
    problem, amber waiting, navy open or information, grey draft or expired.
--}}
@props(['status', 'label' => null])

@php
    $colours = [
        'draft' => 'badge-muted',
        'sent' => 'badge-info',
        'open' => 'badge-info',
        'dispatched' => 'badge-info',
        'in_transit' => 'badge-warning',
        'accepted' => 'badge-success',
        'received' => 'badge-success',
        'delivered' => 'badge-success',
        'completed' => 'badge-success',
        'closed' => 'badge-success',
        'converted' => 'badge-info',
        'expired' => 'badge-muted',
        'rejected' => 'badge-danger',
        'active' => 'badge-info',
        'cancelled' => 'badge-danger',
        'void' => 'badge-danger',
        'reversed' => 'badge-danger',
        'success' => 'badge-success',
        'failed' => 'badge-danger',
        'queued' => 'badge-warning',
        'sending' => 'badge-warning',
        'pending' => 'badge-warning',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'badge '.($colours[$status] ?? $colours['draft'])]) }}>
    {{ $label ?? ucwords(str_replace('_', ' ', $status)) }}
</span>
