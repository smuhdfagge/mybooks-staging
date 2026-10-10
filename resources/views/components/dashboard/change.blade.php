{{--
    Change against the compare period: "▲ 9.6% vs 1–10 Sep", or with
    :amount="true" the money difference: "▲ +₦2,988 since 30 Sep". $upIsGood says
    which way is good (income up: good; expenses up: worth a look).
--}}
@props(['now' => 0, 'before' => null, 'upIsGood' => true, 'against' => null, 'amount' => false])
@php
    $now = (float) $now;
    $text = null;
    $tone = 'text-gray-600 dark:text-gray-400';
    $arrow = '';
    if ($before !== null) {
        $before = (float) $before;
        if ($amount) {
            $diff = round($now - $before, 2);
            $up = $diff >= 0;
            $arrow = abs($diff) < 0.5 ? '' : ($up ? '▲' : '▼');
            $text = abs($diff) < 0.5 ? 'No change' : ($up ? '+' : '−').\App\Support\Money::whole(abs($diff));
            if ($arrow !== '') {
                $tone = $up === $upIsGood ? 'text-green-700 dark:text-green-300' : 'text-amber-700 dark:text-amber-300';
            }
        } elseif (abs($before) < 0.005) {
            $text = abs($now) < 0.005 ? 'No change' : 'Nothing'.($against ? ' in '.$against : ' before');
            $against = null;
        } else {
            $pct = ($now - $before) / abs($before) * 100;
            $up = $pct >= 0;
            $arrow = abs($pct) < 0.05 ? '' : ($up ? '▲' : '▼');
            $text = (abs($pct) < 0.05 ? 'No change' : ($up ? '+' : '−').number_format(abs($pct), abs($pct) < 10 ? 1 : 0).'%');
            if ($arrow !== '') {
                $good = $up === $upIsGood;
                $tone = $good ? 'text-green-700 dark:text-green-300' : 'text-amber-700 dark:text-amber-300';
            }
        }
    }
@endphp
@if ($text)
    <p {{ $attributes->merge(['class' => "text-sm font-medium {$tone}"]) }}>
        @if ($arrow)<span aria-hidden="true">{{ $arrow }}</span><span class="sr-only">{{ $arrow === '▲' ? 'Up' : 'Down' }}</span>@endif
        {{ $text }}@if ($against) <span class="font-normal">{{ str_starts_with($against, 'since') ? $against : 'vs '.$against }}</span>@endif
    </p>
@endif
