{{-- One number in <x-report.stats>. value: already formatted text. tone: bad (red), good (green) or plain. --}}
@props(['label', 'value', 'hint' => null, 'tone' => null])
<div class="bg-white px-4 py-3 dark:bg-gray-800">
    <dt class="text-[13px] text-gray-600 dark:text-gray-400">{{ $label }}</dt>
    <dd class="mt-0.5 text-lg font-semibold tabular-nums {{ $tone === 'bad' ? 'text-red-700 dark:text-red-300' : ($tone === 'good' ? 'text-green-800 dark:text-green-300' : 'text-gray-900 dark:text-white') }}">{{ $value }}</dd>
    @if ($hint)<dd class="text-xs text-gray-600 dark:text-gray-400">{{ $hint }}</dd>@endif
</div>
