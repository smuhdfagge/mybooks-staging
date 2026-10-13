{{-- Payroll runs (tables plan T5). --}}
@php
    $user = auth()->user();
    $money = fn ($v) => number_format((float) $v, 2);
    $labels = \App\Livewire\Payroll\PayrollBatchTable::LABELS;
    $tone = ['draft' => 'draft', 'approved' => 'approved', 'processing' => 'pending', 'failed' => 'failed', 'paid' => 'paid', 'cancelled' => 'cancelled'];
    $month = fn ($b) => $b->pay_period_start ? \Illuminate\Support\Carbon::parse($b->pay_period_start)->format('F Y') : '—';
    $range = fn ($b) => ($b->pay_period_start ? \Illuminate\Support\Carbon::parse($b->pay_period_start)->format('j M') : '').' to '.($b->pay_period_end ? \Illuminate\Support\Carbon::parse($b->pay_period_end)->format('j M Y') : '');
@endphp
<div class="relative space-y-3">
    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search run number or notes" :filtered="$filtered">
        @if (count($years) > 1)
            <x-slot name="filters">
                <x-table.pick model="year" label="Year" :options="$years" />
            </x-slot>
        @endif
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($batches->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="No payroll runs match these filters" />
                @else
                    <x-table.empty title="No payroll runs yet" text="Run payroll for a month and MyBooks works out pay, tax and pension for each employee.">
                        @can('create payroll')<a href="{{ route('payroll.generate-form') }}" class="btn-new">Run payroll</a>@endcan
                    </x-table.empty>
                @endif
            </div>
        @else
            <x-table caption="Payroll runs" class="hidden md:block">
                <x-slot name="head">
                    <x-table.th field="batch_number" :sort="[$sortField, $sortDirection]">Run</x-table.th>
                    <x-table.th field="pay_period_start" :sort="[$sortField, $sortDirection]">Month</x-table.th>
                    <x-table.th num>Staff</x-table.th>
                    <x-table.th field="total_gross" :sort="[$sortField, $sortDirection]" num>Gross pay</x-table.th>
                    <x-table.th num>Deductions</x-table.th>
                    <x-table.th field="total_net" :sort="[$sortField, $sortDirection]" num>Net pay</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($batches as $batch)
                    <tr wire:key="pb-{{ $batch->id }}">
                        <td><a href="{{ route('payroll-batches.show', $batch) }}" class="tbl-link">{{ $batch->batch_number }}</a></td>
                        <td>
                            <span class="block">{{ $month($batch) }}</span>
                            <span class="block text-xs tbl-muted">{{ $range($batch) }}</span>
                        </td>
                        <td class="num">{{ number_format((int) $batch->employee_count) }}</td>
                        <td class="num">{{ $money($batch->total_gross) }}</td>
                        <td class="num">{{ $money($batch->total_deductions) }}</td>
                        <td class="num">{{ $money($batch->total_net) }}</td>
                        <td>
                            <x-status-badge :status="$tone[$batch->status] ?? 'draft'" :label="$labels[$batch->status] ?? ucfirst($batch->status)" />
                            @if ($batch->status === 'failed' && $batch->failure_reason)<p class="mt-0.5 max-w-[14rem] truncate text-xs text-red-700 dark:text-red-300" title="{{ $batch->failure_reason }}">{{ $batch->failure_reason }}</p>@endif
                        </td>
                        <td class="tbl-menu">
                            <x-table.dropdown :sr-label="'Actions for '.$batch->batch_number">
                                <x-table.menu-item :href="route('payroll-batches.show', $batch)">{{ in_array($batch->status, ['draft', 'approved', 'failed'], true) ? 'View, approve or pay' : 'View' }}</x-table.menu-item>
                                <x-table.menu-item :href="route('payroll-batches.payslips', $batch)" new-tab>Payslips</x-table.menu-item>
                                @if (in_array($batch->status, ['approved', 'paid'], true))
                                    <x-table.menu-item :href="route('payroll-batches.bank-file', $batch)">Bank file</x-table.menu-item>
                                @endif
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
                <x-slot name="foot">
                    <tr>
                        <td colspan="3">Total of {{ number_format($totals->n) }} {{ $totals->n == 1 ? 'run' : 'runs' }}@if ($filtered || $tab !== '') <span class="font-normal tbl-muted">(this filter)</span>@endif</td>
                        <td class="num">{{ $money($totals->gross) }}</td>
                        <td class="num">{{ $money($totals->deductions) }}</td>
                        <td class="num">{{ $money($totals->net) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </x-slot>
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Payroll runs">
                @foreach ($batches as $batch)
                    <li wire:key="pb-card-{{ $batch->id }}">
                        <x-table.card :href="route('payroll-batches.show', $batch)" :title="$month($batch)" :amount="\App\Support\Money::format($batch->total_net)"
                            :meta="$batch->batch_number.' · '.$batch->employee_count.' staff'">
                            <x-slot name="badge"><x-status-badge :status="$tone[$batch->status] ?? 'draft'" :label="$labels[$batch->status] ?? ucfirst($batch->status)" /></x-slot>
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
            <p class="text-sm font-medium text-gray-700 md:hidden dark:text-gray-300">Net pay {{ \App\Support\Money::format($totals->net) }}</p>
        @endif
    </div>

    <x-table.footer :rows="$batches" />
</div>
