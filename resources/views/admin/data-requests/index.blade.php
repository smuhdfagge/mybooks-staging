<x-layouts.admin>
    <x-slot name="header">Data Requests</x-slot>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Data protection requests</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            Requests under the Nigeria Data Protection Act. Reply within {{ \App\Models\DataRequest::RESPONSE_DAYS }} days.
            Closing a business is logged automatically.
        </p>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-md bg-green-50 dark:bg-green-900/30 p-3 text-sm text-green-800 dark:text-green-300">{{ session('success') }}</div>
    @endif

    <div class="mb-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Log a request received by email or phone</h2>
        <form method="POST" action="{{ route('admin.data-requests.store') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3">
            @csrf
            <select name="type" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm" required>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <input type="text" name="requester" placeholder="Who asked" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm" required>
            <input type="number" name="tenant_id" placeholder="Business ID (optional)" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
            <input type="text" name="details" placeholder="Details" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
            <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm font-medium">Log request</button>
        </form>
        @if ($errors->any())
            <p class="mt-2 text-sm text-red-500">{{ $errors->first() }}</p>
        @endif
    </div>

    <div class="mb-3 text-sm">
        <a href="{{ route('admin.data-requests.index') }}" class="{{ request('status') ? 'text-gray-500' : 'font-semibold text-brand-600 dark:text-brand-300' }}">All</a>
        &middot;
        <a href="{{ route('admin.data-requests.index', ['status' => 'open']) }}" class="{{ request('status') === 'open' ? 'font-semibold text-brand-600 dark:text-brand-300' : 'text-gray-500' }}">Open</a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
            <thead class="bg-gray-50 dark:bg-gray-750">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Received</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Business</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Requester</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Due / done</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-700 dark:text-gray-300">
                @forelse ($requests as $dataRequest)
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $dataRequest->created_at?->format('j M Y') }}</td>
                        <td class="px-4 py-3">{{ $types[$dataRequest->type] ?? $dataRequest->type }}</td>
                        <td class="px-4 py-3">{{ $dataRequest->tenant_name ?? '—' }} @if($dataRequest->tenant_id)<span class="text-gray-500 dark:text-gray-400">#{{ $dataRequest->tenant_id }}</span>@endif</td>
                        <td class="px-4 py-3">{{ $dataRequest->requester ?? '—' }}</td>
                        <td class="px-4 py-3">{{ ucfirst($dataRequest->status) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if ($dataRequest->completed_at)
                                {{ $dataRequest->completed_at->format('j M Y') }}
                            @else
                                {{ $dataRequest->due_at?->format('j M Y') ?? '—' }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($dataRequest->status === \App\Models\DataRequest::STATUS_RECEIVED)
                                <form method="POST" action="{{ route('admin.data-requests.complete', $dataRequest) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-brand-600 hover:text-brand-800 text-sm dark:text-brand-300 dark:hover:text-brand-200">Mark done</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-gray-500">No requests yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($requests->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">{{ $requests->links() }}</div>
        @endif
    </div>
</x-layouts.admin>
