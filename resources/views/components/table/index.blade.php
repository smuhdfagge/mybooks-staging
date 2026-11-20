{{--
    The table frame (tables plan T1). Every table in MyBooks uses it.

    <x-table caption="Invoices">
        <x-slot name="head"> <x-table.th>…</x-table.th> … </x-slot>
        <tr>…</tr>
        <x-slot name="foot"> <tr>…totals…</tr> </x-slot>
    </x-table>

    On a list, add class="hidden md:block" and give phones <x-table.card>s.
    A line-item editor passes table-class="line-items": on a phone each row
    becomes a card (see app.css).
    A table that may scroll sideways where it has no links or buttons to
    reach it by (a public page) passes :focusable="true", so keyboard users
    can scroll it too.
    A line editor whose script adds rows passes body-id="…" for the script
    to find the <tbody> by (an id="…" goes on the wrapper div).
--}}
@props(['caption' => null, 'tableClass' => null, 'focusable' => false, 'bodyId' => null])
<div {{ $attributes->merge(['class' => 'tbl-wrap']) }}>
    <div class="tbl-scroll" @if ($focusable) tabindex="0" role="region" aria-label="{{ $caption ?? 'Table' }}" @endif>
        <table class="{{ trim('tbl '.$tableClass) }}">
            @if ($caption)<caption class="sr-only">{{ $caption }}</caption>@endif
            <thead><tr>{{ $head }}</tr></thead>
            <tbody @if ($bodyId) id="{{ $bodyId }}" @endif>{{ $slot }}</tbody>
            @isset($foot)<tfoot>{{ $foot }}</tfoot>@endisset
        </table>
    </div>
    {{ $after ?? '' }}
</div>
