{{-- Auto-breadcrumb navigation component --}}
{{-- Can be used with explicit items: <x-breadcrumbs :items="[['label' => 'Invoices', 'url' => route('invoices.index')], ['label' => 'INV-001']]" /> --}}
{{-- Or auto-generates from current route name when no items provided --}}
@props(['items' => null])

@php
    $crumbs = $items;

    if ($crumbs === null) {
        $crumbs = [];
        $routeName = request()->route()?->getName();

        if ($routeName && $routeName !== 'dashboard') {
            // Route naming convention: {resource}.{action} e.g. invoices.show, invoices.create
            $parts = explode('.', $routeName);
            $resource = $parts[0] ?? null;
            $action = $parts[1] ?? 'index';

            // Human-readable resource labels
            $labels = [
                'invoices' => 'Invoices',
                'bills' => 'Bills',
                'expenses' => 'Expenses',
                'customers' => 'Customers',
                'vendors' => 'Vendors',
                'items' => 'Items',
                'item-categories' => 'Item Categories',
                'employees' => 'Employees',
                'payrolls' => 'Payroll',
                'leaves' => 'Leaves',
                'salary-structures' => 'Salary Structures',
                'allowances' => 'Allowances',
                'deductions' => 'Deductions',
                'departments' => 'Departments',
                'designations' => 'Designations',
                'chart-of-accounts' => 'Chart of Accounts',
                'journals' => 'Journals',
                'accrual-schedules' => 'Prepaid & Deferred Schedules',
                'banks' => 'Banks',
                'bank-feeds' => 'Bank feeds',
                'bank-transfers' => 'Bank Transfers',
                'bank-transfer-categories' => 'Transfer Categories',
                'budgets' => 'Budgets',
                'accounting-periods' => 'Accounting Periods',
                'opening-balances' => 'Opening Balances',
                'tax-rates' => 'Tax Rates',
                'tax-groups' => 'Tax Groups',
                'fixed-assets' => 'Fixed Assets',
                'categories' => 'Categories',
                'sales-orders' => 'Sales Orders',
                'sales-receipts' => 'Sales Receipts',
                'payments-received' => 'Payments Received',
                'payments-made' => 'Payments Made',
                'vendor-credits' => 'Supplier Credits',
                'supplier-advances' => 'Supplier Advances',
                'recurring-invoices' => 'Recurring Invoices',
                'inventory' => 'Inventory',
                'reports' => 'Reports',
                'settings' => 'Settings',
                'imports' => 'Imports',
                'activity-logs' => 'Activity Logs',
                'payment-methods' => 'Payment Methods',
                'withholding-tax' => 'Withholding Tax',
            ];

            // Sections whose first page isn't named "{resource}.index".
            $indexRoutes = ['withholding-tax' => 'withholding-tax.setup'];
            $sectionActions = ['settings' => ['messaging' => 'SMS & WhatsApp'], 'withholding-tax' => ['setup' => 'Rates & settings', 'schedule' => 'WHT payable schedule', 'receivable' => 'WHT credit notes']];

            $label = $labels[$resource] ?? ucwords(str_replace('-', ' ', $resource ?? ''));

            if ($resource) {
                // Try to build index route
                $indexRoute = null;
                try {
                    $indexRoute = route($indexRoutes[$resource] ?? $resource . '.index');
                } catch (\Exception $e) {
                    // Route may not exist
                }

                if ($action === 'index') {
                    $crumbs[] = ['label' => $label];
                } else {
                    if ($indexRoute) {
                        $crumbs[] = ['label' => $label, 'url' => $indexRoute];
                    }

                    $actionLabels = [
                        'create' => 'Create',
                        'edit' => 'Edit',
                        'show' => 'View',
                    ];
                    $crumbs[] = ['label' => $sectionActions[$resource][$action] ?? $actionLabels[$action] ?? ucfirst($action)];
                }
            }
        }
    }
@endphp

@if(count($crumbs) > 0)
<nav class="flex mb-4" aria-label="Breadcrumb">
    <ol class="inline-flex items-center space-x-1 md:space-x-2 text-sm">
        <li class="inline-flex items-center">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                </svg>
                Dashboard
            </a>
        </li>
        @foreach($crumbs as $item)
            <li class="flex items-center">
                <svg class="w-4 h-4 text-gray-400 mx-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                </svg>
                @if($loop->last || !isset($item['url']))
                    <span class="text-gray-700 dark:text-gray-200 font-medium">{{ $item['label'] }}</span>
                @else
                    <a href="{{ $item['url'] }}" class="text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                        {{ $item['label'] }}
                    </a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
@endif
