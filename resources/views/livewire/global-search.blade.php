<div class="relative w-full" x-data="{ open: @entangle('isOpen') }" @click.away="open = false" @keydown.escape.window="open = false">
    <div class="relative">
        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        </div>
        <input type="search"
               wire:model.live.debounce.300ms="query"
               placeholder="Search customers, invoices, items..."
               aria-label="Global search"
               class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 py-2 pl-10 pr-3 text-sm text-gray-900 dark:text-white placeholder-gray-500 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
               @focus="if(($wire.query || '').length >= 2) open = true"
               @keydown.slash.window.prevent="$el.focus()">
        <div wire:loading wire:target="query" class="absolute inset-y-0 right-0 flex items-center pr-3">
            <svg class="animate-spin h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
    </div>

    {{-- Results dropdown --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-1"
         class="absolute z-50 mt-1 w-full max-h-96 overflow-y-auto rounded-lg bg-white dark:bg-gray-800 shadow-lg ring-1 ring-black ring-opacity-5"
         x-cloak>

        @if(count($results) === 0 && strlen($query) >= 2)
            <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                No results found for "{{ $query }}"
            </div>
        @endif

        @if(isset($results['customers']))
            <div class="border-b border-gray-100 dark:border-gray-700">
                <div class="px-3 py-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/50">
                    Customers
                </div>
                @foreach($results['customers'] as $customer)
                    <a href="{{ route('customers.show', $customer->id) }}"
                       wire:click="selectResult"
                       class="flex items-center gap-3 px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <div class="flex-shrink-0 h-8 w-8 rounded-full bg-brand-100 dark:bg-brand-900/50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $customer->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $customer->email }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

        @if(isset($results['vendors']))
            <div class="border-b border-gray-100 dark:border-gray-700">
                <div class="px-3 py-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/50">
                    Vendors
                </div>
                @foreach($results['vendors'] as $vendor)
                    <a href="{{ route('vendors.show', $vendor->id) }}"
                       wire:click="selectResult"
                       class="flex items-center gap-3 px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <div class="flex-shrink-0 h-8 w-8 rounded-full bg-accent-100 dark:bg-accent-900/50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-accent-700 dark:text-accent-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $vendor->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $vendor->email }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

        @if(isset($results['invoices']))
            <div class="border-b border-gray-100 dark:border-gray-700">
                <div class="px-3 py-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/50">
                    Invoices
                </div>
                @foreach($results['invoices'] as $invoice)
                    <a href="{{ route('invoices.show', $invoice->id) }}"
                       wire:click="selectResult"
                       class="flex items-center gap-3 px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <div class="flex-shrink-0 h-8 w-8 rounded-full bg-green-100 dark:bg-green-900/50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $invoice->invoice_number }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($invoice->total, 2) }}</p>
                        </div>
                        <span class="text-xs px-2 py-0.5 rounded-full
                            @if($invoice->status === 'paid') bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300
                            @elseif($invoice->status === 'draft') bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                            @elseif($invoice->status === 'sent') bg-brand-100 text-brand-800 dark:bg-brand-900/50 dark:text-brand-300
                            @else bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300
                            @endif">
                            {{ ucfirst($invoice->status) }}
                        </span>
                    </a>
                @endforeach
            </div>
        @endif

        @if(isset($results['bills']))
            <div class="border-b border-gray-100 dark:border-gray-700">
                <div class="px-3 py-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/50">
                    Bills
                </div>
                @foreach($results['bills'] as $bill)
                    <a href="{{ route('bills.show', $bill->id) }}"
                       wire:click="selectResult"
                       class="flex items-center gap-3 px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <div class="flex-shrink-0 h-8 w-8 rounded-full bg-red-100 dark:bg-red-900/50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $bill->bill_number }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($bill->total, 2) }}</p>
                        </div>
                        <span class="text-xs px-2 py-0.5 rounded-full
                            @if($bill->status === 'paid') bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300
                            @elseif($bill->status === 'draft') bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                            @else bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300
                            @endif">
                            {{ ucfirst($bill->status) }}
                        </span>
                    </a>
                @endforeach
            </div>
        @endif

        @if(isset($results['items']))
            <div class="border-b border-gray-100 dark:border-gray-700">
                <div class="px-3 py-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/50">
                    Items
                </div>
                @foreach($results['items'] as $item)
                    <a href="{{ route('items.show', $item->id) }}"
                       wire:click="selectResult"
                       class="flex items-center gap-3 px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <div class="flex-shrink-0 h-8 w-8 rounded-full bg-amber-100 dark:bg-amber-900/50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $item->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item->sku }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

        @if(isset($results['employees']))
            <div>
                <div class="px-3 py-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/50">
                    Employees
                </div>
                @foreach($results['employees'] as $employee)
                    <a href="{{ route('employees.show', $employee->id) }}"
                       wire:click="selectResult"
                       class="flex items-center gap-3 px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <div class="flex-shrink-0 h-8 w-8 rounded-full bg-accent-100 dark:bg-accent-900/50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-accent-700 dark:text-accent-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $employee->first_name }} {{ $employee->last_name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $employee->employee_id }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
