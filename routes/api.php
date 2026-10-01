<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\ChartOfAccountController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\JournalController;
use App\Http\Controllers\Api\PaymentMadeController;
use App\Http\Controllers\Api\PaymentReceivedController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SalesOrderController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\TaxRateController;
use App\Http\Controllers\Api\VendorController;
use App\Services\HealthCheck;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

/*
|--------------------------------------------------------------------------
| Public Routes (No Authentication Required)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {
    // Health Check
    // Checks the database, cache, queue, storage and backups (O6): 200 when
    // everything works, 503 when something is broken.
    Route::get('health', function (HealthCheck $health) {
        $result = $health->run();

        return response()->json([
            'status' => $result['healthy'] ? 'ok' : 'error',
            'service' => 'MyBooks API',
            'version' => '1.0',
            'timestamp' => now()->toIso8601String(),
            'checks' => $result['checks'],
        ], $result['healthy'] ? 200 : 503);
    })->middleware('throttle:api-read')->name('api.health');

    // Authentication (with rate limiting for login)
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:auth-sensitive');

    // Password Reset (public, with rate limiting)
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:auth-sensitive')
        ->name('api.password.email');
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:auth-sensitive')
        ->name('api.password.reset');
    Route::post('auth/verify-reset-token', [AuthController::class, 'verifyResetToken'])
        ->middleware('throttle:auth-sensitive')
        ->name('api.password.verify');

    /*
    |--------------------------------------------------------------------------
    | Protected Routes (Authentication Required)
    |--------------------------------------------------------------------------
    */
    // throttle:api limits every route here, reads and writes separately (I8).
    // 'idempotent' replays the first result of a write sent again with the
    // same Idempotency-Key header (I5).
    Route::middleware(['auth:sanctum', 'active', 'throttle:api', 'subscription', 'tenant', 'idempotent'])->group(function () {
        // Auth routes (exempt from permission checks)
        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'logoutAll']);
            Route::get('user', [AuthController::class, 'user']);
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::put('profile', [AuthController::class, 'updateProfile']);
            Route::put('password', [AuthController::class, 'updatePassword']);
        });

        // Dashboard
        Route::middleware('permission:view dashboard')->prefix('dashboard')->group(function () {
            Route::get('/', [DashboardController::class, 'index']);
            Route::get('trends', [DashboardController::class, 'trends']);
            Route::get('recent-transactions', [DashboardController::class, 'recentTransactions']);
        });

        // Reports
        Route::middleware(['permission:view reports', 'throttle:api-reports'])->prefix('reports')->group(function () {
            Route::get('profit-loss', [ReportController::class, 'profitLoss']);
            Route::get('balance-sheet', [ReportController::class, 'balanceSheet']);
            Route::get('cash-flow', [ReportController::class, 'cashFlow']);
            Route::get('accounts-receivable', [ReportController::class, 'accountsReceivable']);
            Route::get('accounts-payable', [ReportController::class, 'accountsPayable']);
            Route::get('sales', [ReportController::class, 'sales']);
            Route::get('tax-summary', [ReportController::class, 'taxSummary']);
            Route::get('trial-balance', [ReportController::class, 'trialBalance']);
            Route::get('general-ledger', [ReportController::class, 'generalLedger']);
            Route::get('sales-by-customer', [ReportController::class, 'salesByCustomer']);
            Route::get('sales-by-item', [ReportController::class, 'salesByItem']);
            Route::get('purchase-by-vendor', [ReportController::class, 'purchaseByVendor']);
            Route::get('inventory-summary', [ReportController::class, 'inventorySummary']);
            Route::get('payroll-summary', [ReportController::class, 'payrollSummary']);
            Route::get('payroll-register', [ReportController::class, 'payrollRegister']);
            Route::get('ytd-earnings', [ReportController::class, 'ytdEarnings']);
            Route::get('tax-liability-payroll', [ReportController::class, 'taxLiabilityPayroll']);
            Route::get('employer-contributions', [ReportController::class, 'employerContributions']);
            Route::get('bank-disbursement', [ReportController::class, 'bankDisbursement']);
            Route::get('salary-revision-history', [ReportController::class, 'salaryRevisionHistory']);
            Route::get('customer-statement', [ReportController::class, 'customerStatement']);
        });

        // Search & Lookup (read-only, requires view permission on each entity)
        Route::prefix('search')->group(function () {
            Route::get('/', [SearchController::class, 'global'])->middleware('permission:view dashboard');
            Route::get('customers', [SearchController::class, 'customers'])->middleware('permission:view customers');
            Route::get('vendors', [SearchController::class, 'vendors'])->middleware('permission:view vendors');
            Route::get('items', [SearchController::class, 'items'])->middleware('permission:view items');
            Route::get('accounts', [SearchController::class, 'accounts'])->middleware('permission:view chart-of-accounts');
        });

        // Settings
        Route::prefix('settings')->group(function () {
            Route::middleware('permission:view settings')->group(function () {
                Route::get('/', [SettingsController::class, 'index']);
                Route::get('currencies', [SettingsController::class, 'currencies']);
                Route::get('payment-methods', [SettingsController::class, 'paymentMethods']);
            });
            Route::middleware('permission:edit settings')->group(function () {
                Route::put('organization', [SettingsController::class, 'updateOrganization']);
                Route::put('accounting', [SettingsController::class, 'updateAccounting']);
                Route::put('tax', [SettingsController::class, 'updateTax']);
                Route::put('custom', [SettingsController::class, 'updateCustomSettings']);
            });
        });

        // Sync (for offline mobile support)
        // Each entity checks its own view permission inside SyncController (I1).
        Route::prefix('sync')->group(function () {
            Route::get('/', [SyncController::class, 'index'])->name('api.sync.index');
            Route::get('status', [SyncController::class, 'status'])->name('api.sync.status');
            Route::get('deleted', [SyncController::class, 'deleted'])->name('api.sync.deleted');
            Route::get('{entity}', [SyncController::class, 'entity'])->name('api.sync.entity');
        });

        // Customers
        Route::middleware('permission:view customers')->group(function () {
            Route::get('customers', [CustomerController::class, 'index'])->name('api.customers.index');
            Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('api.customers.show');
            Route::get('customers/{customer}/statistics', [CustomerController::class, 'statistics'])->name('api.customers.statistics');
        });
        Route::post('customers', [CustomerController::class, 'store'])->middleware(['permission:create customers', 'throttle:api-write'])->name('api.customers.store');
        Route::put('customers/{customer}', [CustomerController::class, 'update'])->middleware(['permission:edit customers', 'throttle:api-write'])->name('api.customers.update');
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->middleware(['permission:delete customers', 'throttle:api-write'])->name('api.customers.destroy');

        // Vendors
        Route::middleware('permission:view vendors')->group(function () {
            Route::get('vendors', [VendorController::class, 'index'])->name('api.vendors.index');
            Route::get('vendors/{vendor}', [VendorController::class, 'show'])->name('api.vendors.show');
            Route::get('vendors/{vendor}/statistics', [VendorController::class, 'statistics'])->name('api.vendors.statistics');
        });
        Route::post('vendors', [VendorController::class, 'store'])->middleware(['permission:create vendors', 'throttle:api-write'])->name('api.vendors.store');
        Route::put('vendors/{vendor}', [VendorController::class, 'update'])->middleware(['permission:edit vendors', 'throttle:api-write'])->name('api.vendors.update');
        Route::delete('vendors/{vendor}', [VendorController::class, 'destroy'])->middleware(['permission:delete vendors', 'throttle:api-write'])->name('api.vendors.destroy');

        // Items
        Route::middleware('permission:view items')->group(function () {
            Route::get('items', [ItemController::class, 'index'])->name('api.items.index');
            Route::get('items/{item}', [ItemController::class, 'show'])->name('api.items.show');
        });
        Route::post('items', [ItemController::class, 'store'])->middleware(['permission:create items', 'throttle:api-write'])->name('api.items.store');
        Route::put('items/{item}', [ItemController::class, 'update'])->middleware(['permission:edit items', 'throttle:api-write'])->name('api.items.update');
        Route::delete('items/{item}', [ItemController::class, 'destroy'])->middleware(['permission:delete items', 'throttle:api-write'])->name('api.items.destroy');

        // Invoices
        Route::middleware('permission:view invoices')->group(function () {
            Route::get('invoices/summary', [InvoiceController::class, 'summary'])->name('api.invoices.summary');
            Route::get('invoices', [InvoiceController::class, 'index'])->name('api.invoices.index');
            Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('api.invoices.show');
            Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->middleware('throttle:api-export')->name('api.invoices.pdf');
        });
        Route::post('invoices', [InvoiceController::class, 'store'])->middleware(['permission:create invoices', 'throttle:api-write'])->name('api.invoices.store');
        Route::middleware('permission:edit invoices')->group(function () {
            Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->name('api.invoices.update');
            Route::post('invoices/{invoice}/release', [InvoiceController::class, 'release'])->name('api.invoices.release');
            Route::put('invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('api.invoices.status');
        });
        Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->middleware(['permission:send invoices', 'throttle:api-write'])->name('api.invoices.send');
        Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->middleware(['permission:delete invoices', 'throttle:api-write'])->name('api.invoices.destroy');

        // Bills
        Route::middleware('permission:view bills')->group(function () {
            Route::get('bills/summary', [BillController::class, 'summary'])->name('api.bills.summary');
            Route::get('bills', [BillController::class, 'index'])->name('api.bills.index');
            Route::get('bills/{bill}', [BillController::class, 'show'])->name('api.bills.show');
        });
        Route::post('bills', [BillController::class, 'store'])->middleware(['permission:create bills', 'throttle:api-write'])->name('api.bills.store');
        Route::put('bills/{bill}', [BillController::class, 'update'])->middleware(['permission:edit bills', 'throttle:api-write'])->name('api.bills.update');
        Route::delete('bills/{bill}', [BillController::class, 'destroy'])->middleware(['permission:delete bills', 'throttle:api-write'])->name('api.bills.destroy');

        // Expenses
        Route::middleware('permission:view expenses')->group(function () {
            Route::get('expenses/summary', [ExpenseController::class, 'summary'])->name('api.expenses.summary');
            Route::get('expenses', [ExpenseController::class, 'index'])->name('api.expenses.index');
            Route::get('expenses/{expense}', [ExpenseController::class, 'show'])->name('api.expenses.show');
        });
        Route::post('expenses', [ExpenseController::class, 'store'])->middleware(['permission:create expenses', 'throttle:api-write'])->name('api.expenses.store');
        Route::middleware('permission:edit expenses')->group(function () {
            Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->name('api.expenses.update');
            Route::post('expenses/{expense}/submit', [ExpenseController::class, 'submit'])->name('api.expenses.submit');
            Route::post('expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('api.expenses.approve');
            Route::post('expenses/{expense}/reject', [ExpenseController::class, 'reject'])->name('api.expenses.reject');
        });
        Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->middleware(['permission:delete expenses', 'throttle:api-write'])->name('api.expenses.destroy');

        // Payments Received
        Route::middleware('permission:view payments-received')->group(function () {
            Route::get('payments-received/summary', [PaymentReceivedController::class, 'summary'])->name('api.payments-received.summary');
            Route::get('payments-received', [PaymentReceivedController::class, 'index'])->name('api.payments-received.index');
            Route::get('payments-received/{paymentReceived}', [PaymentReceivedController::class, 'show'])->name('api.payments-received.show');
        });
        Route::post('payments-received', [PaymentReceivedController::class, 'store'])->middleware(['permission:create payments-received', 'throttle:api-write'])->name('api.payments-received.store');
        Route::delete('payments-received/{paymentReceived}', [PaymentReceivedController::class, 'destroy'])->middleware(['permission:delete payments-received', 'throttle:api-write'])->name('api.payments-received.destroy');

        // Payments Made
        Route::middleware('permission:view payments-made')->group(function () {
            Route::get('payments-made/summary', [PaymentMadeController::class, 'summary'])->name('api.payments-made.summary');
            Route::get('payments-made', [PaymentMadeController::class, 'index'])->name('api.payments-made.index');
            Route::get('payments-made/{paymentMade}', [PaymentMadeController::class, 'show'])->name('api.payments-made.show');
        });
        Route::post('payments-made', [PaymentMadeController::class, 'store'])->middleware(['permission:create payments-made', 'throttle:api-write'])->name('api.payments-made.store');
        Route::delete('payments-made/{paymentMade}', [PaymentMadeController::class, 'destroy'])->middleware(['permission:delete payments-made', 'throttle:api-write'])->name('api.payments-made.destroy');

        // Inventory
        Route::middleware('permission:view inventory')->group(function () {
            Route::get('inventory/summary', [InventoryController::class, 'summary'])->name('api.inventory.summary');
            Route::get('inventory', [InventoryController::class, 'index'])->name('api.inventory.index');
            Route::get('inventory/{inventory}', [InventoryController::class, 'show'])->name('api.inventory.show');
            Route::get('inventory/{inventory}/history', [InventoryController::class, 'history'])->name('api.inventory.history');
        });
        Route::post('inventory/{inventory}/adjust', [InventoryController::class, 'adjust'])->middleware(['permission:adjust inventory', 'throttle:api-write'])->name('api.inventory.adjust');

        // Journals
        Route::middleware('permission:view journals')->group(function () {
            Route::get('journals/summary', [JournalController::class, 'summary'])->name('api.journals.summary');
            Route::get('journals', [JournalController::class, 'index'])->name('api.journals.index');
            Route::get('journals/{journal}', [JournalController::class, 'show'])->name('api.journals.show');
        });
        Route::post('journals', [JournalController::class, 'store'])->middleware(['permission:create journals', 'throttle:api-write'])->name('api.journals.store');
        Route::middleware('permission:edit journals')->group(function () {
            Route::put('journals/{journal}', [JournalController::class, 'update'])->name('api.journals.update');
            Route::post('journals/{journal}/post', [JournalController::class, 'post'])->name('api.journals.post');
            Route::post('journals/{journal}/reverse', [JournalController::class, 'reverse'])->name('api.journals.reverse');
        });
        Route::delete('journals/{journal}', [JournalController::class, 'destroy'])->middleware(['permission:delete journals', 'throttle:api-write'])->name('api.journals.destroy');

        // Chart of Accounts
        Route::middleware('permission:view chart-of-accounts')->group(function () {
            Route::get('accounts/types', [ChartOfAccountController::class, 'types'])->name('api.accounts.types');
            Route::get('accounts/balance-summary', [ChartOfAccountController::class, 'balanceSummary'])->name('api.accounts.balance-summary');
            Route::get('accounts', [ChartOfAccountController::class, 'index'])->name('api.accounts.index');
            Route::get('accounts/{chartOfAccount}', [ChartOfAccountController::class, 'show'])->name('api.accounts.show');
        });
        Route::post('accounts', [ChartOfAccountController::class, 'store'])->middleware(['permission:create chart-of-accounts', 'throttle:api-write'])->name('api.accounts.store');
        Route::put('accounts/{chartOfAccount}', [ChartOfAccountController::class, 'update'])->middleware(['permission:edit chart-of-accounts', 'throttle:api-write'])->name('api.accounts.update');
        Route::delete('accounts/{chartOfAccount}', [ChartOfAccountController::class, 'destroy'])->middleware(['permission:delete chart-of-accounts', 'throttle:api-write'])->name('api.accounts.destroy');

        // Banks
        Route::middleware('permission:view banks')->group(function () {
            Route::get('banks/types', [BankController::class, 'types'])->name('api.banks.types');
            Route::get('banks/summary', [BankController::class, 'summary'])->name('api.banks.summary');
            Route::get('banks', [BankController::class, 'index'])->name('api.banks.index');
            Route::get('banks/{bank}', [BankController::class, 'show'])->name('api.banks.show');
        });
        Route::post('banks', [BankController::class, 'store'])->middleware(['permission:create banks', 'throttle:api-write'])->name('api.banks.store');
        Route::put('banks/{bank}', [BankController::class, 'update'])->middleware(['permission:edit banks', 'throttle:api-write'])->name('api.banks.update');
        Route::delete('banks/{bank}', [BankController::class, 'destroy'])->middleware(['permission:delete banks', 'throttle:api-write'])->name('api.banks.destroy');

        // Sales Orders
        Route::middleware('permission:view sales-orders')->group(function () {
            Route::get('sales-orders/summary', [SalesOrderController::class, 'summary'])->name('api.sales-orders.summary');
            Route::get('sales-orders', [SalesOrderController::class, 'index'])->name('api.sales-orders.index');
            Route::get('sales-orders/{salesOrder}', [SalesOrderController::class, 'show'])->name('api.sales-orders.show');
        });
        Route::post('sales-orders', [SalesOrderController::class, 'store'])->middleware(['permission:create sales-orders', 'throttle:api-write'])->name('api.sales-orders.store');
        Route::put('sales-orders/{salesOrder}', [SalesOrderController::class, 'update'])->middleware(['permission:edit sales-orders', 'throttle:api-write'])->name('api.sales-orders.update');
        Route::delete('sales-orders/{salesOrder}', [SalesOrderController::class, 'destroy'])->middleware(['permission:delete sales-orders', 'throttle:api-write'])->name('api.sales-orders.destroy');

        // Employees
        Route::middleware('permission:view employees')->group(function () {
            Route::get('employees/summary', [EmployeeController::class, 'summary'])->name('api.employees.summary');
            Route::get('employees', [EmployeeController::class, 'index'])->name('api.employees.index');
            Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('api.employees.show');
        });
        Route::post('employees', [EmployeeController::class, 'store'])->middleware(['permission:create employees', 'throttle:api-write'])->name('api.employees.store');
        Route::put('employees/{employee}', [EmployeeController::class, 'update'])->middleware(['permission:edit employees', 'throttle:api-write'])->name('api.employees.update');
        Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])->middleware(['permission:delete employees', 'throttle:api-write'])->name('api.employees.destroy');

        // Tax Rates
        Route::middleware('permission:view chart-of-accounts')->group(function () {
            Route::get('tax-rates', [TaxRateController::class, 'index'])->name('api.tax-rates.index');
            Route::get('tax-rates/{taxRate}', [TaxRateController::class, 'show'])->name('api.tax-rates.show');
        });
        Route::post('tax-rates', [TaxRateController::class, 'store'])->middleware(['permission:edit chart-of-accounts', 'throttle:api-write'])->name('api.tax-rates.store');
        Route::put('tax-rates/{taxRate}', [TaxRateController::class, 'update'])->middleware(['permission:edit chart-of-accounts', 'throttle:api-write'])->name('api.tax-rates.update');
        Route::delete('tax-rates/{taxRate}', [TaxRateController::class, 'destroy'])->middleware(['permission:edit chart-of-accounts', 'throttle:api-write'])->name('api.tax-rates.destroy');
    });
});
