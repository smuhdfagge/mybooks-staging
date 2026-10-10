{{-- Horizontal bars: name, bar, amount. $rows: [['name' =>, 'amount' =>, 'other' => bool]]. --}}
@props(['rows' => [], 'tone' => 'income', 'signed' => false])
@php
    $max = collect($rows)->map(fn ($r) => abs($r['amount']))->max() ?: 1;
    $fill = fn ($amount) => $tone === 'flow'
        ? ($amount >= 0 ? 'bg-[#2F6AAE] dark:bg-[#5B8FD3]' : 'bg-[#C0841A] dark:bg-[#BF8020]')
        : ($tone === 'expense' ? 'bg-[#C0841A] dark:bg-[#BF8020]' : 'bg-[#2F6AAE] dark:bg-[#5B8FD3]');
@endphp
<ul class="space-y-2.5">
    @foreach ($rows as $row)
        <li class="grid grid-cols-[minmax(0,8.5rem)_1fr_auto] items-center gap-3 text-sm">
            <span class="truncate {{ ($row['other'] ?? false) ? 'text-gray-600 dark:text-gray-400' : 'text-gray-800 dark:text-gray-200' }}" title="{{ $row['name'] ?? $row['label'] }}">{{ $row['name'] ?? $row['label'] }}</span>
            <span class="h-2.5 rounded-sm bg-gray-100 dark:bg-gray-700" aria-hidden="true">
                <span class="block h-full rounded-sm {{ ($row['other'] ?? false) ? 'bg-gray-400 dark:bg-gray-500' : $fill($row['amount']) }}" style="width: {{ max(2, round(abs($row['amount']) / $max * 100)) }}%"></span>
            </span>
            <span class="text-right font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ $signed && $row['amount'] < 0 ? '−' : '' }}@moneyWhole(abs($row['amount']))</span>
        </li>
    @endforeach
</ul>
