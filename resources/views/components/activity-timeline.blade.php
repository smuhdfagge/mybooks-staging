{{--
    A document's history (audit MB-37), in the order things happened in the
    business: by the date on the document or payment, not the day someone
    typed it in. Where it was typed in on another day, that shows as
    "recorded".

    Each event: ['date' => the business date, 'recorded' => when it was
    entered (optional), 'text' => what happened, 'who' => by whom (optional)].
--}}
@props(['events', 'title' => 'Activity'])

@php
    $events = collect($events)
        ->filter(fn ($event) => $event['date'] ?? null)
        ->sortBy(fn ($event) => $event['date']->format('Y-m-d').' '.($event['recorded'] ?? $event['date'])->format('H:i:s'))
        ->values();
@endphp

<section {{ $attributes->class('rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10') }}>
    <h2 class="section-title">{{ $title }}</h2>
    <ol class="mt-3 space-y-3 border-l border-gray-200 pl-4 dark:border-gray-700">
        @foreach ($events as $event)
            @php $recorded = $event['recorded'] ?? null; @endphp
            <li class="relative text-sm">
                <span class="absolute -left-[1.3rem] top-1.5 h-2 w-2 rounded-full bg-gray-400 ring-4 ring-white dark:bg-gray-500 dark:ring-gray-800" aria-hidden="true"></span>
                <p class="text-gray-900 dark:text-gray-100">{{ $event['text'] }}</p>
                <p class="text-gray-600 dark:text-gray-400">
                    <time datetime="{{ $event['date']->toDateString() }}">{{ $event['date']->format('j M Y') }}</time>@if ($event['who'] ?? null), {{ $event['who'] }}@endif
                    @if ($recorded && ! $recorded->isSameDay($event['date']))
                        <span class="block text-xs">Recorded <time datetime="{{ $recorded->toIso8601String() }}">{{ $recorded->format('j M Y') }}</time></span>
                    @endif
                </p>
            </li>
        @endforeach
    </ol>
</section>
