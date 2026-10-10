{{--
    The comparison table (tables plan T6): one row per figure, the two
    periods, the change and the change in percent. A better change is
    green, a worse one red.
    $rows: [ ['label' => 'Income', 'key' => 'revenue', 'row' => null|'sub'|'total'|'section', 'out' => false, 'pct' => false] ]
--}}
@php
    $cur = $periodData['current'] ?? null;
    $prev = $periodData['previous'] ?? null;
@endphp
<x-table :caption="$caption">
    <x-slot name="head">
        <x-table.th>Figure</x-table.th>
        <x-table.th num>{{ $cur['label'] ?? 'This period' }}</x-table.th>
        <x-table.th num>{{ $prev['label'] ?? 'Last period' }}</x-table.th>
        <x-table.th num class="hidden sm:table-cell">Change</x-table.th>
        <x-table.th num>Change %</x-table.th>
    </x-slot>
    @foreach ($rows as $r)
        @if (($r['row'] ?? null) === 'section')
            <tr class="rpt-section"><td colspan="5">{{ $r['label'] }}</td></tr>
            @continue
        @endif
        @php
            $k = $r['key'];
            $sign = ! empty($r['out']) ? -1 : 1;
            $c = $changes[$k] ?? null;
            $pct = ! empty($r['pct']);
            $tone = ($c['improved'] ?? null) === null ? '' : ($c['improved'] ? 'text-green-800 dark:text-green-300' : 'text-red-700 dark:text-red-300');
            $showC = fn ($v) => $pct ? number_format((float) $v, 1).'%' : \App\Support\Figure::show($sign * (float) $v);
        @endphp
        <tr @class(['rpt-sub' => ($r['row'] ?? null) === 'sub', 'rpt-total' => ($r['row'] ?? null) === 'total'])>
            <td class="rpt-wrap {{ empty($r['row']) ? 'rpt-in1' : '' }}">{{ $r['label'] }}</td>
            <td class="num">{{ $showC($cur[$k] ?? 0) }}</td>
            <td class="num">{{ $showC($prev[$k] ?? 0) }}</td>
            <td class="num hidden sm:table-cell {{ $c && abs($c['difference']) >= 0.005 ? $tone : 'tbl-zero' }}">
                @if (! $c || abs($c['difference']) < 0.005)
                    —
                @else
                    {{ $c['difference'] > 0 ? '+' : '-' }}{{ $pct ? number_format(abs($c['difference']), 1).' pts' : number_format(abs($c['difference']), 2) }}
                @endif
            </td>
            <td class="num {{ $c && ! $pct && abs($c['difference']) >= 0.005 ? $tone : 'tbl-zero' }}">
                @if (! $c || $pct || abs($c['difference']) < 0.005)
                    —
                @elseif (abs((float) $c['previous']) < 0.005)
                    new
                @else
                    {{ $c['percentChange'] > 0 ? '+' : '' }}{{ number_format($c['percentChange'], 1) }}%
                @endif
            </td>
        </tr>
    @endforeach
</x-table>
