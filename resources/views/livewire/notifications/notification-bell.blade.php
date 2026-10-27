{{--
    The header bell. The dot shows only when something is unread, so it
    still means something when it is lit.
--}}
<div class="relative" x-data @keydown.escape.window="$wire.set('open', false)">
    <button type="button" wire:click="toggle" wire:poll.60s
            class="relative p-2 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-md dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700 focus-ring"
            aria-haspopup="true" aria-expanded="{{ $open ? 'true' : 'false' }}">
        <span class="sr-only">{{ $count ? $count.' unread notifications' : 'Notifications' }}</span>
        <svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
        @if ($count)
            <span class="absolute top-0.5 right-0.5 flex min-w-[1.1rem] items-center justify-center rounded-full bg-red-500 px-1 text-[0.65rem] font-semibold leading-4 text-white ring-2 ring-white dark:ring-gray-800">
                {{ $count > 9 ? '9+' : $count }}
            </span>
        @endif
    </button>

    @if ($open)
        {{-- Clicking anywhere else closes it. --}}
        <div class="fixed inset-0 z-30" wire:click="toggle" aria-hidden="true"></div>

        <div class="absolute right-0 z-40 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
             role="menu" aria-label="Notifications">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-2.5 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Notifications</h2>
                @if ($count)
                    <button type="button" wire:click="markAllRead"
                            class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-300 focus-ring">
                        Mark all as read
                    </button>
                @endif
            </div>

            <ul class="max-h-80 divide-y divide-gray-100 overflow-y-auto dark:divide-gray-700">
                @forelse ($items as $item)
                    <li>
                        <button type="button" wire:click="openNotification(@jsText($item['id']))"
                                @class([
                                    'flex w-full items-start gap-2.5 px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-gray-700/50 focus-ring',
                                    'bg-brand-50/60 dark:bg-brand-900/10' => $item['unread'],
                                ])>
                            <span @class([
                                    'mt-1.5 h-2 w-2 flex-shrink-0 rounded-full',
                                    'bg-brand-500' => $item['unread'],
                                    'bg-transparent' => ! $item['unread'],
                                ]) aria-hidden="true"></span>
                            <span class="min-w-0">
                                <span @class([
                                        'block text-sm text-gray-900 dark:text-gray-100',
                                        'font-medium' => $item['unread'],
                                    ])>{{ $item['message'] }}</span>
                                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400"
                                      title="{{ $item['at']?->format('j M Y, g:i a') }}">
                                    {{ $item['at']?->diffForHumans() }}
                                </span>
                            </span>
                        </button>
                    </li>
                @empty
                    <li class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                        Nothing yet. Overdue invoices, bills due and payments received will show up here.
                    </li>
                @endforelse
            </ul>
        </div>
    @endif
</div>
