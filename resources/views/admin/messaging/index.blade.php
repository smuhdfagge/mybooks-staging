<x-layouts.admin>
    <x-slot name="header">SMS &amp; WhatsApp</x-slot>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">SMS &amp; WhatsApp</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">MyBooks pays for the messages businesses send to their customers. Each plan includes a monthly allowance; a long SMS counts once per page.</p>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-md bg-green-50 dark:bg-green-900/30 p-3 text-sm text-green-800 dark:text-green-300">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-md bg-red-50 dark:bg-red-900/30 p-3 text-sm text-red-800 dark:text-red-300">{{ $errors->first() }}</div>
    @endif

    <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-gray-500 dark:text-gray-400">SMS sent through</p>
            <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $smsDriver === 'log' ? 'Not set up (log only)' : ucfirst($smsDriver) }}</p>
            @if($balance)
                <p class="mt-1 text-gray-600 dark:text-gray-300">Termii balance: {{ $balance['currency'] }} {{ number_format($balance['balance'], 2) }}</p>
            @endif
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-gray-500 dark:text-gray-400">WhatsApp sent through</p>
            <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $whatsappDriver === 'log' ? 'Not set up (log only)' : ($whatsappDriver === 'meta' ? 'Meta Cloud API' : ucfirst($whatsappDriver)) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-gray-500 dark:text-gray-400">Delivery reports</p>
            <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $webhookSet ? 'Webhook token set' : 'MESSAGING_WEBHOOK_TOKEN not set' }}</p>
        </div>
    </div>

    <div class="mb-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Monthly allowance per plan</h2>
        <form method="POST" action="{{ route('admin.messaging.plans.update') }}">
            @csrf
            @method('PUT')
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                            <th class="py-2 pr-4">Plan</th>
                            <th class="py-2 pr-4">SMS a month</th>
                            <th class="py-2 pr-4">WhatsApp a month</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($plans as $plan)
                            <tr>
                                <td class="py-2 pr-4 text-gray-900 dark:text-white">{{ $plan->name }} @unless($plan->is_active)<span class="text-xs text-gray-500">(inactive)</span>@endunless</td>
                                <td class="py-2 pr-4"><input type="number" min="0" name="plans[{{ $plan->id }}][sms_monthly_limit]" value="{{ old('plans.'.$plan->id.'.sms_monthly_limit', $plan->sms_monthly_limit) }}" aria-label="SMS a month for {{ $plan->name }}" class="w-28 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"></td>
                                <td class="py-2 pr-4"><input type="number" min="0" name="plans[{{ $plan->id }}][whatsapp_monthly_limit]" value="{{ old('plans.'.$plan->id.'.whatsapp_monthly_limit', $plan->whatsapp_monthly_limit) }}" aria-label="WhatsApp a month for {{ $plan->name }}" class="w-28 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="submit" class="mt-3 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm font-medium">Save allowances</button>
        </form>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-x-auto">
        <h2 class="px-4 pt-4 text-sm font-semibold text-gray-900 dark:text-white">Use by business, {{ $month }}</h2>
        <table class="mt-2 min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm" data-testid="usage-by-business">
            <thead class="bg-gray-50 dark:bg-gray-750">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Business</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Plan</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">SMS</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">WhatsApp</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cost reported</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($usage as $line)
                    <tr>
                        <td class="px-4 py-3 text-gray-900 dark:text-white">
                            @if($line['tenant'])
                                <a href="{{ route('admin.tenants.show', $line['tenant']) }}" class="text-brand-600 dark:text-brand-300 hover:underline">{{ $line['tenant']->name }}</a>
                            @else
                                (deleted)
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $line['plan']?->name ?? 'No active plan' }}</td>
                        @foreach(['sms', 'whatsapp'] as $channel)
                            <td class="px-4 py-3 text-right {{ $line[$channel]['used'] >= $line[$channel]['limit'] && $line[$channel]['used'] > 0 ? 'text-red-600 dark:text-red-300 font-medium' : 'text-gray-900 dark:text-white' }}">
                                {{ number_format($line[$channel]['used']) }} / {{ number_format($line[$channel]['limit']) }}
                            </td>
                        @endforeach
                        <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ number_format($line['sms']['cost'] + $line['whatsapp']['cost'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No messages sent this month.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
