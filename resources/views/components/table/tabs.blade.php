{{--
    Status tabs with counts (tables plan T1). Tabs on a computer, chips on a
    phone. $tabs: ['key' => ['label' => 'Overdue', 'count' => 24, 'alert' => true]].
    The key '' means "All". Clicking sets the Livewire property $model.
--}}
@props(['tabs' => [], 'active' => '', 'model' => 'tab'])
<div {{ $attributes->merge(['class' => '']) }}>
    <nav class="hidden md:flex gap-1 border-b border-gray-200 dark:border-gray-700" aria-label="Filter by status">
        @foreach ($tabs as $key => $tab)
            @php $on = (string) $active === (string) $key; @endphp
            <button type="button" wire:click="$set('{{ $model }}', '{{ $key }}')" @if ($on) aria-current="true" @endif
                class="-mb-px inline-flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 {{ $on ? 'border-brand-600 font-semibold text-gray-900 dark:border-brand-300 dark:text-white' : 'border-transparent font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}">
                {{ $tab['label'] }}
                @isset($tab['count'])
                    <span class="rounded-full px-1.5 text-xs tabular-nums {{ ($tab['alert'] ?? false) && $tab['count'] > 0 ? 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200' }}">{{ number_format($tab['count']) }}</span>
                @endisset
            </button>
        @endforeach
    </nav>
    <nav class="flex gap-2 overflow-x-auto pb-1 md:hidden" aria-label="Filter by status">
        @foreach ($tabs as $key => $tab)
            @php $on = (string) $active === (string) $key; @endphp
            <button type="button" wire:click="$set('{{ $model }}', '{{ $key }}')" @if ($on) aria-current="true" @endif
                class="whitespace-nowrap rounded-full border px-3 py-1.5 text-sm font-medium {{ $on ? 'border-brand-900 bg-brand-900 text-white dark:border-brand-300 dark:bg-brand-300 dark:text-brand-950' : 'border-gray-300 bg-white text-gray-800 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200' }}">
                {{ $tab['label'] }}@if (isset($tab['count']) && $key !== '') <span class="tabular-nums">{{ number_format($tab['count']) }}</span>@endif
            </button>
        @endforeach
    </nav>
</div>
