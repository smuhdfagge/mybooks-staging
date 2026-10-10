{{-- Small trend line drawn on the server (no script needed). $values: numbers, oldest first. --}}
@props(['values' => [], 'tone' => 'income'])
@php
    $vals = array_values(array_map('floatval', $values));
    $n = count($vals);
    $min = $n ? min($vals) : 0;
    $max = $n ? max($vals) : 0;
    $span = ($max - $min) ?: 1;
    $pts = [];
    foreach ($vals as $i => $v) {
        $x = $n > 1 ? round($i * 100 / ($n - 1), 2) : 50;
        $y = round(26 - (($v - $min) / $span) * 22, 2);
        $pts[] = "{$x},{$y}";
    }
    $last = end($pts) ?: '0,0';
    [$lx, $ly] = explode(',', $last);
    $colour = [
        'income' => 'text-[#2F6AAE] dark:text-[#5B8FD3]',
        'expense' => 'text-[#C0841A] dark:text-[#BF8020]',
        'profit' => 'text-[#374151] dark:text-[#D1D5DB]',
    ][$tone] ?? 'text-gray-500';
@endphp
@if ($n > 1)
    <svg {{ $attributes->merge(['class' => "h-8 w-24 flex-none {$colour}"]) }} viewBox="-3 -3 106 34" preserveAspectRatio="none" aria-hidden="true" focusable="false">
        <polyline points="{{ implode(' ', $pts) }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
        <circle cx="{{ $lx }}" cy="{{ $ly }}" r="2.5" fill="currentColor"/>
    </svg>
@endif
