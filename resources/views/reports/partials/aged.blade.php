{{--
    Aged receivables / payables (tables plan T6). Ages are counted from the
    "as at" date, not today. One table by customer (or supplier) in age
    bands, then every open document.
    Needs: $docs, $asOf, $party ('customer'|'vendor'), $partyName, $docName, $docRoute, $numberField, $dateField
--}}
@php
    $at = \Carbon\Carbon::parse($asOf)->startOfDay();
    $bands = ['current' => 'Not yet due', 'd30' => '1–30 days', 'd60' => '31–60', 'd90' => '61–90', 'd120' => '91–120', 'over' => 'Over 120'];
    $band = function ($doc) use ($at) {
        $late = $doc->due_date ? (int) \Carbon\Carbon::parse($doc->due_date)->startOfDay()->diffInDays($at, false) : 0;

        return match (true) { $late <= 0 => 'current', $late <= 30 => 'd30', $late <= 60 => 'd60', $late <= 90 => 'd90', $late <= 120 => 'd120', default => 'over' };
    };
    $rows = $docs->groupBy(fn ($d) => $d->{$party.'_id'})->map(function ($group) use ($band, $bands, $party) {
        $r = ['who' => $group->first()->{$party}, 'total' => 0.0] + array_fill_keys(array_keys($bands), 0.0);
        foreach ($group as $d) { $r[$band($d)] += (float) $d->balance_due; $r['total'] += (float) $d->balance_due; }
        $r['overdue'] = $r['total'] - $r['current'];

        return $r;
    })->sortByDesc('total')->values();
    $sum = fn ($k) => $rows->sum($k);
@endphp

<x-report.stats :cols="4">
    <x-report.stat label="Total open" :value="\App\Support\Figure::show($sum('total'))" :hint="$docs->count().' '.\Illuminate\Support\Str::plural($docName, $docs->count())" />
    <x-report.stat label="Not yet due" :value="\App\Support\Figure::show($sum('current'))" />
    <x-report.stat label="Overdue" :value="\App\Support\Figure::show($sum('overdue'))" :tone="$sum('overdue') > 0 ? 'bad' : null" />
    <x-report.stat label="Over 90 days" :value="\App\Support\Figure::show($sum('d120') + $sum('over'))" :tone="($sum('d120') + $sum('over')) > 0 ? 'bad' : null" />
</x-report.stats>

@if ($docs->isEmpty())
    <div class="tbl-wrap"><x-table.empty :title="'Nothing open at this date'" :text="'No '.\Illuminate\Support\Str::plural($docName).' had money left to pay on '.$at->format('j M Y').'.'" /></div>
@else
    <section class="space-y-2" aria-labelledby="by-party">
        <h3 id="by-party" class="text-base font-semibold text-gray-900 dark:text-white">By {{ $partyName }}</h3>
        <x-table :caption="'By '.$partyName">
            <x-slot name="head">
                <x-table.th>{{ ucfirst($partyName) }}</x-table.th>
                @foreach ($bands as $k => $label)
                    <x-table.th num class="hidden md:table-cell">{{ $label }}</x-table.th>
                @endforeach
                <x-table.th num class="md:hidden">Overdue</x-table.th>
                <x-table.th num>Total</x-table.th>
            </x-slot>
            @foreach ($rows as $r)
                <tr>
                    <td class="rpt-wrap">
                        @if ($r['who'])
                            <a href="{{ route($party === 'customer' ? 'customers.show' : 'vendors.show', $r['who']) }}" class="tbl-link">{{ $r['who']->name }}</a>
                        @else
                            <span class="tbl-muted">Deleted {{ $partyName }}</span>
                        @endif
                    </td>
                    @foreach ($bands as $k => $label)
                        <td class="num hidden md:table-cell {{ \App\Support\Figure::tone($r[$k]) }} {{ $k !== 'current' && $r[$k] > 0 && in_array($k, ['d120', 'over'], true) ? 'tbl-late' : '' }}">@fig($r[$k])</td>
                    @endforeach
                    <td class="num md:hidden {{ $r['overdue'] > 0 ? 'tbl-late' : 'tbl-zero' }}">@fig($r['overdue'])</td>
                    <td class="num font-medium">@fig($r['total'])</td>
                </tr>
            @endforeach
            <x-slot name="foot">
                <tr>
                    <td>Total</td>
                    @foreach ($bands as $k => $label)
                        <td class="num hidden md:table-cell">@fig($sum($k))</td>
                    @endforeach
                    <td class="num md:hidden">@fig($sum('overdue'))</td>
                    <td class="num">@fig($sum('total'))</td>
                </tr>
            </x-slot>
        </x-table>
    </section>

    <section class="space-y-2" aria-labelledby="open-docs">
        <h3 id="open-docs" class="text-base font-semibold text-gray-900 dark:text-white">Open {{ \Illuminate\Support\Str::plural($docName) }}</h3>
        <x-table :caption="'Open '.\Illuminate\Support\Str::plural($docName)" class="hidden md:block">
            <x-slot name="head">
                <x-table.th>{{ ucfirst($docName) }}</x-table.th>
                <x-table.th>{{ ucfirst($partyName) }}</x-table.th>
                <x-table.th>Date</x-table.th>
                <x-table.th>Due</x-table.th>
                <x-table.th num>Total</x-table.th>
                <x-table.th num>Left to pay</x-table.th>
                <x-table.th>Age</x-table.th>
            </x-slot>
            @foreach ($docs as $doc)
                @php $late = $doc->due_date ? (int) \Carbon\Carbon::parse($doc->due_date)->startOfDay()->diffInDays($at, false) : 0; @endphp
                <tr>
                    <td><a href="{{ route($docRoute, $doc) }}" class="tbl-link">{{ $doc->{$numberField} }}</a></td>
                    <td class="rpt-wrap">{{ $doc->{$party}?->name ?? '—' }}</td>
                    <td class="tbl-muted">{{ \Carbon\Carbon::parse($doc->{$dateField})->format('j M Y') }}</td>
                    <td class="{{ $late > 0 ? 'tbl-late' : 'tbl-muted' }}">{{ $doc->due_date ? \Carbon\Carbon::parse($doc->due_date)->format('j M Y') : '—' }}</td>
                    <td class="num">@fig($doc->total)</td>
                    <td class="num font-medium">@fig($doc->balance_due)</td>
                    <td class="{{ $late > 0 ? 'tbl-late' : 'tbl-muted' }}">{{ $late > 0 ? $late.' '.\Illuminate\Support\Str::plural('day', $late).' late' : ($late === 0 ? 'Due today' : 'Due in '.abs($late).' '.\Illuminate\Support\Str::plural('day', abs($late))) }}</td>
                </tr>
            @endforeach
            <x-slot name="foot">
                <tr><td colspan="5">Total left to pay</td><td class="num">@fig($docs->sum('balance_due'))</td><td></td></tr>
            </x-slot>
        </x-table>
        <ul class="space-y-2 md:hidden" aria-label="Open {{ \Illuminate\Support\Str::plural($docName) }}">
            @foreach ($docs as $doc)
                @php $late = $doc->due_date ? (int) \Carbon\Carbon::parse($doc->due_date)->startOfDay()->diffInDays($at, false) : 0; @endphp
                <li>
                    <x-table.card :href="route($docRoute, $doc)" :title="$doc->{$party}?->name ?? $doc->{$numberField}" :amount="\App\Support\Money::format($doc->balance_due)"
                        :meta="$doc->{$numberField}.' · due '.($doc->due_date ? \Carbon\Carbon::parse($doc->due_date)->format('j M Y') : '—')" :tone="$late > 0 ? 'bad' : 'muted'">
                        @if ($late > 0)
                            <x-slot name="alert">{{ $late }} {{ \Illuminate\Support\Str::plural('day', $late) }} late</x-slot>
                        @endif
                    </x-table.card>
                </li>
            @endforeach
        </ul>
    </section>
@endif
