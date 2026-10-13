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
        'paid' => 'badge-success',
        'unpaid' => 'badge-warning',
        'partial' => 'badge-warning',
        'overdue' => 'badge-danger',
        'posted' => 'badge-success',
        'inactive' => 'badge-muted',
        'confirmed' => 'badge-info',
        'processing' => 'badge-warning',
        'invoiced' => 'badge-info',
        'pending_approval' => 'badge-warning',
        'approved' => 'badge-info',
        'partially_received' => 'badge-warning',
        'billed' => 'badge-info',
        'paused' => 'badge-warning',
        'stopped' => 'badge-muted',
    ];
    $labels = ['partial' => 'Part paid', 'pending_approval' => 'Waiting for approval', 'partially_received' => 'Part received'];
@endphp

<span {{ $attributes->merge(['class' => 'badge '.($colours[$status] ?? $colours['draft'])]) }}>
    {{ $label ?? $labels[$status] ?? ucfirst(str_replace('_', ' ', $status)) }}
</span>
