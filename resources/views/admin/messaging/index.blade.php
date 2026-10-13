{{-- Admin: SMS and WhatsApp (tables plan T5): how messages go out, each plan's allowance, and use this month. --}}
@php
    $over = fn ($c) => $c['used'] > 0 && $c['used'] >= $c['limit'];
@endphp
<x-layouts.admin>
    <x-slot name="header">SMS &amp; WhatsApp</x-slot>

    <div class="space-y-6">
        <x-table.page-header title="SMS & WhatsApp" description="MyBooks pays for the messages businesses send to their customers. Each plan includes a monthly allowance; a long SMS counts once per page." />

        @if ($errors->any())
            <p class="form-error" role="alert">{{ $errors->first() }}</p>
        @endif

        <dl class="grid grid-cols-1 gap-3 text-sm md:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <dt class="text-gray-600 dark:text-gray-400">SMS sent through</dt>
                <dd class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $smsDriver === 'log' ? 'Not set up (log only)' : ucfirst($smsDriver) }}</dd>
                @if ($balance)
                    <dd class="mt-1 text-gray-600 dark:text-gray-300">Termii balance: {{ $balance['currency'] }} {{ number_format($balance['balance'], 2) }}</dd>
                @endif
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <dt class="text-gray-600 dark:text-gray-400">WhatsApp sent through</dt>
                <dd class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $whatsappDriver === 'log' ? 'Not set up (log only)' : ($whatsappDriver === 'meta' ? 'Meta Cloud API' : ucfirst($whatsappDriver)) }}</dd>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <dt class="text-gray-600 dark:text-gray-400">Delivery reports</dt>
                <dd class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $webhookSet ? 'Webhook token set' : 'MESSAGING_WEBHOOK_TOKEN not set' }}</dd>
            </div>
        </dl>

        <section class="space-y-3" aria-labelledby="usage-title">
            <h2 id="usage-title" class="text-base font-semibold text-gray-900 dark:text-white">Use by business, {{ $month }}</h2>
            @if ($usage->isEmpty())
                <div class="tbl-wrap"><x-table.empty title="No messages sent this month" text="Businesses' SMS and WhatsApp use shows here as they send." /></div>
            @else
                <x-table caption="Use by business" data-testid="usage-by-business">
                    <x-slot name="head">
                        <x-table.th>Business</x-table.th>
                        <x-table.th>Plan</x-table.th>
                        <x-table.th num>SMS used / allowed</x-table.th>
                        <x-table.th num>WhatsApp used / allowed</x-table.th>
                        <x-table.th num>Cost reported</x-table.th>
                    </x-slot>
                    @foreach ($usage as $line)
                        <tr>
                            <td>
                                @if ($line['tenant'])
                                    <a href="{{ route('admin.tenants.show', $line['tenant']) }}" class="tbl-link">{{ $line['tenant']->name }}</a>
                                @else
                                    <span class="tbl-muted">Deleted business</span>
                                @endif
                            </td>
                            <td class="{{ $line['plan'] ? '' : 'tbl-zero' }}">{{ $line['plan']?->name ?? 'No active plan' }}</td>
                            @foreach (['sms', 'whatsapp'] as $channel)
                                <td class="num {{ $over($line[$channel]) ? 'tbl-late' : ($line[$channel]['used'] ? '' : 'tbl-zero') }}">{{ number_format($line[$channel]['used']) }} / {{ number_format($line[$channel]['limit']) }}</td>
                            @endforeach
                            <td class="num tbl-muted">{{ number_format($line['sms']['cost'] + $line['whatsapp']['cost'], 2) }}</td>
                        </tr>
                    @endforeach
                    <x-slot name="foot">
                        <tr>
                            <td colspan="2">Total</td>
                            <td class="num">{{ number_format($usage->sum(fn ($l) => $l['sms']['used'])) }}</td>
                            <td class="num">{{ number_format($usage->sum(fn ($l) => $l['whatsapp']['used'])) }}</td>
                            <td class="num">{{ number_format($usage->sum(fn ($l) => $l['sms']['cost'] + $l['whatsapp']['cost']), 2) }}</td>
                        </tr>
                    </x-slot>
                </x-table>
            @endif
        </section>

        <section class="space-y-3" aria-labelledby="allow-title">
            <h2 id="allow-title" class="text-base font-semibold text-gray-900 dark:text-white">Monthly allowance per plan</h2>
            <form method="POST" action="{{ route('admin.messaging.plans.update') }}" class="space-y-3">
                @csrf
                @method('PUT')
                <x-table caption="Monthly allowance per plan">
                    <x-slot name="head">
                        <x-table.th>Plan</x-table.th>
                        <x-table.th num>SMS a month</x-table.th>
                        <x-table.th num>WhatsApp a month</x-table.th>
                    </x-slot>
                    @foreach ($plans as $plan)
                        <tr>
                            <td>{{ $plan->name }}@unless ($plan->is_active) <span class="tbl-muted">(not offered)</span>@endunless</td>
                            @foreach (['sms', 'whatsapp'] as $channel)
                                @php $field = $channel.'_monthly_limit'; @endphp
                                <td class="num">
                                    <input type="number" min="0" name="plans[{{ $plan->id }}][{{ $field }}]" value="{{ old('plans.'.$plan->id.'.'.$field, $plan->{$field}) }}"
                                        aria-label="{{ $channel === 'sms' ? 'SMS' : 'WhatsApp' }} a month for {{ $plan->name }}"
                                        class="h-8 w-28 rounded-md border-gray-300 text-right text-sm tabular-nums dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </x-table>
                <button type="submit" class="btn-primary">Save allowances</button>
            </form>
        </section>
    </div>
</x-layouts.admin>
