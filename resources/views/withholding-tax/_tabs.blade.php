{{-- Tabs across the withholding tax pages (tables plan T7). --}}
@php
    $whtTabs = collect([
        'withholding-tax.setup' => 'Rates & settings',
        'withholding-tax.schedule' => 'WHT payable schedule',
        'withholding-tax.receivable' => 'WHT credit notes receivable',
    ])->filter(fn ($label, $name) => \Illuminate\Support\Facades\Route::has($name))
        ->map(fn ($label, $name) => ['label' => $label, 'href' => route($name)])->all();
    $whtActive = collect(array_keys($whtTabs))->first(fn ($name) => request()->routeIs($name)) ?? '';
@endphp
<x-table.tabs :tabs="$whtTabs" :active="$whtActive" label="Withholding tax" class="no-print" />
