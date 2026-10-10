{{--
    The table frame (tables plan T1). Every table in MyBooks uses it.

    <x-table caption="Invoices">
        <x-slot name="head"> <x-table.th>…</x-table.th> … </x-slot>
        <tr>…</tr>
        <x-slot name="foot"> <tr>…totals…</tr> </x-slot>
    </x-table>

    On a list, add class="hidden md:block" and give phones <x-table.card>s.
--}}
@props(['caption' => null])
<div {{ $attributes->merge(['class' => 'tbl-wrap']) }}>
    <div class="tbl-scroll">
        <table class="tbl">
            @if ($caption)<caption class="sr-only">{{ $caption }}</caption>@endif
            <thead><tr>{{ $head }}</tr></thead>
            <tbody>{{ $slot }}</tbody>
            @isset($foot)<tfoot>{{ $foot }}</tfoot>@endisset
        </table>
    </div>
    {{ $after ?? '' }}
</div>
