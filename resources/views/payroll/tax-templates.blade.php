{{-- Tax templates (tables plan T5): the PAYE bands in use, and ready-made sets to apply. Amounts in ₦. --}}
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $byCountry = $templates->count() > 1;
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Tax templates" description="The income tax bands payroll uses, and ready-made sets you can apply. Amounts in ₦.">
            <x-slot name="more">
                <x-table.menu-item :href="route('payroll.index')">Payroll runs</x-table.menu-item>
                <x-table.menu-item :href="route('payroll.liabilities')">Tax and pension to pay</x-table.menu-item>
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="space-y-6">
        <section class="space-y-3" aria-labelledby="bands-title">
            <h3 id="bands-title" class="text-base font-semibold text-gray-900 dark:text-white">Tax bands in use</h3>
            @if ($currentBrackets->isEmpty())
                <div class="tbl-wrap">
                    <x-table.empty title="No tax bands yet" text="Apply a template below and payroll will work out income tax from it." />
                </div>
            @else
                <x-table caption="Tax bands in use">
                    <x-slot name="head">
                        <x-table.th>Band</x-table.th>
                        <x-table.th num>From</x-table.th>
                        <x-table.th num>Up to</x-table.th>
                        <x-table.th num>Rate</x-table.th>
                        <x-table.th>Per</x-table.th>
                    </x-slot>
                    @foreach ($currentBrackets as $bracket)
                        <tr>
                            <td>{{ $bracket->name }}</td>
                            <td class="num">{{ $money($bracket->min_amount) }}</td>
                            <td class="num {{ $bracket->max_amount ? '' : 'tbl-muted' }}">{{ $bracket->max_amount ? $money($bracket->max_amount) : 'No limit' }}</td>
                            <td class="num">{{ rtrim(rtrim(number_format((float) $bracket->rate, 2), '0'), '.') }}%</td>
                            <td class="tbl-muted">{{ $bracket->period === 'monthly' ? 'Month' : 'Year' }}</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </section>

        @foreach ($templates as $countryCode => $countryTemplates)
            <section class="space-y-4" aria-label="Templates {{ $countryCode }}">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Templates you can apply{{ $byCountry ? ' · '.$countryCode : '' }}</h3>
                @foreach ($countryTemplates as $template)
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h4 class="font-medium text-gray-900 dark:text-white">
                                    {{ $template->name }}
                                    @if ($template->is_current)
                                        <x-status-badge status="active" label="Latest" />
                                    @endif
                                </h4>
                                @if ($template->description)
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $template->description }}</p>
                                @endif
                            </div>
                            <form action="{{ route('payroll.apply-tax-template') }}" method="POST" data-confirm="This replaces the tax bands in use. Continue?">
                                @csrf
                                <input type="hidden" name="template_id" value="{{ $template->id }}">
                                <button type="submit" class="tbl-chip h-9">Apply</button>
                            </form>
                        </div>
                        <x-table :caption="$template->name">
                            <x-slot name="head">
                                <x-table.th>Band</x-table.th>
                                <x-table.th num class="hidden sm:table-cell">From</x-table.th>
                                <x-table.th num class="hidden sm:table-cell">Up to</x-table.th>
                                <x-table.th num>Rate</x-table.th>
                            </x-slot>
                            @foreach ($template->brackets as $bracket)
                                <tr>
                                    <td>{{ $bracket['name'] ?? 'Band' }}</td>
                                    <td class="num hidden sm:table-cell">{{ $money($bracket['min'] ?? 0) }}</td>
                                    <td class="num hidden sm:table-cell {{ isset($bracket['max']) ? '' : 'tbl-muted' }}">{{ isset($bracket['max']) ? $money($bracket['max']) : 'No limit' }}</td>
                                    <td class="num">{{ $bracket['rate'] }}%</td>
                                </tr>
                            @endforeach
                        </x-table>
                        @if ($template->employer_contributions)
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                <span class="font-medium text-gray-900 dark:text-white">Employer pays:</span>
                                @foreach ($template->employer_contributions as $contrib)
                                    {{ $contrib['name'] }} {{ $contrib['type'] === 'fixed' ? $money($contrib['rate']) : $contrib['rate'].'%' }}{{ ! empty($contrib['cap']) ? ' (up to '.$money($contrib['cap']).')' : '' }}{{ $loop->last ? '.' : ';' }}
                                @endforeach
                            </p>
                        @endif
                    </div>
                @endforeach
            </section>
        @endforeach
    </div>
</x-app-layout>
