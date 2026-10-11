{{--
    The body of a report (tables plan T6). On paper the page header and
    options are hidden, so this prints the business, title and period first.
    <x-report.sheet title="Trial balance" period="As at 10 Oct 2026"> … </x-report.sheet>
--}}
@props(['title', 'period' => null])
<div {{ $attributes->merge(['class' => 'space-y-4']) }}>
    <div class="hidden print:block">
        <p class="text-sm">{{ auth()->user()?->tenant?->name }}</p>
        <h1 class="text-xl font-semibold">{{ $title }}</h1>
        @if ($period)<p class="text-sm">{{ $period }} · Amounts in {{ \App\Support\Money::symbol() }}</p>@endif
    </div>
    {{ $slot }}
</div>
