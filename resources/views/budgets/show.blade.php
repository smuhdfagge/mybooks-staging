{{-- One budget (tables plan T7): its figures by month, and how spending is going. Amounts in ₦. --}}
@php
    $statusLabel = $budget->isDraft() ? 'Draft' : ($budget->isActive() ? 'Active' : 'Locked');
    $statusTone = $budget->isDraft() ? 'draft' : ($budget->isActive() ? 'active' : 'inactive');
    $groups = ['income' => 'Income', 'expense' => 'Spending'];
    $through = $ytdUtilization['through'] ?? null;
    $soFar = $through ? 'to the end of '.\Illuminate\Support\Str::ucfirst($months[$through] ?? $through) : null;
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-table.page-header :title="$budget->name" :description="'Budget for '.$budget->fiscal_year.($budget->description ? ' · '.$budget->description : '').'. Amounts in ₦.'">
            <x-slot name="badge"><x-status-badge :status="$statusTone" :label="$statusLabel" /></x-slot>
            <x-slot name="more">
                @can('edit budgets')
                    @if (! $budget->isLocked())
                        <x-table.menu-item :href="route('budgets.edit', $budget)">Edit</x-table.menu-item>
                    @endif
                    @if ($budget->isActive())
                        <x-table.menu-item :post="route('budgets.lock', $budget)" confirm="Lock this budget? It becomes read-only and this can't be undone.">Lock budget</x-table.menu-item>
                    @endif
                @endcan
                <x-table.menu-item :href="route('budgets.index')">All budgets</x-table.menu-item>
            </x-slot>
            <x-slot name="actions">
                @if ($budget->isActive() || $budget->isLocked())
                    <a href="{{ route('budgets.vs-actual', $budget) }}" class="btn-new">Budget and actual</a>
                @elseif ($budget->isDraft() && $budget->lines->count() > 0)
                    @can('edit budgets')
                        <form action="{{ route('budgets.activate', $budget) }}" method="POST" data-confirm="Activate this budget? Its figures will be compared with what actually happens.">
                            @csrf
                            <button type="submit" class="btn-new">Activate budget</button>
                        </form>
                    @endcan
                @endif
            </x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="space-y-6">
        <x-report.stats :cols="4">
            <x-report.stat label="Income budget" :value="\App\Support\Figure::show($summary['income_budget'])" hint="Whole year" />
            <x-report.stat label="Spending budget" :value="\App\Support\Figure::show($summary['expense_budget'])" hint="Whole year" />
            <x-report.stat label="Profit in the budget" :value="\App\Support\Figure::show($summary['net_budget'])" :tone="$summary['net_budget'] < 0 ? 'bad' : null" />
            @if ($ytdUtilization)
                <x-report.stat label="Spending budget used" :value="$through ? \App\Support\Figure::percent($ytdUtilization['total_actual_ytd'], $ytdUtilization['total_budget_ytd']) : '—'" :hint="$soFar ?? 'Not started yet'" :tone="$ytdUtilization['utilization_percent'] > 100 ? 'bad' : null" />
            @else
                <x-report.stat label="Accounts" :value="$summary['accounts_count']" />
            @endif
        </x-report.stats>

        @if ($ytdUtilization && $ytdUtilization['over_budget_count'] > 0)
            <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-100">
                {{ $ytdUtilization['over_budget_count'] }} {{ \Illuminate\Support\Str::plural('account', $ytdUtilization['over_budget_count']) }} worse than budget {{ $soFar }}.
                <a href="{{ route('budgets.vs-actual', $budget) }}" class="font-medium underline">See budget and actual</a>
            </p>
        @endif

        <section class="space-y-3" aria-labelledby="lines-title">
            <h3 id="lines-title" class="text-base font-semibold text-gray-900 dark:text-white">Month by month</h3>
            @if ($budget->lines->isEmpty())
                <div class="tbl-wrap">
                    <x-table.empty title="No budget lines yet" text="Add the income and spending accounts you want to plan for." />
                </div>
            @else
                <x-table caption="Budget lines, month by month">
                    <x-slot name="head">
                        <x-table.th>Account</x-table.th>
                        @foreach ($months as $key => $name)
                            <x-table.th num class="hidden lg:table-cell">{{ substr($name, 0, 3) }}</x-table.th>
                        @endforeach
                        <x-table.th num>Year</x-table.th>
                    </x-slot>
                    @foreach ($groups as $type => $heading)
                        @php $lines = $budget->lines->filter(fn ($l) => $l->account->type === $type); @endphp
                        @continue($lines->isEmpty())
                        <tr class="rpt-section"><td colspan="{{ count($months) + 2 }}">{{ $heading }}</td></tr>
                        @foreach ($lines as $line)
                            <tr>
                                <td class="rpt-in1"><span class="tbl-muted">{{ $line->account->account_code }}</span> {{ $line->account->name }}</td>
                                @foreach ($months as $key => $name)
                                    <td class="num hidden lg:table-cell {{ \App\Support\Figure::tone($line->{$key}) }}">@fig($line->{$key}, false)</td>
                                @endforeach
                                <td class="num font-medium">@fig($line->annual_total, false)</td>
                            </tr>
                        @endforeach
                        <tr class="rpt-sub">
                            <td class="rpt-in1">Total {{ strtolower($heading) }}</td>
                            @foreach ($months as $key => $name)
                                <td class="num hidden lg:table-cell">@fig($lines->sum($key), false)</td>
                            @endforeach
                            <td class="num">@fig($lines->sum('annual_total'), false)</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </section>

        <p class="text-sm text-gray-600 dark:text-gray-400">
            Made by {{ $budget->createdBy?->name ?? '—' }} on {{ $budget->created_at->format('j M Y') }}@if ($budget->approvedBy); approved by {{ $budget->approvedBy->name }} on {{ $budget->approved_at?->format('j M Y') }}@endif.
        </p>
    </div>
</x-app-layout>
