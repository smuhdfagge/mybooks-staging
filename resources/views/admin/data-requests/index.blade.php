{{-- Admin: data protection requests (tables plan T5). --}}
@php
    $tone = ['received' => 'pending', 'scheduled' => 'confirmed', 'completed' => 'completed', 'cancelled' => 'cancelled'];
    $label = ['received' => 'To answer', 'scheduled' => 'Scheduled', 'completed' => 'Done', 'cancelled' => 'Cancelled'];
@endphp
<x-layouts.admin>
    <x-slot name="header">Data requests</x-slot>

    <div class="space-y-4">
        <x-table.page-header title="Data protection requests"
            :description="'Requests under the Nigeria Data Protection Act. Reply within '.\App\Models\DataRequest::RESPONSE_DAYS.' days. Closing a business is logged automatically.'" />

        <details class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800" @if ($errors->any()) open @endif>
            <summary class="cursor-pointer text-sm font-semibold text-gray-900 dark:text-white">Log a request received by email or phone</summary>
            <form method="POST" action="{{ route('admin.data-requests.store') }}" class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-5">
                @csrf
                <label class="block"><span class="sr-only">Kind of request</span>
                    <select name="type" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200" required>
                        @foreach ($types as $value => $text)
                            <option value="{{ $value }}" @selected(old('type') === $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block"><span class="sr-only">Who asked</span>
                    <input type="text" name="requester" value="{{ old('requester') }}" placeholder="Who asked" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200" required>
                </label>
                <label class="block"><span class="sr-only">Business ID</span>
                    <input type="number" name="tenant_id" value="{{ old('tenant_id') }}" placeholder="Business ID (if known)" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                </label>
                <label class="block"><span class="sr-only">Details</span>
                    <input type="text" name="details" value="{{ old('details') }}" placeholder="Details" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                </label>
                <button type="submit" class="btn-primary">Log request</button>
            </form>
            @if ($errors->any())
                <p class="form-error mt-2">{{ $errors->first() }}</p>
            @endif
        </details>

        <div class="space-y-3">
            <x-table.tabs :tabs="$tabs" :active="$status" />

            @if ($requests->isEmpty())
                <div class="tbl-wrap">
                    @if ($status !== '')
                        <x-table.empty filtered title="None here" :clear="route('admin.data-requests.index')" text="No requests have this status." />
                    @else
                        <x-table.empty title="No requests yet" text="Requests made in the app show here. Log ones that come by email or phone above." />
                    @endif
                </div>
            @else
                <x-table caption="Data protection requests" class="hidden md:block">
                    <x-slot name="head">
                        <x-table.th>Received</x-table.th>
                        <x-table.th>Kind</x-table.th>
                        <x-table.th>Business</x-table.th>
                        <x-table.th>Who asked</x-table.th>
                        <x-table.th>Reply by / done</x-table.th>
                        <x-table.th>Status</x-table.th>
                        <th scope="col" class="tbl-menu"><span class="sr-only">Actions</span></th>
                    </x-slot>
                    @foreach ($requests as $dataRequest)
                        @php $late = $dataRequest->isOpen() && $dataRequest->due_at?->isPast(); @endphp
                        <tr>
                            <td class="tbl-muted whitespace-nowrap">{{ $dataRequest->created_at?->format('j M Y') }}</td>
                            <td>{{ $types[$dataRequest->type] ?? $dataRequest->type }}</td>
                            <td class="{{ $dataRequest->tenant_name ? '' : 'tbl-zero' }}">
                                @if ($dataRequest->tenant_id)
                                    <a href="{{ route('admin.tenants.show', $dataRequest->tenant_id) }}" class="tbl-link">{{ $dataRequest->tenant_name ?? 'Business' }}</a> <span class="tbl-muted">#{{ $dataRequest->tenant_id }}</span>
                                @else
                                    {{ $dataRequest->tenant_name ?? '—' }}
                                @endif
                            </td>
                            <td class="{{ $dataRequest->requester ? '' : 'tbl-zero' }}">{{ $dataRequest->requester ?? '—' }}</td>
                            <td class="whitespace-nowrap {{ $late ? 'tbl-late' : 'tbl-muted' }}">
                                {{ $dataRequest->completed_at ? $dataRequest->completed_at->format('j M Y') : ($dataRequest->due_at?->format('j M Y') ?? '—') }}@if ($late) · Late @endif
                            </td>
                            <td><x-status-badge :status="$tone[$dataRequest->status] ?? 'draft'" :label="$label[$dataRequest->status] ?? ucfirst($dataRequest->status)" /></td>
                            <td class="tbl-menu">
                                @if ($dataRequest->status === \App\Models\DataRequest::STATUS_RECEIVED)
                                    <x-table.dropdown :sr-label="'Actions for the request from '.($dataRequest->requester ?? 'unknown')">
                                        <x-table.menu-item :post="route('admin.data-requests.complete', $dataRequest)" method="PATCH">Mark done</x-table.menu-item>
                                    </x-table.dropdown>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-table>
                <ul class="space-y-2 md:hidden" aria-label="Data protection requests">
                    @foreach ($requests as $dataRequest)
                        @php $late = $dataRequest->isOpen() && $dataRequest->due_at?->isPast(); @endphp
                        <li>
                            <x-table.card :href="$dataRequest->tenant_id ? route('admin.tenants.show', $dataRequest->tenant_id) : '#'" :title="$types[$dataRequest->type] ?? $dataRequest->type"
                                :meta="($dataRequest->requester ?? '—').' · '.$dataRequest->created_at?->format('j M Y')" :tone="$late ? 'bad' : 'muted'">
                                <x-slot name="badge"><x-status-badge :status="$tone[$dataRequest->status] ?? 'draft'" :label="$label[$dataRequest->status] ?? ucfirst($dataRequest->status)" /></x-slot>
                                @if ($late)
                                    <x-slot name="alert">Reply was due {{ $dataRequest->due_at->format('j M Y') }}</x-slot>
                                @endif
                            </x-table.card>
                        </li>
                    @endforeach
                </ul>
            @endif
            <x-table.footer :rows="$requests" links />
        </div>
    </div>
</x-layouts.admin>
