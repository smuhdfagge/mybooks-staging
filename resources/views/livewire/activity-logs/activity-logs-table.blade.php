{{-- Activity log (tables plan T5): who did what, newest first. --}}
@php
    $bad = [\App\Models\ActivityLog::ACTION_DELETED, \App\Models\ActivityLog::ACTION_LOGIN_FAILED, \App\Models\ActivityLog::ACTION_SUSPICIOUS_ACTIVITY, \App\Models\ActivityLog::ACTION_ACCOUNT_LOCKED];
    $module = fn ($log) => $log->model_type ? \Illuminate\Support\Str::headline(class_basename($log->model_type)) : null;
@endphp
<div class="relative space-y-3">
    <x-table.tabs :tabs="$tabs" :active="$tab" />

    <x-table.toolbar placeholder="Search what was done, record or user" :filtered="$filtered">
        <x-slot name="filters">
            <x-table.select model="period" label="Date" :options="$periods" />
            <x-table.pick model="user" label="User" :options="$users" />
            <x-table.pick model="module" label="Record" :options="$modules" />
        </x-slot>
        <x-slot name="end">
            <x-table.dropdown label="Export" align="right">
                <x-table.menu-item wire="export('csv')">CSV file</x-table.menu-item>
                <x-table.menu-item wire="export('json')">JSON file</x-table.menu-item>
            </x-table.dropdown>
        </x-slot>
    </x-table.toolbar>

    <div class="relative">
        <x-table.veil />
        @if ($logs->isEmpty())
            <div class="tbl-wrap">
                @if ($filtered || $tab !== '')
                    <x-table.empty filtered title="Nothing matches these filters" />
                @else
                    <x-table.empty title="Nothing logged yet" text="Each time someone adds, changes or deletes a record, or signs in, it shows here." />
                @endif
            </div>
        @else
            <x-table caption="Activity log" class="hidden md:block">
                <x-slot name="head">
                    <x-table.th field="created_at" :sort="[$sortField, $sortDirection]">When</x-table.th>
                    <x-table.th>Who</x-table.th>
                    <x-table.th>Did</x-table.th>
                    <x-table.th>What</x-table.th>
                    <x-table.th>From</x-table.th>
                    <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                </x-slot>
                @foreach ($logs as $log)
                    <tr wire:key="log-{{ $log->id }}">
                        <td class="tbl-muted whitespace-nowrap">{{ $log->created_at->format('j M Y') }}<div class="text-xs">{{ $log->created_at->format('H:i') }}</div></td>
                        <td class="whitespace-nowrap {{ $log->user_name ? '' : 'tbl-muted' }}">{{ $log->user_name ?? 'MyBooks' }}</td>
                        <td class="whitespace-nowrap {{ in_array($log->action, $bad, true) ? 'tbl-late' : '' }}">{{ $log->action_label }}</td>
                        <td class="max-w-[24rem]">
                            <a href="{{ route('activity-logs.show', $log) }}" class="tbl-link block truncate" title="{{ $log->description }}">{{ $log->description }}</a>
                            @if ($log->model_name || $module($log))
                                <div class="truncate text-xs tbl-muted">{{ collect([$module($log), $log->model_name])->filter()->join(' · ') }}</div>
                            @endif
                        </td>
                        <td class="font-mono text-xs {{ $log->ip_address ? 'tbl-muted' : 'tbl-zero' }}">{{ $log->ip_address ?? '—' }}</td>
                        <td class="tbl-menu">
                            <x-table.dropdown sr-label="Actions for this entry">
                                <x-table.menu-item :href="route('activity-logs.show', $log)">View details</x-table.menu-item>
                                @if ($log->user_id)
                                    <x-table.menu-item :wire="'onlyUser('.$log->user_id.')'">Only this user</x-table.menu-item>
                                @endif
                            </x-table.dropdown>
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <ul class="space-y-2 md:hidden" aria-label="Activity log">
                @foreach ($logs as $log)
                    <li wire:key="log-card-{{ $log->id }}">
                        <x-table.card :href="route('activity-logs.show', $log)" :title="$log->description" :meta="($log->user_name ?? 'MyBooks').' · '.$log->created_at->format('j M Y, H:i')"
                            :tone="in_array($log->action, $bad, true) ? 'bad' : 'muted'">
                            <x-slot name="badge"><span class="whitespace-nowrap text-xs {{ in_array($log->action, $bad, true) ? 'tbl-late' : 'tbl-muted' }}">{{ $log->action_label }}</span></x-slot>
                        </x-table.card>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-table.footer :rows="$logs" />
</div>
