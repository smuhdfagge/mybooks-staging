<!-- Fixed Sidebar -->
<aside id="app-sidebar" class="fixed inset-y-0 left-0 z-50 print:hidden w-64 flex flex-col bg-brand-900 sidebar-transition"
       :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
       x-cloak>
    
    <!-- Logo -->
    <div class="flex h-16 items-center justify-between px-4 bg-brand-950 flex-shrink-0">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 rounded focus-ring">
            <x-brand-mark variant="reversed" class="h-8 w-8" />
            <span class="text-xl font-semibold text-white">MyBooks</span>
        </a>
        <button type="button" @click="closeSidebar()" class="lg:hidden p-1 rounded-md text-gray-400 hover:text-white hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-white">
            <span class="sr-only">Close sidebar</span>
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- Navigation: by business area (audit MB-40), each area's pages in the order the work goes. -->
    @php
        $on = fn (string ...$routes) => request()->routeIs(...$routes);
        $feature = fn (string $flag) => \App\Http\Middleware\EnsureFeatureEnabled::enabled($flag);
    @endphp
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1 sidebar-scroll" aria-label="Main">
        @can('view dashboard')
        <a href="{{ route('dashboard') }}" @if ($on('dashboard')) aria-current="page" @endif
           class="group flex min-h-9 items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ $on('dashboard') ? 'nav-active' : 'text-gray-300 hover:bg-brand-800 hover:text-white' }}">
            <svg class="mr-3 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            Dashboard
        </a>
        @endcan

        {{-- Sales: from the customer to the money in --}}
        @canany(['view customers', 'view invoices', 'view sales-orders', 'view sales-receipts', 'view payments-received'])
        <x-sidebar.group label="Sales" icon="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
            :open="$on('customers.*', 'quotations.*', 'sales-orders.*', 'invoices.*', 'delivery-notes.*', 'sales-receipts.*', 'payments-received.*', 'credit-notes.*', 'e-invoices.*')">
            @can('view customers')
            <x-sidebar.link :href="route('customers.index')" :active="$on('customers.*')">Customers</x-sidebar.link>
            @endcan
            @if ($feature('quotations'))
            @can('view invoices')
            <x-sidebar.link :href="route('quotations.index')" :active="$on('quotations.*')">Quotations</x-sidebar.link>
            @endcan
            @endif
            @can('view sales-orders')
            <x-sidebar.link :href="route('sales-orders.index')" :active="$on('sales-orders.*')">Sales Orders</x-sidebar.link>
            @endcan
            @can('view invoices')
            <x-sidebar.link :href="route('invoices.index')" :active="$on('invoices.*')">Invoices</x-sidebar.link>
            @endcan
            @if ($feature('delivery_notes'))
            @can('view invoices')
            <x-sidebar.link :href="route('delivery-notes.index')" :active="$on('delivery-notes.*')">Delivery Notes</x-sidebar.link>
            @endcan
            @endif
            @can('view sales-receipts')
            <x-sidebar.link :href="route('sales-receipts.index')" :active="$on('sales-receipts.*')">Sales Receipts</x-sidebar.link>
            @endcan
            @can('view payments-received')
            <x-sidebar.link :href="route('payments-received.index')" :active="$on('payments-received.*')">Payments Received</x-sidebar.link>
            @endcan
            @if ($feature('credit_notes'))
            @can('view invoices')
            <x-sidebar.link :href="route('credit-notes.index')" :active="$on('credit-notes.*')">Credit Notes</x-sidebar.link>
            @endcan
            @endif
            @if ($feature('e_invoicing'))
            @can('view e-invoices')
            <x-sidebar.link :href="route('e-invoices.index')" :active="$on('e-invoices.*')">E-invoices</x-sidebar.link>
            @endcan
            @endif
        </x-sidebar.group>
        @endcanany

        {{-- Purchases: from the vendor to the money out --}}
        @canany(['view vendors', 'view expenses', 'view bills', 'view purchase-orders', 'view recurrent-bills', 'view recurrent-expenses', 'view payments-made'])
        <x-sidebar.group label="Purchases" icon="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"
            :open="$on('vendors.*', 'purchase-orders.*', 'bills.*', 'expenses.*', 'payments-made.*', 'vendor-credits.*', 'supplier-advances.*', 'recurrent-bills.*', 'recurrent-expenses.*')">
            @can('view vendors')
            <x-sidebar.link :href="route('vendors.index')" :active="$on('vendors.*')">Vendors</x-sidebar.link>
            @endcan
            @can('view purchase-orders')
            <x-sidebar.link :href="route('purchase-orders.index')" :active="$on('purchase-orders.*')">Purchase Orders</x-sidebar.link>
            @endcan
            @can('view bills')
            <x-sidebar.link :href="route('bills.index')" :active="$on('bills.*')">Bills</x-sidebar.link>
            @endcan
            @can('view expenses')
            <x-sidebar.link :href="route('expenses.index')" :active="$on('expenses.*')">Expenses</x-sidebar.link>
            @endcan
            @can('view payments-made')
            <x-sidebar.link :href="route('payments-made.index')" :active="$on('payments-made.*')">Payments Made</x-sidebar.link>
            @endcan
            @can('view bills')
            <x-sidebar.link :href="route('vendor-credits.index')" :active="$on('vendor-credits.*')">Supplier Credits</x-sidebar.link>
            @endcan
            @can('view payments-made')
            <x-sidebar.link :href="route('supplier-advances.index')" :active="$on('supplier-advances.*')">Supplier Advances</x-sidebar.link>
            @endcan
            @can('view recurrent-bills')
            <x-sidebar.link :href="route('recurrent-bills.index')" :active="$on('recurrent-bills.*')">Recurring Bills</x-sidebar.link>
            @endcan
            @can('view recurrent-expenses')
            <x-sidebar.link :href="route('recurrent-expenses.index')" :active="$on('recurrent-expenses.*')">Recurring Expenses</x-sidebar.link>
            @endcan
        </x-sidebar.group>
        @endcanany

        {{-- Inventory --}}
        @canany(['view items', 'view inventory'])
        <x-sidebar.group label="Items & Stock" icon="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
            :open="$on('items.*', 'item-categories.*', 'inventory.*', 'warehouses.*', 'stock-transfers.*', 'bill-of-materials.*', 'assembly-orders.*')">
            @can('view items')
            <x-sidebar.link :href="route('items.index')" :active="$on('items.*')">Items</x-sidebar.link>
            @endcan
            <x-sidebar.link :href="route('item-categories.index')" :active="$on('item-categories.*')">Item Categories</x-sidebar.link>
            @can('view inventory')
            <x-sidebar.link :href="route('inventory.index')" :active="$on('inventory.*')">Stock on Hand</x-sidebar.link>
            @if ($feature('warehouses'))
            <x-sidebar.link :href="route('warehouses.index')" :active="$on('warehouses.*')">Warehouses</x-sidebar.link>
            @endif
            @endcan
            @if (\App\Models\StockTransfer::moduleOn())
            @can('adjust inventory')
            <x-sidebar.link :href="route('stock-transfers.index')" :active="$on('stock-transfers.*')">Stock Transfers</x-sidebar.link>
            @endcan
            @endif
            @if ($feature('assembly'))
            @can('view items')
            <x-sidebar.link :href="route('bill-of-materials.index')" :active="$on('bill-of-materials.*')">Bills of Materials</x-sidebar.link>
            @endcan
            @can('adjust inventory')
            <x-sidebar.link :href="route('assembly-orders.index')" :active="$on('assembly-orders.*')">Assembly Orders</x-sidebar.link>
            @endcan
            @endif
        </x-sidebar.group>
        @endcanany

        {{-- Accounting: banks and the ledger --}}
        @canany(['view journals', 'view chart-of-accounts', 'view banks', 'view budgets', 'view accrual-schedules'])
        <x-sidebar.group label="Accounting" icon="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"
            :open="$on('banks.*', 'bank-feeds.*', 'journals.*', 'accrual-schedules.*', 'chart-of-accounts.*', 'accounting-periods.*', 'budgets.*')">
            @can('view banks')
            <x-sidebar.link :href="route('banks.index')" :active="$on('banks.*')">Banks</x-sidebar.link>
            @if ($feature('bank_feeds'))
            <x-sidebar.link :href="route('bank-feeds.index')" :active="$on('bank-feeds.*')">Bank Feeds</x-sidebar.link>
            @endif
            @endcan
            @can('view journals')
            <x-sidebar.link :href="route('journals.index')" :active="$on('journals.*')">Manual Journals</x-sidebar.link>
            @endcan
            @if ($feature('prepaid_schedules'))
            @can('view accrual-schedules')
            <x-sidebar.link :href="route('accrual-schedules.index')" :active="$on('accrual-schedules.*')">Prepaid &amp; Deferred</x-sidebar.link>
            @endcan
            @endif
            @can('view chart-of-accounts')
            <x-sidebar.link :href="route('chart-of-accounts.index')" :active="$on('chart-of-accounts.*')">Chart of Accounts</x-sidebar.link>
            <x-sidebar.link :href="route('accounting-periods.index')" :active="$on('accounting-periods.*')">Accounting Periods</x-sidebar.link>
            @endcan
            @can('view budgets')
            @php $hasBudgetAccess = in_array(auth()->user()?->tenant?->activeSubscription?->plan?->slug, ['professional', 'enterprise'], true); @endphp
            @if ($hasBudgetAccess)
            <x-sidebar.link :href="route('budgets.index')" :active="$on('budgets.*')">Budgets</x-sidebar.link>
            @else
            {{-- The plan gate, said in words (audit MB-52): a hover title can't be read on a phone. --}}
            @can('view settings')
            <x-sidebar.link :href="route('settings.subscription')" data-testid="budgets-gate">
                <span>Budgets <span class="block text-xs text-gray-400">On Professional and Enterprise. See plans</span></span>
            </x-sidebar.link>
            @else
            <p class="px-3 py-2 text-sm text-gray-400" data-testid="budgets-gate">Budgets <span class="block text-xs">On Professional and Enterprise plans</span></p>
            @endcan
            @endif
            @endcan
        </x-sidebar.group>
        @endcanany

        {{-- Tax: returns and rates --}}
        @canany(['view vat-returns', 'view withholding-tax', 'view tax-rates'])
        <x-sidebar.group label="Tax" icon="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"
            :open="$on('reports.vat-return*', 'withholding-tax.*', 'tax-rates.*', 'tax-groups.*')">
            @can('view vat-returns')
            <x-sidebar.link :href="route('reports.vat-return')" :active="$on('reports.vat-return*')">VAT Return</x-sidebar.link>
            @endcan
            @can('view withholding-tax')
            <x-sidebar.link :href="route('withholding-tax.setup')" :active="$on('withholding-tax.*')">Withholding Tax</x-sidebar.link>
            @endcan
            @can('view tax-rates')
            <x-sidebar.link :href="route('tax-rates.index')" :active="$on('tax-rates.*')">Tax Rates</x-sidebar.link>
            <x-sidebar.link :href="route('tax-groups.index')" :active="$on('tax-groups.*')">Tax Groups</x-sidebar.link>
            @endcan
        </x-sidebar.group>
        @endcanany

        @canany(['view fixed-assets', 'view fixed-asset-categories'])
        <x-sidebar.group label="Fixed Assets" icon="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
            :open="$on('fixed-assets.*', 'fixed-asset-categories.*')">
            @can('view fixed-assets')
            <x-sidebar.link :href="route('fixed-assets.index')" :active="$on('fixed-assets.index', 'fixed-assets.show')">Assets</x-sidebar.link>
            @endcan
            @can('create fixed-assets')
            <x-sidebar.link :href="route('fixed-assets.create')" :active="$on('fixed-assets.create')">Add an Asset</x-sidebar.link>
            @endcan
            @can('view fixed-assets')
            <x-sidebar.link :href="route('fixed-assets.register')" :active="$on('fixed-assets.register')">Asset Register</x-sidebar.link>
            @endcan
            @can('view fixed-asset-categories')
            <x-sidebar.link :href="route('fixed-asset-categories.index')" :active="$on('fixed-asset-categories.*')">Asset Categories</x-sidebar.link>
            @endcan
        </x-sidebar.group>
        @endcanany

        @canany(['view employees', 'view departments', 'view designations', 'view leaves', 'view leave-types'])
        <x-sidebar.group label="Human Resources" icon="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
            :open="$on('employees.*', 'departments.*', 'designations.*', 'leave-types.*', 'leaves.*')">
            @can('view employees')
            <x-sidebar.link :href="route('employees.index')" :active="$on('employees.*')">Employees</x-sidebar.link>
            @endcan
            @can('view departments')
            <x-sidebar.link :href="route('departments.index')" :active="$on('departments.*')">Departments</x-sidebar.link>
            @endcan
            @can('view designations')
            <x-sidebar.link :href="route('designations.index')" :active="$on('designations.*')">Designations</x-sidebar.link>
            @endcan
            @can('view leaves')
            <x-sidebar.link :href="route('leaves.index')" :active="$on('leaves.*')">Leave Requests</x-sidebar.link>
            @endcan
            @can('view leave-types')
            <x-sidebar.link :href="route('leave-types.index')" :active="$on('leave-types.*')">Leave Types</x-sidebar.link>
            @endcan
        </x-sidebar.group>
        @endcanany

        @can('view payroll')
        <x-sidebar.group label="Payroll" icon="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"
            :open="$on('payroll.*', 'salary-structures.*', 'allowances.*', 'deductions.*')">
            <x-sidebar.link :href="route('payroll.index')" :active="$on('payroll.*') && ! $on('payroll.liabilities*')">Pay Runs</x-sidebar.link>
            <x-sidebar.link :href="route('payroll.liabilities')" :active="$on('payroll.liabilities*')">PAYE, Pension &amp; Levies</x-sidebar.link>
            <x-sidebar.link :href="route('salary-structures.index')" :active="$on('salary-structures.*')">Salary Structures</x-sidebar.link>
            <x-sidebar.link :href="route('allowances.index')" :active="$on('allowances.*')">Allowances</x-sidebar.link>
            <x-sidebar.link :href="route('deductions.index')" :active="$on('deductions.*')">Deductions</x-sidebar.link>
        </x-sidebar.group>
        @endcan

        @can('view reports')
        <a href="{{ route('reports.index') }}" @if ($on('reports.*') && ! $on('reports.vat-return*')) aria-current="page" @endif
           class="group flex min-h-9 items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ $on('reports.*') && ! $on('reports.vat-return*') ? 'nav-active' : 'text-gray-300 hover:bg-brand-800 hover:text-white' }}">
            <svg class="mr-3 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Reports
        </a>
        <a href="{{ route('analytics.index') }}" @if ($on('analytics.*')) aria-current="page" @endif
           class="group flex min-h-9 items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ $on('analytics.*') ? 'nav-active' : 'text-gray-300 hover:bg-brand-800 hover:text-white' }}">
            <svg class="mr-3 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
            </svg>
            Analytics
        </a>
        @endcan

        @canany(['view settings', 'view users', 'view roles', 'export reports', 'import data'])
        <x-sidebar.group label="Settings" icon="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z"
            :open="$on('settings.*', 'activity-logs.*', 'exports.*', 'imports.*')">
            @can('view settings')
            <x-sidebar.link :href="route('settings.company')" :active="$on('settings.company')">Company Profile</x-sidebar.link>
            <x-sidebar.link :href="route('settings.notifications')" :active="$on('settings.notifications*')">Notifications</x-sidebar.link>
            @if ($feature('sms_whatsapp'))
            <x-sidebar.link :href="route('settings.messaging')" :active="$on('settings.messaging*')">SMS &amp; WhatsApp</x-sidebar.link>
            @endif
            @if ($feature('e_invoicing'))
            @can('view e-invoices')
            <x-sidebar.link :href="route('settings.e-invoicing')" :active="$on('settings.e-invoicing*')">E-invoicing</x-sidebar.link>
            @endcan
            @endif
            <x-sidebar.link :href="route('settings.invoice-templates.index')" :active="$on('settings.invoice-templates*')">Invoice Templates</x-sidebar.link>
            <x-sidebar.link :href="route('settings.subscription')" :active="$on('settings.subscription')">Subscription</x-sidebar.link>
            @endcan
            @can('view users')
            <x-sidebar.link :href="route('settings.users')" :active="$on('settings.users*')">Users</x-sidebar.link>
            @endcan
            @can('view roles')
            <x-sidebar.link :href="route('settings.roles')" :active="$on('settings.roles*')">Roles &amp; Permissions</x-sidebar.link>
            @endcan
            @can('view settings')
            <x-sidebar.link :href="route('activity-logs.index')" :active="$on('activity-logs.*')">Activity Log</x-sidebar.link>
            @endcan
            @can('export reports')
            <x-sidebar.link :href="route('exports.index')" :active="$on('exports.*')">Data Export</x-sidebar.link>
            @endcan
            @can('import data')
            <x-sidebar.link :href="route('imports.index')" :active="$on('imports.*')">Data Import</x-sidebar.link>
            @endcan
        </x-sidebar.group>
        @endcanany
    </nav>

    <!-- User info at bottom -->
    <div class="border-t border-brand-800 p-4 flex-shrink-0">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <div class="h-9 w-9 rounded-full bg-brand-600 ring-1 ring-brand-400/40 flex items-center justify-center">
                    <span class="text-sm font-medium text-white">{{ auth()->user() ? strtoupper(substr(auth()->user()->name, 0, 1)) : 'G' }}</span>
                </div>
            </div>
            <div class="ml-3 min-w-0 flex-1">
                <p class="text-sm font-medium text-white truncate">{{ auth()->user()->name ?? 'Guest' }}</p>
                <p class="text-xs text-gray-400 truncate">{{ auth()->user()->tenant->name ?? 'No Company' }}</p>
            </div>
        </div>
    </div>
</aside>
