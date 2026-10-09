<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">SMS &amp; WhatsApp messages</h2>
            <a href="{{ route('settings.messaging') }}" class="text-sm text-brand-600 dark:text-brand-300 hover:underline">SMS &amp; WhatsApp settings</a>
        </div>
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
