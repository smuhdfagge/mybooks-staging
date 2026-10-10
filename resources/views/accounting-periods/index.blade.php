{{-- Accounting periods (tables plan T4): a short list, so a plain page with the shared table. --}}
@php
    $tone = ['open' => 'active', 'closed' => 'pending', 'locked' => 'rejected'];
    $canEdit = auth()->user()->can('edit chart-of-accounts');
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="Accounting periods" description="Open periods take entries. Closed ones take none but can be opened again. Locked ones are closed for good.">
            @if ($canEdit)
                <x-slot name="more">
                    <x-table.menu-item :href="route('accounting-periods.create')">Add one period</x-table.menu-item>
                </x-slot>
                <x-slot name="actions">
                    <button type="button" data-open-modal="generate-periods" class="btn-new">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H5a1 1 0 110-2h4V5a1 1 0 011-1z"/></svg>
                        Set up a year
                    </button>
                </x-slot>
            @endif
        </x-table.page-header>
    </x-slot>

    <div class="space-y-4">
        @if ($lockDates !== null)
            @include('accounting-periods._lock-dates')
        @endif

        @if ($periods->isEmpty())
            <div class="tbl-wrap">
                <x-table.empty title="No accounting periods yet" text="Set up the twelve months of a financial year in one go, then close each month when its books are done.">
                    @if ($canEdit)<button type="button" data-open-modal="generate-periods" class="btn-new">Set up a year</button>@endif
                </x-table.empty>
            </div>
        @else
            <x-table caption="Accounting periods" class="hidden md:block">
                <x-slot name="head">
                    <x-table.th>Period</x-table.th>
                    <x-table.th>From</x-table.th>
                    <x-table.th>To</x-table.th>
                    <x-table.th>Year</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <x-table.th>Closed</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($periods as $period)
                    <tr>
                        <td>
                            <a href="{{ route('accounting-periods.show', $period) }}" class="tbl-link">{{ $period->name }}</a>
                            @if ($period->is_year_end)<div class="text-xs tbl-muted">Year end</div>@endif
                        </td>
                        <td class="tbl-muted">{{ $period->start_date->format('j M Y') }}</td>
                        <td class="tbl-muted">{{ $period->end_date->format('j M Y') }}</td>
                        <td class="tabular-nums">{{ $period->fiscal_year ?? '—' }}</td>
                        <td><x-status-badge :status="$tone[$period->status] ?? 'draft'" :label="ucfirst($period->status)" /></td>
                        <td class="{{ $period->closed_at ? 'tbl-muted' : 'tbl-zero' }}">
                            @if ($period->closed_at)
                                {{ $period->closed_at->format('j M Y') }}@if ($period->closedBy) <span class="text-xs">by {{ $period->closedBy->name }}</span>@endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$period->name">
                                <x-table.menu-item :href="route('accounting-periods.show', $period)">{{ $canEdit && ! $period->isLocked() ? 'View, close or lock' : 'View' }}</x-table.menu-item>
                                @if ($canEdit && ! $period->isLocked())
                                    <x-table.menu-item :href="route('accounting-periods.edit', $period)">Edit</x-table.menu-item>
                                @endif
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Accounting periods">
                @foreach ($periods as $period)
                    <li>
                        <x-table.card :href="route('accounting-periods.show', $period)" :title="$period->name" :meta="$period->start_date->format('j M').' to '.$period->end_date->format('j M Y')">
                            <x-slot name="badge"><x-status-badge :status="$tone[$period->status] ?? 'draft'" :label="ucfirst($period->status)" /></x-slot>
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <!-- Generate Periods Modal -->
    <x-modal name="generate-periods" title="Generate Monthly Periods" maxWidth="md">
        <div class="px-6 pb-5">
            <div class="mt-4">
                <form action="{{ route('accounting-periods.generate') }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label for="fiscal_year" class="form-label">Fiscal Year</label>
                        <input type="number" name="fiscal_year" id="fiscal_year" value="{{ date('Y') }}" min="2000" max="2100" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-brand-500 focus:ring-brand-500" required>
                    </div>
                    <div class="mb-4">
                        <label for="start_month" class="form-label">Fiscal Year Starts In</label>
                        <select name="start_month" id="start_month" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="1">January</option>
                            <option value="2">February</option>
                            <option value="3">March</option>
                            <option value="4">April</option>
                            <option value="5">May</option>
                            <option value="6">June</option>
                            <option value="7">July</option>
                            <option value="8">August</option>
                            <option value="9">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" data-close-modal="generate-periods" class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-400 dark:hover:bg-gray-500">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-green-700 text-white rounded-md hover:bg-green-700">
                            Generate Periods
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </x-modal>
</x-app-layout>
