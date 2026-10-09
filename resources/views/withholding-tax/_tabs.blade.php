{{-- Tabs across the withholding tax pages. --}}
@php
    $tabs = [
        ['route' => 'withholding-tax.setup', 'label' => 'Rates & settings'],
        ['route' => 'withholding-tax.schedule', 'label' => 'WHT payable schedule'],
        ['route' => 'withholding-tax.receivable', 'label' => 'WHT credit notes receivable'],
    ];
@endphp
<nav class="flex flex-wrap gap-2 mb-6 no-print" aria-label="Withholding tax">
    @foreach($tabs as $tab)
        @if(\Illuminate\Support\Facades\Route::has($tab['route']))
            <a href="{{ route($tab['route']) }}"
               @if(request()->routeIs($tab['route'])) aria-current="page" @endif
               class="px-3 py-2 text-sm rounded-md {{ request()->routeIs($tab['route']) ? 'bg-brand-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                {{ $tab['label'] }}
            </a>
        @endif
    @endforeach
</nav>
