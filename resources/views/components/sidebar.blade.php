<!-- Fixed Sidebar -->
<aside class="fixed inset-y-0 left-0 z-50 w-64 flex flex-col bg-gray-900 sidebar-transition"
       :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
       x-cloak>
    
    <!-- Logo -->
    <div class="flex h-16 items-center justify-between px-4 bg-gray-800 flex-shrink-0">
        <a href="{{ route('dashboard') }}" class="flex items-center space-x-2">
            <svg class="h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
            <span class="text-xl font-bold text-white">MyBooks</span>
        </a>
        <button @click="sidebarOpen = false" class="lg:hidden p-1 rounded-md text-gray-400 hover:text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-white">
            <span class="sr-only">Close sidebar</span>
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1 sidebar-scroll">
        <!-- Dashboard -->
        @can('view dashboard')
        <a href="{{ route('dashboard') }}" 
           class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('dashboard') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
            <svg class="mr-3 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            Dashboard
        </a>
        @endcan

        <!-- Items Module -->
        @canany(['view items', 'view inventory'])
        <div x-data="{ open: {{ request()->is('items*') || request()->is('inventory*') || request()->is('warehouses*') || request()->is('stock-transfers*') || request()->is('bill-of-materials*') || request()->is('assembly-orders*') ? 'true' : 'false' }} }">
            <button @click="open = !open" 
                    class="w-full group flex items-center justify-between px-3 py-2 text-sm font-medium rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white transition-colors">
                <div class="flex items-center">
                    <svg class="mr-3 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                    Items
                </div>
                <svg :class="open ? 'rotate-90' : ''" class="h-4 w-4 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
            <div x-show="open" x-collapse x-cloak class="mt-1 space-y-1 pl-10">
                @can('view items')
                <a href="{{ route('items.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('items.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Items</a>
                @endcan
                <a href="{{ route('item-categories.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('item-categories.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Item Categories</a>
                @can('view inventory')
                <a href="{{ route('inventory.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('inventory.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Inventory</a>
                @endcan
                @if(\App\Http\Middleware\EnsureFeatureEnabled::enabled('warehouses'))
                @can('view inventory')
                <a href="{{ route('warehouses.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('warehouses.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Warehouses</a>
                @endcan
                @endif
                @if(\App\Models\StockTransfer::moduleOn())
                @can('adjust inventory')
                <a href="{{ route('stock-transfers.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('stock-transfers.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Stock transfers</a>
                @endcan
                @endif
                @if(\App\Http\Middleware\EnsureFeatureEnabled::enabled('assembly'))
                @can('view items')
                <a href="{{ route('bill-of-materials.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('bill-of-materials.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Bills of materials</a>
                @endcan
                @can('adjust inventory')
                <a href="{{ route('assembly-orders.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('assembly-orders.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Assembly orders</a>
                @endcan
                @endif
            </div>
        </div>
        @endcanany

        <!-- Sales Module -->
        @canany(['view customers', 'view invoices', 'view sales-orders', 'view sales-receipts', 'view payments-received'])
        <div x-data="{ open: {{ request()->is('sales*') || request()->is('customers*') || request()->is('invoices*') || request()->is('quotations*') || request()->is('delivery-notes*') || request()->is('credit-notes*') || request()->is('e-invoices*') ? 'true' : 'false' }} }">
            <button @click="open = !open" 
                    class="w-full group flex items-center justify-between px-3 py-2 text-sm font-medium rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white transition-colors">
                <div class="flex items-center">
                    <svg class="mr-3 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    Sales
                </div>
                <svg :class="open ? 'rotate-90' : ''" class="h-4 w-4 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
            <div x-show="open" x-collapse x-cloak class="mt-1 space-y-1 pl-10">
                @can('view customers')
                <a href="{{ route('customers.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('customers.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Customers</a>
                @endcan
                @if(\App\Http\Middleware\EnsureFeatureEnabled::enabled('quotations'))
                @can('view invoices')
                <a href="{{ route('quotations.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('quotations.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Quotations</a>
                @endcan
                @endif
                @can('view invoices')
                <a href="{{ route('invoices.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('invoices.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Invoices</a>
                @endcan
                @can('view sales-orders')
                <a href="{{ route('sales-orders.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('sales-orders.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Sales Orders</a>
                @endcan
                @if(\App\Http\Middleware\EnsureFeatureEnabled::enabled('delivery_notes'))
                @can('view invoices')
                <a href="{{ route('delivery-notes.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('delivery-notes.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Delivery Notes</a>
                @endcan
                @endif
                @if(\App\Http\Middleware\EnsureFeatureEnabled::enabled('credit_notes'))
                @can('view invoices')
                <a href="{{ route('credit-notes.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('credit-notes.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Credit Notes</a>
                @endcan
                @endif
                @if(\App\Http\Middleware\EnsureFeatureEnabled::enabled('e_invoicing'))
                @can('view e-invoices')
                <a href="{{ route('e-invoices.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('e-invoices.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">E-invoices</a>
                @endcan
                @endif
                @can('view sales-receipts')
                <a href="{{ route('sales-receipts.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('sales-receipts.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Sales Receipt</a>
                @endcan
                @can('view payments-received')
                <a href="{{ route('payments-received.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('payments-received.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Payment Received</a>
                @endcan
            </div>
        </div>
        @endcanany

        <!-- Purchases Module -->
        @canany(['view vendors', 'view expenses', 'view bills', 'view purchase-orders', 'view recurrent-bills', 'view recurrent-expenses', 'view payments-made'])
        <div x-data="{ open: {{ request()->is('purchases*') || request()->is('vendors*') || request()->is('bills*') || request()->is('purchase-orders*') || request()->is('expenses*') || request()->is('vendor-credits*') || request()->is('supplier-advances*') ? 'true' : 'false' }} }">
            <button @click="open = !open" 
                    class="w-full group flex items-center justify-between px-3 py-2 text-sm font-medium rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white transition-colors">
                <div class="flex items-center">
                    <svg class="mr-3 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    Purchases
                </div>
                <svg :class="open ? 'rotate-90' : ''" class="h-4 w-4 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
            <div x-show="open" x-collapse x-cloak class="mt-1 space-y-1 pl-10">
                @can('view vendors')
                <a href="{{ route('vendors.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('vendors.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Vendors</a>
                @endcan
                @can('view expenses')
                <a href="{{ route('expenses.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('expenses.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Expenses</a>
                @endcan
                @can('view bills')
                <a href="{{ route('bills.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('bills.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Bills</a>
                @endcan
                @can('view purchase-orders')
                <a href="{{ route('purchase-orders.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('purchase-orders.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Purchase Orders</a>
                @endcan
                @can('view recurrent-bills')
                <a href="{{ route('recurrent-bills.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('recurrent-bills.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Recurrent Bills</a>
                @endcan
                @can('view recurrent-expenses')
                <a href="{{ route('recurrent-expenses.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('recurrent-expenses.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Recurrent Expenses</a>
                @endcan
                @can('view payments-made')
                <a href="{{ route('payments-made.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('payments-made.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Payments Made</a>
                @endcan
                @can('view bills')
                <a href="{{ route('vendor-credits.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('vendor-credits.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Supplier Credits</a>
                @endcan
                @can('view payments-made')
                <a href="{{ route('supplier-advances.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('supplier-advances.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Supplier Advances</a>
                @endcan
            </div>
        </div>
        @endcanany

        <!-- Human Resource Module -->
        @canany(['view employees', 'view departments', 'view designations', 'view leaves', 'view leave-types'])
        <div x-data="{ open: {{ request()->is('hr*') || request()->is('employees*') || request()->is('departments*') ? 'true' : 'false' }} }">
            <button @click="open = !open" 
                    class="w-full group flex items-center justify-between px-3 py-2 text-sm font-medium rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white transition-colors">
                <div class="flex items-center">
                    <svg class="mr-3 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    Human Resource
                </div>
                <svg :class="open ? 'rotate-90' : ''" class="h-4 w-4 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
            <div x-show="open" x-collapse x-cloak class="mt-1 space-y-1 pl-10">
                @can('view employees')
                <a href="{{ route('employees.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('employees.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Employee</a>
                @endcan
                @can('view departments')
                <a href="{{ route('departments.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('departments.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Departments</a>
                @endcan
                @can('view designations')
                <a href="{{ route('designations.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('designations.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Designation</a>
                @endcan
                @can('view leave-types')
                <a href="{{ route('leave-types.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('leave-types.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Leave Types</a>
                @endcan
                @can('view leaves')
                <a href="{{ route('leaves.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('leaves.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Leaves</a>
                @endcan
            </div>
        </div>
        @endcanany

        <!-- Payroll Module -->
        @can('view payroll')
        <div x-data="{ open: {{ request()->routeIs('payroll.*') || request()->routeIs('salary-structures.*') || request()->routeIs('allowances.*') || request()->routeIs('deductions.*') ? 'true' : 'false' }} }">
            <button @click="open = !open" 
                    class="w-full group flex items-center justify-between px-3 py-2 text-sm font-medium rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white transition-colors">
                <div class="flex items-center">
                    <svg class="mr-3 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    Payroll
                </div>
                <svg :class="open ? 'rotate-90' : ''" class="h-4 w-4 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
            <div x-show="open" x-collapse x-cloak class="mt-1 space-y-1 pl-10">
                <a href="{{ route('payroll.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('payroll.*') && ! request()->routeIs('payroll.liabilities*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Payroll</a>
                <a href="{{ route('payroll.liabilities') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('payroll.liabilities*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">PAYE, Pension &amp; Levies</a>
                <a href="{{ route('salary-structures.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('salary-structures.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Salary Structure</a>
                <a href="{{ route('allowances.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('allowances.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Allowances</a>
                <a href="{{ route('deductions.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('deductions.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Deductions</a>
            </div>
        </div>
        @endcan

        <!-- Accountant Module -->
        @canany(['view journals', 'view chart-of-accounts', 'view banks', 'view budgets', 'view withholding-tax', 'view accrual-schedules'])
        <div x-data="{ open: {{ request()->is('accountant*') || request()->is('journals*') || request()->is('accrual-schedules*') || request()->is('chart-of-accounts*') || request()->is('banks*') || request()->is('bank-feeds*') || request()->is('budgets*') || request()->is('withholding-tax*') ? 'true' : 'false' }} }">
            <button @click="open = !open" 
                    class="w-full group flex items-center justify-between px-3 py-2 text-sm font-medium rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white transition-colors">
                <div class="flex items-center">
                    <svg class="mr-3 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    Accountant
                </div>
                <svg :class="open ? 'rotate-90' : ''" class="h-4 w-4 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
            <div x-show="open" x-collapse x-cloak class="mt-1 space-y-1 pl-10">
                @can('view banks')
                <a href="{{ route('banks.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('banks.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Banks</a>
                @endcan
                @can('view banks')
                @if(\App\Http\Middleware\EnsureFeatureEnabled::enabled('bank_feeds'))
                <a href="{{ route('bank-feeds.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('bank-feeds.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Bank feeds</a>
                @endif
                @endcan
                @can('view journals')
                <a href="{{ route('journals.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('journals.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Manual Journals</a>
                @endcan
                @if(\App\Http\Middleware\EnsureFeatureEnabled::enabled('prepaid_schedules'))
                @can('view accrual-schedules')
                <a href="{{ route('accrual-schedules.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('accrual-schedules.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Prepaid &amp; Deferred</a>
                @endcan
                @endif
                @can('edit journals')
                <a href="{{ route('journals.bulk-update') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('journals.bulk-update') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Bulk Update</a>
                @endcan
                @can('view chart-of-accounts')
                <a href="{{ route('chart-of-accounts.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('chart-of-accounts.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Chart of Accounts</a>
                <a href="{{ route('accounting-periods.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('accounting-periods.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Accounting Periods</a>
                @endcan
                @can('view withholding-tax')
                <a href="{{ route('withholding-tax.setup') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('withholding-tax.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Withholding Tax</a>
                @endcan
                @can('view budgets')
                @php
                    $currentPlan = auth()->user()?->tenant?->activeSubscription?->plan?->slug;
                    $hasBudgetAccess = in_array($currentPlan, ['professional', 'enterprise']);
                @endphp
                @if($hasBudgetAccess)
                <a href="{{ route('budgets.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('budgets.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">
                    Budgets
                    <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">Pro</span>
                </a>
                @else
                <span class="block px-3 py-2 text-sm rounded-lg text-gray-500 cursor-not-allowed" title="Available on Professional and Enterprise plans">
                    Budgets
                    <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-600 text-gray-400">Pro</span>
                </span>
                @endif
                @endcan
            </div>
        </div>
        @endcanany

        <!-- Fixed Assets Module -->
        @canany(['view fixed-assets', 'view fixed-asset-categories'])
        <div x-data="{ open: {{ request()->is('fixed-assets*') || request()->is('fixed-asset-categories*') ? 'true' : 'false' }} }">
            <button @click="open = !open" 
                    class="w-full group flex items-center justify-between px-3 py-2 text-sm font-medium rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white transition-colors">
                <div class="flex items-center">
                    <svg class="mr-3 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                    Fixed Assets
                </div>
                <svg :class="open ? 'rotate-90' : ''" class="h-4 w-4 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
            <div x-show="open" x-collapse x-cloak class="mt-1 space-y-1 pl-10">
                @can('view fixed-assets')
                <a href="{{ route('fixed-assets.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('fixed-assets.index') || request()->routeIs('fixed-assets.show') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">All Assets</a>
                @endcan
                @can('create fixed-assets')
                <a href="{{ route('fixed-assets.create') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('fixed-assets.create') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Add New Asset</a>
                @endcan
                @can('view fixed-assets')
                <a href="{{ route('fixed-assets.register') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('fixed-assets.register') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Asset Register</a>
                @endcan
                @can('view fixed-asset-categories')
                <a href="{{ route('fixed-asset-categories.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('fixed-asset-categories.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Categories</a>
                @endcan
            </div>
        </div>
        @endcanany

        <!-- Reports -->
        @can('view reports')
        <a href="{{ route('reports.index') }}" 
           class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('reports.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
            <svg class="mr-3 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Reports
        </a>
        @endcan

        <!-- Monthly VAT return -->
        @can('view reports')
        <a href="{{ route('reports.vat-return') }}"
           class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('reports.vat-return*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
            <svg class="mr-3 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
            </svg>
            VAT Return
        </a>
        @endcan

        <!-- Analytics Dashboard -->
        @can('view reports')
        <a href="{{ route('analytics.index') }}" 
           class="group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('analytics.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
            <svg class="mr-3 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
            </svg>
            Analytics
        </a>
        @endcan

        <!-- Settings Module -->
        @canany(['view settings', 'view users', 'view roles', 'view tax-rates', 'export reports', 'import data'])
        <div x-data="{ open: {{ request()->is('settings*') || request()->is('activity-logs*') || request()->is('tax-rates*') || request()->is('tax-groups*') || request()->is('exports*') || request()->is('imports*') ? 'true' : 'false' }} }">
            <button @click="open = !open" 
                    class="w-full group flex items-center justify-between px-3 py-2 text-sm font-medium rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white transition-colors">
                <div class="flex items-center">
                    <svg class="mr-3 h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Settings
                </div>
                <svg :class="open ? 'rotate-90' : ''" class="h-4 w-4 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
            <div x-show="open" x-collapse x-cloak class="mt-1 space-y-1 pl-10">
                @can('view settings')
                <a href="{{ route('settings.company') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('settings.company') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Company Profile</a>
                <a href="{{ route('settings.notifications') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('settings.notifications*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Notifications</a>
                @if(\App\Http\Middleware\EnsureFeatureEnabled::enabled('sms_whatsapp'))
                <a href="{{ route('settings.messaging') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('settings.messaging*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">SMS &amp; WhatsApp</a>
                @endif
                @if(\App\Http\Middleware\EnsureFeatureEnabled::enabled('e_invoicing'))
                @can('view e-invoices')
                <a href="{{ route('settings.e-invoicing') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('settings.e-invoicing*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">E-invoicing</a>
                @endcan
                @endif
                <a href="{{ route('settings.invoice-templates.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('settings.invoice-templates*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Invoice Templates</a>
                <a href="{{ route('settings.subscription') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('settings.subscription') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">
                    Subscription
                </a>
                @endcan
                @can('view tax-rates')
                <a href="{{ route('tax-rates.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('tax-rates.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Tax Rates</a>
                <a href="{{ route('tax-groups.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('tax-groups.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Tax Groups</a>
                @endcan
                @can('view users')
                <a href="{{ route('settings.users') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('settings.users*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Users Management</a>
                @endcan
                @can('view roles')
                <a href="{{ route('settings.roles') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('settings.roles*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Roles and Permission</a>
                @endcan
                @can('view settings')
                <a href="{{ route('activity-logs.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('activity-logs.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Activity Logs</a>
                @endcan
                @can('export reports')
                <a href="{{ route('exports.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('exports.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Data Export</a>
                @endcan
                @can('import data')
                <a href="{{ route('imports.index') }}" class="block px-3 py-2 text-sm rounded-lg {{ request()->routeIs('imports.*') ? 'text-white bg-gray-800' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">Data Import</a>
                @endcan
            </div>
        </div>
        @endcanany
    </nav>

    <!-- User info at bottom -->
    <div class="border-t border-gray-700 p-4 flex-shrink-0">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <div class="h-9 w-9 rounded-full bg-indigo-600 flex items-center justify-center">
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
