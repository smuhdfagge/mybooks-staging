<x-app-layout>
    <x-slot name="header">
        <x-table.page-header title="SMS & WhatsApp messages" description="Messages MyBooks has sent for you.">
            <x-slot name="actions"><a href="{{ route('settings.messaging') }}" class="btn-secondary">SMS & WhatsApp settings</a></x-slot>
        </x-table.page-header>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-card class="p-6">
                <form method="GET" class="flex flex-wrap items-end gap-3">
                    <div>
                        <x-field name="channel" label="Channel" type="select">
                            <option value="">All</option>
                            <option value="sms" @selected(($filters['channel'] ?? null) === 'sms')>SMS</option>
                            <option value="whatsapp" @selected(($filters['channel'] ?? null) === 'whatsapp')>WhatsApp</option>
                        </x-field>
                    </div>
                    <div>
                        <x-field name="status" label="Status" type="select">
                            <option value="">All</option>
                            @foreach(['queued' => 'Queued', 'sent' => 'Sent', 'delivered' => 'Delivered', 'failed' => 'Failed'] as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
                            @endforeach
                        </x-field>
                    </div>
                    <button type="submit" class="btn-primary">Show</button>
                </form>

                @include('settings.messaging._list', ['messages' => $messages, 'showCustomer' => true])

                <div class="mt-4">{{ $messages->links() }}</div>
            </x-card>
        </div>
    </div>
</x-app-layout>
