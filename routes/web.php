<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Mail\ContactFormMail;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemCategoryController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\BillOfMaterialController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\SalesReceiptController;
use App\Http\Controllers\PaymentReceivedController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\DeliveryNoteController;
use App\Http\Controllers\CreditNoteController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\RecurrentBillController;
use App\Http\Controllers\RecurrentExpenseController;
use App\Http\Controllers\PaymentMadeController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LeaveTypeController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\AllowanceController;
use App\Http\Controllers\DeductionController;
use App\Http\Controllers\SalaryStructureController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\AccountingPeriodController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\TaxRateController;
use App\Http\Controllers\TaxGroupController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\InvoiceTemplateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/about', function () {
    return view('pages.about');
})->name('about');

Route::get('/privacy-policy', function () {
    return view('pages.privacy-policy');
})->name('privacy-policy');

Route::get('/contact', function () {
    return view('pages.contact');
})->name('contact');

Route::post('/contact', function (\Illuminate\Http\Request $request) {
    // Honeypot check: if the hidden field is filled, it's a bot
    if ($request->filled('website')) {
        return back()->with('success', 'Thank you for your message! We will get back to you soon.');
    }

    // Time-based check: reject if submitted in under 3 seconds (bot behavior)
    $loadedAt = (int) $request->input('_form_loaded_at', 0);
    if ($loadedAt > 0 && (now()->timestamp - $loadedAt) < 3) {
        return back()->with('success', 'Thank you for your message! We will get back to you soon.');
    }

    $request->validate([
        'first_name' => 'required|string|max:255',
        'last_name' => 'required|string|max:255',
        'email' => 'required|email|max:255',
        'phone' => 'nullable|string|max:50',
        'subject' => 'required|string|in:general,support,sales,billing,feedback,partnership,other',
        'message' => 'required|string|max:5000',
    ]);

    // Send email notification (only pass validated fields)
    Mail::to(config('mybooks.support_email'))->send(new ContactFormMail($request->only([
        'first_name', 'last_name', 'email', 'phone', 'subject', 'message',
    ])));

    return back()->with('success', 'Thank you for your message! We will get back to you soon.');
})->middleware('throttle:5,1')->name('contact.submit');

Route::get('/support', function () {
    return view('pages.support');
})->name('support');

Route::get('/terms-of-service', function () {
    return view('pages.terms-of-service');
})->name('terms-of-service');

Route::get('/docs/api', function () {
    $filePath = base_path('docs/API.md');
    
    if (!file_exists($filePath)) {
        abort(404, 'API documentation file not found');
    }
    
    $markdown = file_get_contents($filePath);
    
    if ($markdown === false) {
        abort(500, 'Unable to read API documentation file');
    }
    
    return view('docs.api', ['content' => \Illuminate\Support\Str::markdown($markdown, [
        'html_input' => 'strip',
        'allow_unsafe_links' => false,
    ])]);
})->name('docs.api');

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active', 'verified', 'two-factor', 'subscription', 'tenant'])->group(function () {
    
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:view dashboard')
        ->name('dashboard');
    
    // Profile (accessible by all authenticated users - subscription middleware allows these)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /*
    |--------------------------------------------------------------------------
    | Items Module
    |--------------------------------------------------------------------------
    */
    // Items - Create routes MUST come before wildcard routes
    Route::middleware('permission:create items')->group(function () {
        Route::get('items/create', [ItemController::class, 'create'])->name('items.create');
        Route::post('items', [ItemController::class, 'store'])->name('items.store');
    });
    Route::middleware('permission:view items')->group(function () {
        Route::get('items', [ItemController::class, 'index'])->name('items.index');
        Route::get('items/{item}', [ItemController::class, 'show'])->name('items.show');
    });
    Route::middleware('permission:edit items')->group(function () {
        Route::get('items/{item}/edit', [ItemController::class, 'edit'])->name('items.edit');
        Route::put('items/{item}', [ItemController::class, 'update'])->name('items.update');
        Route::patch('items/{item}', [ItemController::class, 'update']);
    });
    Route::delete('items/{item}', [ItemController::class, 'destroy'])
        ->middleware('permission:delete items')
        ->name('items.destroy');

    // Item Categories - Create routes MUST come before wildcard routes
    Route::middleware('permission:create items')->group(function () {
        Route::get('item-categories/create', [ItemCategoryController::class, 'create'])->name('item-categories.create');
        Route::post('item-categories', [ItemCategoryController::class, 'store'])->name('item-categories.store');
    });
    Route::middleware('permission:view items')->group(function () {
        Route::get('item-categories', [ItemCategoryController::class, 'index'])->name('item-categories.index');
        Route::get('item-categories/{itemCategory}', [ItemCategoryController::class, 'show'])->name('item-categories.show');
    });
    Route::middleware('permission:edit items')->group(function () {
        Route::get('item-categories/{itemCategory}/edit', [ItemCategoryController::class, 'edit'])->name('item-categories.edit');
        Route::put('item-categories/{itemCategory}', [ItemCategoryController::class, 'update'])->name('item-categories.update');
        Route::patch('item-categories/{itemCategory}', [ItemCategoryController::class, 'update']);
    });
    Route::delete('item-categories/{itemCategory}', [ItemCategoryController::class, 'destroy'])
        ->middleware('permission:delete items')
        ->name('item-categories.destroy');

    // Inventory
    Route::middleware('permission:view inventory')->group(function () {
        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('inventory/{item}', [InventoryController::class, 'show'])->name('inventory.show');
        Route::get('inventory/{item}/history', [InventoryController::class, 'history'])->name('inventory.history');
        Route::get('inventory/{item}/valuation', [InventoryController::class, 'valuation'])->name('inventory.valuation');
    });
    Route::post('inventory/{item}/adjust', [InventoryController::class, 'adjust'])
        ->middleware('permission:adjust inventory')
        ->name('inventory.adjust');

    // Warehouses
    Route::middleware('permission:create items')->group(function () {
        Route::get('warehouses/create', [WarehouseController::class, 'create'])->name('warehouses.create');
        Route::post('warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
    });
    Route::middleware('permission:view inventory')->group(function () {
        Route::get('warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
        Route::get('warehouses/{warehouse}', [WarehouseController::class, 'show'])->name('warehouses.show');
    });
    Route::middleware('permission:edit items')->group(function () {
        Route::get('warehouses/{warehouse}/edit', [WarehouseController::class, 'edit'])->name('warehouses.edit');
        Route::put('warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
    });
    Route::delete('warehouses/{warehouse}', [WarehouseController::class, 'destroy'])
        ->middleware('permission:delete items')
        ->name('warehouses.destroy');

    // Stock Transfers
    Route::middleware('permission:adjust inventory')->group(function () {
        Route::get('stock-transfers', [StockTransferController::class, 'index'])->name('stock-transfers.index');
        Route::get('stock-transfers/create', [StockTransferController::class, 'create'])->name('stock-transfers.create');
        Route::post('stock-transfers', [StockTransferController::class, 'store'])->name('stock-transfers.store');
        Route::get('stock-transfers/{stockTransfer}', [StockTransferController::class, 'show'])->name('stock-transfers.show');
        Route::post('stock-transfers/{stockTransfer}/ship', [StockTransferController::class, 'ship'])->name('stock-transfers.ship');
        Route::post('stock-transfers/{stockTransfer}/receive', [StockTransferController::class, 'receive'])->name('stock-transfers.receive');
        Route::delete('stock-transfers/{stockTransfer}', [StockTransferController::class, 'destroy'])->name('stock-transfers.destroy');
    });

    // Bill of Materials
    Route::middleware('permission:create items')->group(function () {
        Route::get('bill-of-materials/create', [BillOfMaterialController::class, 'create'])->name('bill-of-materials.create');
        Route::post('bill-of-materials', [BillOfMaterialController::class, 'store'])->name('bill-of-materials.store');
    });
    Route::middleware('permission:view items')->group(function () {
        Route::get('bill-of-materials', [BillOfMaterialController::class, 'index'])->name('bill-of-materials.index');
        Route::get('bill-of-materials/{billOfMaterial}', [BillOfMaterialController::class, 'show'])->name('bill-of-materials.show');
    });
    Route::middleware('permission:edit items')->group(function () {
        Route::get('bill-of-materials/{billOfMaterial}/edit', [BillOfMaterialController::class, 'edit'])->name('bill-of-materials.edit');
        Route::put('bill-of-materials/{billOfMaterial}', [BillOfMaterialController::class, 'update'])->name('bill-of-materials.update');
    });
    Route::delete('bill-of-materials/{billOfMaterial}', [BillOfMaterialController::class, 'destroy'])
        ->middleware('permission:delete items')
        ->name('bill-of-materials.destroy');

    // Assembly Orders
    Route::middleware('permission:adjust inventory')->group(function () {
        Route::get('assembly-orders', [BillOfMaterialController::class, 'assemblyOrders'])->name('assembly-orders.index');
        Route::get('bill-of-materials/{billOfMaterial}/assemble', [BillOfMaterialController::class, 'createAssemblyOrder'])->name('assembly-orders.create');
        Route::post('bill-of-materials/{billOfMaterial}/assemble', [BillOfMaterialController::class, 'storeAssemblyOrder'])->name('assembly-orders.store');
        Route::get('assembly-orders/{assemblyOrder}', [BillOfMaterialController::class, 'showAssemblyOrder'])->name('assembly-orders.show');
        Route::post('assembly-orders/{assemblyOrder}/complete', [BillOfMaterialController::class, 'completeAssemblyOrder'])->name('assembly-orders.complete');
    });

    /*
    |--------------------------------------------------------------------------
    | Sales Module
    |--------------------------------------------------------------------------
    */
    // Customers - Create routes MUST come before wildcard routes
    Route::middleware('permission:create customers')->group(function () {
        Route::get('customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    });
    Route::middleware('permission:view customers')->group(function () {
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    });
    Route::middleware('permission:edit customers')->group(function () {
        Route::get('customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::patch('customers/{customer}', [CustomerController::class, 'update']);
    });
    Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])
        ->middleware('permission:delete customers')
        ->name('customers.destroy');

    // Invoices - Create routes MUST come before wildcard routes
    Route::middleware('permission:create invoices')->group(function () {
        Route::get('invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    });
    Route::middleware('permission:view invoices')->group(function () {
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::get('invoices/{invoice}/waybill', [InvoiceController::class, 'waybill'])->name('invoices.waybill');
    });
    Route::middleware('permission:edit invoices')->group(function () {
        Route::get('invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
        Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
        Route::patch('invoices/{invoice}', [InvoiceController::class, 'update']);
        Route::post('invoices/{invoice}/mark-paid', [InvoiceController::class, 'markAsPaid'])->name('invoices.mark-paid');
        Route::post('invoices/{invoice}/release', [InvoiceController::class, 'release'])->name('invoices.release');
    });
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])
        ->middleware('permission:send invoices')
        ->name('invoices.send');
    Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])
        ->middleware('permission:delete invoices')
        ->name('invoices.destroy');
    
    // Invoice Refunds
    Route::middleware('permission:edit invoices')->group(function () {
        Route::get('invoices/{invoice}/refund', [\App\Http\Controllers\InvoiceRefundController::class, 'create'])->name('invoices.refunds.create');
        Route::post('invoices/{invoice}/refund', [\App\Http\Controllers\InvoiceRefundController::class, 'store'])->name('invoices.refunds.store');
        Route::get('refunds/{refund}', [\App\Http\Controllers\InvoiceRefundController::class, 'show'])->name('invoices.refunds.show');
        Route::get('refunds/{refund}/print', [\App\Http\Controllers\InvoiceRefundController::class, 'print'])->name('invoices.refunds.print');
        Route::patch('refunds/{refund}/cancel', [\App\Http\Controllers\InvoiceRefundController::class, 'cancel'])->name('invoices.refunds.cancel');
    });
    
    // Sales Orders - Create routes MUST come before wildcard routes
    Route::middleware('permission:create sales-orders')->group(function () {
        Route::get('sales-orders/create', [SalesOrderController::class, 'create'])->name('sales-orders.create');
        Route::post('sales-orders', [SalesOrderController::class, 'store'])->name('sales-orders.store');
    });
    Route::middleware('permission:view sales-orders')->group(function () {
        Route::get('sales-orders', [SalesOrderController::class, 'index'])->name('sales-orders.index');
        Route::get('sales-orders/{salesOrder}', [SalesOrderController::class, 'show'])->name('sales-orders.show');
    });
    Route::middleware('permission:edit sales-orders')->group(function () {
        Route::get('sales-orders/{salesOrder}/edit', [SalesOrderController::class, 'edit'])->name('sales-orders.edit');
        Route::put('sales-orders/{salesOrder}', [SalesOrderController::class, 'update'])->name('sales-orders.update');
        Route::patch('sales-orders/{salesOrder}', [SalesOrderController::class, 'update']);
        Route::post('sales-orders/{salesOrder}/confirm', [SalesOrderController::class, 'confirm'])->name('sales-orders.confirm');
        Route::post('sales-orders/{salesOrder}/convert', [SalesOrderController::class, 'convertToInvoice'])->name('sales-orders.convert');
    });
    Route::delete('sales-orders/{salesOrder}', [SalesOrderController::class, 'destroy'])
        ->middleware('permission:delete sales-orders')
        ->name('sales-orders.destroy');
    
    // Sales Receipts - Create routes MUST come before wildcard routes
    Route::middleware('permission:create sales-receipts')->group(function () {
        Route::get('sales-receipts/create', [SalesReceiptController::class, 'create'])->name('sales-receipts.create');
        Route::post('sales-receipts', [SalesReceiptController::class, 'store'])->name('sales-receipts.store');
    });
    Route::middleware('permission:view sales-receipts')->group(function () {
        Route::get('sales-receipts', [SalesReceiptController::class, 'index'])->name('sales-receipts.index');
        Route::get('sales-receipts/{salesReceipt}', [SalesReceiptController::class, 'show'])->name('sales-receipts.show');
        Route::get('sales-receipts/{salesReceipt}/pdf', [SalesReceiptController::class, 'pdf'])->name('sales-receipts.pdf');
    });
    Route::middleware('permission:edit sales-receipts')->group(function () {
        Route::get('sales-receipts/{salesReceipt}/edit', [SalesReceiptController::class, 'edit'])->name('sales-receipts.edit');
        Route::put('sales-receipts/{salesReceipt}', [SalesReceiptController::class, 'update'])->name('sales-receipts.update');
        Route::patch('sales-receipts/{salesReceipt}', [SalesReceiptController::class, 'update']);
    });
    Route::delete('sales-receipts/{salesReceipt}', [SalesReceiptController::class, 'destroy'])
        ->middleware('permission:delete sales-receipts')
        ->name('sales-receipts.destroy');
    
    // Payments Received - Create routes MUST come before wildcard routes
    Route::middleware('permission:create payments-received')->group(function () {
        Route::get('payments-received/create', [PaymentReceivedController::class, 'create'])->name('payments-received.create');
        Route::post('payments-received', [PaymentReceivedController::class, 'store'])->name('payments-received.store');
    });
    Route::middleware('permission:view payments-received')->group(function () {
        Route::get('payments-received', [PaymentReceivedController::class, 'index'])->name('payments-received.index');
        Route::get('payments-received/{paymentReceived}', [PaymentReceivedController::class, 'show'])->name('payments-received.show');
    });
    Route::middleware('permission:edit payments-received')->group(function () {
        Route::get('payments-received/{paymentReceived}/edit', [PaymentReceivedController::class, 'edit'])->name('payments-received.edit');
        Route::put('payments-received/{paymentReceived}', [PaymentReceivedController::class, 'update'])->name('payments-received.update');
        Route::patch('payments-received/{paymentReceived}', [PaymentReceivedController::class, 'update']);
        // Deposit application routes
        Route::get('payments-received/{paymentReceived}/apply-deposit', [PaymentReceivedController::class, 'showApplyDeposit'])->name('payments-received.apply-deposit');
        Route::post('payments-received/{paymentReceived}/apply-deposit', [PaymentReceivedController::class, 'applyDeposit'])->name('payments-received.apply-deposit.store');
    });
    Route::delete('payments-received/{paymentReceived}', [PaymentReceivedController::class, 'destroy'])
        ->middleware('permission:delete payments-received')
        ->name('payments-received.destroy');

    // Quotations / Estimates
    Route::middleware('permission:create invoices')->group(function () {
        Route::get('quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
        Route::post('quotations', [QuotationController::class, 'store'])->name('quotations.store');
    });
    Route::middleware('permission:view invoices')->group(function () {
        Route::get('quotations', [QuotationController::class, 'index'])->name('quotations.index');
        Route::get('quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
        Route::get('quotations/{quotation}/print', [QuotationController::class, 'print'])->name('quotations.print');
    });
    Route::middleware('permission:edit invoices')->group(function () {
        Route::get('quotations/{quotation}/edit', [QuotationController::class, 'edit'])->name('quotations.edit');
        Route::put('quotations/{quotation}', [QuotationController::class, 'update'])->name('quotations.update');
        Route::post('quotations/{quotation}/send', [QuotationController::class, 'send'])->name('quotations.send');
        Route::post('quotations/{quotation}/accept', [QuotationController::class, 'accept'])->name('quotations.accept');
        Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject'])->name('quotations.reject');
        Route::post('quotations/{quotation}/convert', [QuotationController::class, 'convertToSalesOrder'])->name('quotations.convert');
    });
    Route::delete('quotations/{quotation}', [QuotationController::class, 'destroy'])
        ->middleware('permission:delete invoices')
        ->name('quotations.destroy');

    // Delivery Notes
    Route::middleware('permission:create invoices')->group(function () {
        Route::get('delivery-notes/create', [DeliveryNoteController::class, 'create'])->name('delivery-notes.create');
        Route::post('delivery-notes', [DeliveryNoteController::class, 'store'])->name('delivery-notes.store');
    });
    Route::middleware('permission:view invoices')->group(function () {
        Route::get('delivery-notes', [DeliveryNoteController::class, 'index'])->name('delivery-notes.index');
        Route::get('delivery-notes/{deliveryNote}', [DeliveryNoteController::class, 'show'])->name('delivery-notes.show');
        Route::get('delivery-notes/{deliveryNote}/print', [DeliveryNoteController::class, 'print'])->name('delivery-notes.print');
    });
    Route::middleware('permission:edit invoices')->group(function () {
        Route::post('delivery-notes/{deliveryNote}/dispatch', [DeliveryNoteController::class, 'dispatch'])->name('delivery-notes.dispatch');
        Route::post('delivery-notes/{deliveryNote}/confirm', [DeliveryNoteController::class, 'confirmDelivery'])->name('delivery-notes.confirm');
    });
    Route::delete('delivery-notes/{deliveryNote}', [DeliveryNoteController::class, 'destroy'])
        ->middleware('permission:delete invoices')
        ->name('delivery-notes.destroy');
    // Delivery note from Sales Order
    Route::post('sales-orders/{salesOrder}/delivery-note', [SalesOrderController::class, 'createDeliveryNote'])
        ->middleware('permission:edit sales-orders')
        ->name('sales-orders.delivery-note');

    // Credit Notes
    Route::middleware('permission:create invoices')->group(function () {
        Route::get('credit-notes/create', [CreditNoteController::class, 'create'])->name('credit-notes.create');
        Route::post('credit-notes', [CreditNoteController::class, 'store'])->name('credit-notes.store');
    });
    Route::middleware('permission:view invoices')->group(function () {
        Route::get('credit-notes', [CreditNoteController::class, 'index'])->name('credit-notes.index');
        Route::get('credit-notes/{creditNote}', [CreditNoteController::class, 'show'])->name('credit-notes.show');
    });
    Route::middleware('permission:edit invoices')->group(function () {
        Route::post('credit-notes/{creditNote}/open', [CreditNoteController::class, 'open'])->name('credit-notes.open');
        Route::post('credit-notes/{creditNote}/void', [CreditNoteController::class, 'void'])->name('credit-notes.void');
        Route::get('credit-notes/{creditNote}/apply', [CreditNoteController::class, 'showApply'])->name('credit-notes.apply');
        Route::post('credit-notes/{creditNote}/apply', [CreditNoteController::class, 'apply'])->name('credit-notes.apply.store');
    });
    Route::delete('credit-notes/{creditNote}', [CreditNoteController::class, 'destroy'])
        ->middleware('permission:delete invoices')
        ->name('credit-notes.destroy');

    /*
    |--------------------------------------------------------------------------
    | Purchases Module
    |--------------------------------------------------------------------------
    */
    // Vendors - Create routes MUST come before wildcard routes
    Route::middleware('permission:create vendors')->group(function () {
        Route::get('vendors/create', [VendorController::class, 'create'])->name('vendors.create');
        Route::post('vendors', [VendorController::class, 'store'])->name('vendors.store');
    });
    Route::middleware('permission:view vendors')->group(function () {
        Route::get('vendors', [VendorController::class, 'index'])->name('vendors.index');
        Route::get('vendors/{vendor}', [VendorController::class, 'show'])->name('vendors.show');
    });
    Route::middleware('permission:edit vendors')->group(function () {
        Route::get('vendors/{vendor}/edit', [VendorController::class, 'edit'])->name('vendors.edit');
        Route::put('vendors/{vendor}', [VendorController::class, 'update'])->name('vendors.update');
        Route::patch('vendors/{vendor}', [VendorController::class, 'update']);
    });
    Route::delete('vendors/{vendor}', [VendorController::class, 'destroy'])
        ->middleware('permission:delete vendors')
        ->name('vendors.destroy');

    // Expenses - Create routes MUST come before wildcard routes
    Route::middleware('permission:create expenses')->group(function () {
        Route::get('expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    });
    Route::middleware('permission:view expenses')->group(function () {
        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::get('expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
    });
    Route::middleware('permission:edit expenses')->group(function () {
        Route::get('expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
        Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::patch('expenses/{expense}', [ExpenseController::class, 'update']);
        Route::post('expenses/{expense}/submit', [ExpenseController::class, 'submit'])->name('expenses.submit');
    });
    // Expense approval routes (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::post('expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
        Route::post('expenses/{expense}/reject', [ExpenseController::class, 'reject'])->name('expenses.reject');
        Route::post('expenses/{expense}/mark-paid', [ExpenseController::class, 'markAsPaid'])->name('expenses.mark-paid');
    });
    Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])
        ->middleware('permission:delete expenses')
        ->name('expenses.destroy');

    // Bills - Create routes MUST come before wildcard routes
    Route::middleware('permission:create bills')->group(function () {
        Route::get('bills/create', [BillController::class, 'create'])->name('bills.create');
        Route::post('bills', [BillController::class, 'store'])->name('bills.store');
    });
    Route::middleware('permission:view bills')->group(function () {
        Route::get('bills', [BillController::class, 'index'])->name('bills.index');
        Route::get('bills/{bill}', [BillController::class, 'show'])->name('bills.show');
    });
    Route::middleware('permission:edit bills')->group(function () {
        Route::get('bills/{bill}/edit', [BillController::class, 'edit'])->name('bills.edit');
        Route::put('bills/{bill}', [BillController::class, 'update'])->name('bills.update');
        Route::patch('bills/{bill}', [BillController::class, 'update']);
    });
    Route::delete('bills/{bill}', [BillController::class, 'destroy'])
        ->middleware('permission:delete bills')
        ->name('bills.destroy');

    // Purchase Orders - Create routes MUST come before wildcard routes
    Route::middleware('permission:create purchase-orders')->group(function () {
        Route::get('purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
        Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
    });
    Route::middleware('permission:view purchase-orders')->group(function () {
        Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::get('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
    });
    Route::middleware('permission:edit purchase-orders')->group(function () {
        Route::get('purchase-orders/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->name('purchase-orders.edit');
        Route::put('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update');
        Route::patch('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update']);
        Route::post('purchase-orders/{purchaseOrder}/confirm', [PurchaseOrderController::class, 'confirm'])->name('purchase-orders.confirm');
        Route::post('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
        Route::post('purchase-orders/{purchaseOrder}/convert-to-bill', [PurchaseOrderController::class, 'convertToBill'])->name('purchase-orders.convert-to-bill');
    });
    Route::delete('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])
        ->middleware('permission:delete purchase-orders')
        ->name('purchase-orders.destroy');
    
    // Recurrent Bills - Create routes MUST come before wildcard routes
    Route::middleware('permission:create recurrent-bills')->group(function () {
        Route::get('recurrent-bills/create', [RecurrentBillController::class, 'create'])->name('recurrent-bills.create');
        Route::post('recurrent-bills', [RecurrentBillController::class, 'store'])->name('recurrent-bills.store');
    });
    Route::middleware('permission:view recurrent-bills')->group(function () {
        Route::get('recurrent-bills', [RecurrentBillController::class, 'index'])->name('recurrent-bills.index');
        Route::get('recurrent-bills/{recurrentBill}', [RecurrentBillController::class, 'show'])->name('recurrent-bills.show');
    });
    Route::middleware('permission:edit recurrent-bills')->group(function () {
        Route::get('recurrent-bills/{recurrentBill}/edit', [RecurrentBillController::class, 'edit'])->name('recurrent-bills.edit');
        Route::put('recurrent-bills/{recurrentBill}', [RecurrentBillController::class, 'update'])->name('recurrent-bills.update');
        Route::patch('recurrent-bills/{recurrentBill}', [RecurrentBillController::class, 'update']);
        Route::post('recurrent-bills/{recurrentBill}/toggle', [RecurrentBillController::class, 'toggleStatus'])->name('recurrent-bills.toggle');
    });
    Route::delete('recurrent-bills/{recurrentBill}', [RecurrentBillController::class, 'destroy'])
        ->middleware('permission:delete recurrent-bills')
        ->name('recurrent-bills.destroy');
    
    // Recurrent Expenses - Create routes MUST come before wildcard routes
    Route::middleware('permission:create recurrent-expenses')->group(function () {
        Route::get('recurrent-expenses/create', [RecurrentExpenseController::class, 'create'])->name('recurrent-expenses.create');
        Route::post('recurrent-expenses', [RecurrentExpenseController::class, 'store'])->name('recurrent-expenses.store');
    });
    Route::middleware('permission:view recurrent-expenses')->group(function () {
        Route::get('recurrent-expenses', [RecurrentExpenseController::class, 'index'])->name('recurrent-expenses.index');
        Route::get('recurrent-expenses/{recurrentExpense}', [RecurrentExpenseController::class, 'show'])->name('recurrent-expenses.show');
    });
    Route::middleware('permission:edit recurrent-expenses')->group(function () {
        Route::get('recurrent-expenses/{recurrentExpense}/edit', [RecurrentExpenseController::class, 'edit'])->name('recurrent-expenses.edit');
        Route::put('recurrent-expenses/{recurrentExpense}', [RecurrentExpenseController::class, 'update'])->name('recurrent-expenses.update');
        Route::patch('recurrent-expenses/{recurrentExpense}', [RecurrentExpenseController::class, 'update']);
        Route::post('recurrent-expenses/{recurrentExpense}/toggle', [RecurrentExpenseController::class, 'toggleStatus'])->name('recurrent-expenses.toggle');
    });
    Route::delete('recurrent-expenses/{recurrentExpense}', [RecurrentExpenseController::class, 'destroy'])
        ->middleware('permission:delete recurrent-expenses')
        ->name('recurrent-expenses.destroy');
    
    // Payments Made - Create routes MUST come before wildcard routes
    Route::middleware('permission:create payments-made')->group(function () {
        Route::get('payments-made/create', [PaymentMadeController::class, 'create'])->name('payments-made.create');
        Route::post('payments-made', [PaymentMadeController::class, 'store'])->name('payments-made.store');
    });
    Route::middleware('permission:view payments-made')->group(function () {
        Route::get('payments-made', [PaymentMadeController::class, 'index'])->name('payments-made.index');
        Route::get('payments-made/{paymentMade}', [PaymentMadeController::class, 'show'])->name('payments-made.show');
    });
    Route::middleware('permission:edit payments-made')->group(function () {
        Route::get('payments-made/{paymentMade}/edit', [PaymentMadeController::class, 'edit'])->name('payments-made.edit');
        Route::put('payments-made/{paymentMade}', [PaymentMadeController::class, 'update'])->name('payments-made.update');
        Route::patch('payments-made/{paymentMade}', [PaymentMadeController::class, 'update']);
    });
    Route::delete('payments-made/{paymentMade}', [PaymentMadeController::class, 'destroy'])
        ->middleware('permission:delete payments-made')
        ->name('payments-made.destroy');

    /*
    |--------------------------------------------------------------------------
    | Human Resource Module
    |--------------------------------------------------------------------------
    */
    // Departments - Create routes MUST come before wildcard routes
    Route::middleware('permission:create departments')->group(function () {
        Route::get('departments/create', [DepartmentController::class, 'create'])->name('departments.create');
        Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
    });
    Route::middleware('permission:view departments')->group(function () {
        Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::get('departments/{department}', [DepartmentController::class, 'show'])->name('departments.show');
    });
    Route::middleware('permission:edit departments')->group(function () {
        Route::get('departments/{department}/edit', [DepartmentController::class, 'edit'])->name('departments.edit');
        Route::put('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
        Route::patch('departments/{department}', [DepartmentController::class, 'update']);
    });
    Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])
        ->middleware('permission:delete departments')
        ->name('departments.destroy');

    // Designations - Create routes MUST come before wildcard routes
    Route::middleware('permission:create designations')->group(function () {
        Route::get('designations/create', [DesignationController::class, 'create'])->name('designations.create');
        Route::post('designations', [DesignationController::class, 'store'])->name('designations.store');
    });
    Route::middleware('permission:view designations')->group(function () {
        Route::get('designations', [DesignationController::class, 'index'])->name('designations.index');
        Route::get('designations/{designation}', [DesignationController::class, 'show'])->name('designations.show');
    });
    Route::middleware('permission:edit designations')->group(function () {
        Route::get('designations/{designation}/edit', [DesignationController::class, 'edit'])->name('designations.edit');
        Route::put('designations/{designation}', [DesignationController::class, 'update'])->name('designations.update');
        Route::patch('designations/{designation}', [DesignationController::class, 'update']);
    });
    Route::delete('designations/{designation}', [DesignationController::class, 'destroy'])
        ->middleware('permission:delete designations')
        ->name('designations.destroy');

    // Employees - Create routes MUST come before wildcard routes
    Route::middleware('permission:create employees')->group(function () {
        Route::get('employees/create', [EmployeeController::class, 'create'])->name('employees.create');
        Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
    });
    Route::middleware('permission:view employees')->group(function () {
        Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    });
    Route::middleware('permission:edit employees')->group(function () {
        Route::get('employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::patch('employees/{employee}', [EmployeeController::class, 'update']);
    });
    Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])
        ->middleware('permission:delete employees')
        ->name('employees.destroy');

    // Leave Types - Create routes MUST come before wildcard routes
    Route::middleware('permission:create leave-types')->group(function () {
        Route::get('leave-types/create', [LeaveTypeController::class, 'create'])->name('leave-types.create');
        Route::post('leave-types', [LeaveTypeController::class, 'store'])->name('leave-types.store');
    });
    Route::middleware('permission:view leave-types')->group(function () {
        Route::get('leave-types', [LeaveTypeController::class, 'index'])->name('leave-types.index');
        Route::get('leave-types/{leaveType}', [LeaveTypeController::class, 'show'])->name('leave-types.show');
    });
    Route::middleware('permission:edit leave-types')->group(function () {
        Route::get('leave-types/{leaveType}/edit', [LeaveTypeController::class, 'edit'])->name('leave-types.edit');
        Route::put('leave-types/{leaveType}', [LeaveTypeController::class, 'update'])->name('leave-types.update');
        Route::patch('leave-types/{leaveType}', [LeaveTypeController::class, 'update']);
    });
    Route::delete('leave-types/{leaveType}', [LeaveTypeController::class, 'destroy'])
        ->middleware('permission:delete leave-types')
        ->name('leave-types.destroy');
    
    // Leaves - Create routes MUST come before wildcard routes
    Route::middleware('permission:create leaves')->group(function () {
        Route::get('leaves/create', [LeaveController::class, 'create'])->name('leaves.create');
        Route::post('leaves', [LeaveController::class, 'store'])->name('leaves.store');
    });
    Route::middleware('permission:view leaves')->group(function () {
        Route::get('leaves', [LeaveController::class, 'index'])->name('leaves.index');
        Route::get('leaves/{leave}', [LeaveController::class, 'show'])->name('leaves.show');
    });
    Route::middleware('permission:edit leaves')->group(function () {
        Route::get('leaves/{leave}/edit', [LeaveController::class, 'edit'])->name('leaves.edit');
        Route::put('leaves/{leave}', [LeaveController::class, 'update'])->name('leaves.update');
        Route::patch('leaves/{leave}', [LeaveController::class, 'update']);
    });
    Route::delete('leaves/{leave}', [LeaveController::class, 'destroy'])
        ->middleware('permission:delete leaves')
        ->name('leaves.destroy');
    Route::middleware('permission:approve leaves')->group(function () {
        Route::post('leaves/{leave}/approve', [LeaveController::class, 'approve'])->name('leaves.approve');
        Route::post('leaves/{leave}/reject', [LeaveController::class, 'reject'])->name('leaves.reject');
    });
    
    // Allowances
    Route::middleware('permission:create payroll')->group(function () {
        Route::resource('allowances', AllowanceController::class);
    });

    // Deductions
    Route::middleware('permission:create payroll')->group(function () {
        Route::resource('deductions', DeductionController::class);
    });

    // Salary Structures
    Route::middleware('permission:create payroll')->group(function () {
        Route::get('salary-structures', [SalaryStructureController::class, 'index'])->name('salary-structures.index');
        Route::get('salary-structures/create', [SalaryStructureController::class, 'create'])->name('salary-structures.create');
        Route::post('salary-structures', [SalaryStructureController::class, 'store'])->name('salary-structures.store');
        Route::get('salary-structures/{salaryStructure}', [SalaryStructureController::class, 'show'])->name('salary-structures.show');
        Route::get('salary-structures/{salaryStructure}/edit', [SalaryStructureController::class, 'edit'])->name('salary-structures.edit');
        Route::put('salary-structures/{salaryStructure}', [SalaryStructureController::class, 'update'])->name('salary-structures.update');
        Route::delete('salary-structures/{salaryStructure}', [SalaryStructureController::class, 'destroy'])->name('salary-structures.destroy');
    });

    // Payroll - Create routes MUST come before wildcard routes
    Route::middleware('permission:create payroll')->group(function () {
        Route::get('payroll/create', [PayrollController::class, 'create'])->name('payroll.create');
        Route::post('payroll', [PayrollController::class, 'store'])->name('payroll.store');
        Route::get('payroll-bulk-create', [PayrollController::class, 'bulkCreate'])->name('payroll.bulk-create');
        Route::post('payroll-bulk-store', [PayrollController::class, 'bulkStore'])->name('payroll.bulk-store');
        Route::get('payroll-generate', [PayrollController::class, 'generateForm'])->name('payroll.generate-form');
        Route::post('payroll-generate', [PayrollController::class, 'generate'])->name('payroll.generate');
    });
    Route::middleware('permission:view payroll')->group(function () {
        Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::get('payroll-batches/{payrollBatch}', [PayrollController::class, 'showBatch'])->name('payroll-batches.show');
        Route::get('payroll/{payroll}', [PayrollController::class, 'show'])->name('payroll.show');
        Route::get('payroll/{payroll}/payslip', [PayrollController::class, 'payslip'])->name('payroll.payslip');
        Route::get('payroll-batches/{payrollBatch}/payslips', [PayrollController::class, 'batchPayslips'])->name('payroll-batches.payslips');
    });
    Route::middleware('permission:edit payroll')->group(function () {
        Route::get('payroll/{payroll}/edit', [PayrollController::class, 'edit'])->name('payroll.edit');
        Route::put('payroll/{payroll}', [PayrollController::class, 'update'])->name('payroll.update');
        Route::patch('payroll/{payroll}', [PayrollController::class, 'update']);
        Route::post('payroll/{payroll}/mark-paid', [PayrollController::class, 'markAsPaid'])->name('payroll.mark-paid');
        Route::post('payroll-batches/{payrollBatch}/mark-paid', [PayrollController::class, 'markBatchAsPaid'])->name('payroll-batches.mark-paid');
    });
    Route::delete('payroll/{payroll}', [PayrollController::class, 'destroy'])
        ->middleware('permission:delete payroll')
        ->name('payroll.destroy');
    Route::delete('payroll-batches/{payrollBatch}', [PayrollController::class, 'destroyBatch'])
        ->middleware('permission:delete payroll')
        ->name('payroll-batches.destroy');
    Route::post('payroll/{payroll}/approve', [PayrollController::class, 'approve'])
        ->middleware('permission:approve payroll')
        ->name('payroll.approve');
    Route::post('payroll-batches/{payrollBatch}/approve', [PayrollController::class, 'approveBatch'])
        ->middleware('permission:approve payroll')
        ->name('payroll-batches.approve');

    // Bank file export for batch
    Route::middleware('permission:view payroll')->group(function () {
        Route::get('payroll-batches/{payrollBatch}/bank-file', [PayrollController::class, 'exportBankFile'])->name('payroll-batches.bank-file');
    });

    // Tax templates management
    Route::middleware('permission:create payroll')->group(function () {
        Route::get('payroll-tax-templates', [PayrollController::class, 'taxTemplates'])->name('payroll.tax-templates');
        Route::post('payroll-tax-templates/apply', [PayrollController::class, 'applyTaxTemplate'])->name('payroll.apply-tax-template');
    });

    // Retroactive pay adjustments
    Route::middleware('permission:edit payroll')->group(function () {
        Route::post('payroll-retroactive-adjustment', [PayrollController::class, 'retroactiveAdjustment'])->name('payroll.retroactive-adjustment');
    });

    /*
    |--------------------------------------------------------------------------
    | Accountant Module
    |--------------------------------------------------------------------------
    */
    // Chart of Accounts - Create routes MUST come before wildcard routes
    Route::middleware('permission:create chart-of-accounts')->group(function () {
        Route::get('chart-of-accounts/create', [ChartOfAccountController::class, 'create'])->name('chart-of-accounts.create');
        Route::post('chart-of-accounts', [ChartOfAccountController::class, 'store'])->name('chart-of-accounts.store');
    });
    Route::middleware('permission:view chart-of-accounts')->group(function () {
        Route::get('chart-of-accounts', [ChartOfAccountController::class, 'index'])->name('chart-of-accounts.index');
        Route::get('chart-of-accounts/{chartOfAccount}', [ChartOfAccountController::class, 'show'])->name('chart-of-accounts.show');
    });
    Route::middleware('permission:edit chart-of-accounts')->group(function () {
        Route::get('chart-of-accounts/{chartOfAccount}/edit', [ChartOfAccountController::class, 'edit'])->name('chart-of-accounts.edit');
        Route::put('chart-of-accounts/{chartOfAccount}', [ChartOfAccountController::class, 'update'])->name('chart-of-accounts.update');
        Route::patch('chart-of-accounts/{chartOfAccount}', [ChartOfAccountController::class, 'update']);
    });
    Route::delete('chart-of-accounts/{chartOfAccount}', [ChartOfAccountController::class, 'destroy'])
        ->middleware('permission:delete chart-of-accounts')
        ->name('chart-of-accounts.destroy');

    // Journals - Create routes MUST come before wildcard routes
    Route::middleware('permission:create journals')->group(function () {
        Route::get('journals/create', [JournalController::class, 'create'])->name('journals.create');
        Route::post('journals', [JournalController::class, 'store'])->name('journals.store');
    });
    Route::middleware('permission:view journals')->group(function () {
        Route::get('journals', [JournalController::class, 'index'])->name('journals.index');
        Route::get('journals/{journal}', [JournalController::class, 'show'])->name('journals.show');
    });
    Route::middleware('permission:edit journals')->group(function () {
        Route::get('journals/{journal}/edit', [JournalController::class, 'edit'])->name('journals.edit');
        Route::put('journals/{journal}', [JournalController::class, 'update'])->name('journals.update');
        Route::patch('journals/{journal}', [JournalController::class, 'update']);
        Route::get('bulk-update', [JournalController::class, 'bulkUpdate'])->name('journals.bulk-update');
    });
    Route::delete('journals/{journal}', [JournalController::class, 'destroy'])
        ->middleware('permission:delete journals')
        ->name('journals.destroy');
    Route::post('journals/{journal}/post', [JournalController::class, 'post'])
        ->middleware('permission:post journals')
        ->name('journals.post');

    /*
    |--------------------------------------------------------------------------
    | Banks Module
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:create banks')->group(function () {
        Route::get('banks/create', [BankController::class, 'create'])->name('banks.create');
        Route::post('banks', [BankController::class, 'store'])->name('banks.store');
    });
    Route::middleware('permission:view banks')->group(function () {
        Route::get('banks', [BankController::class, 'index'])->name('banks.index');
        Route::get('banks/{bank}', [BankController::class, 'show'])->name('banks.show');
        Route::get('banks/{bank}/transactions', [BankController::class, 'transactions'])->name('banks.transactions');
    });
    Route::middleware('permission:edit banks')->group(function () {
        Route::get('banks/{bank}/edit', [BankController::class, 'edit'])->name('banks.edit');
        Route::put('banks/{bank}', [BankController::class, 'update'])->name('banks.update');
        Route::patch('banks/{bank}', [BankController::class, 'update']);
    });
    Route::delete('banks/{bank}', [BankController::class, 'destroy'])
        ->middleware('permission:delete banks')
        ->name('banks.destroy');

    Route::middleware('permission:reconcile banks')->group(function () {
        Route::get('banks/{bank}/reconcile', [BankController::class, 'reconcile'])->name('banks.reconcile');
        Route::post('banks/{bank}/reconcile', [BankController::class, 'processReconciliation'])->name('banks.reconcile.process');
        Route::post('banks/{bank}/unreconcile', [BankController::class, 'unreconcile'])->name('banks.unreconcile');
    });

    /*
    |--------------------------------------------------------------------------
    | Accounting Periods Module
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:view chart-of-accounts')->group(function () {
        Route::get('accounting-periods', [AccountingPeriodController::class, 'index'])->name('accounting-periods.index');
        Route::get('accounting-periods/create', [AccountingPeriodController::class, 'create'])->name('accounting-periods.create');
        Route::get('accounting-periods/{accountingPeriod}', [AccountingPeriodController::class, 'show'])->name('accounting-periods.show');
    });
    Route::middleware('permission:edit chart-of-accounts')->group(function () {
        Route::post('accounting-periods', [AccountingPeriodController::class, 'store'])->name('accounting-periods.store');
        Route::post('accounting-periods/generate', [AccountingPeriodController::class, 'generatePeriods'])->name('accounting-periods.generate');
        Route::get('accounting-periods/{accountingPeriod}/edit', [AccountingPeriodController::class, 'edit'])->name('accounting-periods.edit');
        Route::put('accounting-periods/{accountingPeriod}', [AccountingPeriodController::class, 'update'])->name('accounting-periods.update');
        Route::post('accounting-periods/{accountingPeriod}/close', [AccountingPeriodController::class, 'close'])->name('accounting-periods.close');
        Route::post('accounting-periods/{accountingPeriod}/reopen', [AccountingPeriodController::class, 'reopen'])->name('accounting-periods.reopen');
        Route::post('accounting-periods/{accountingPeriod}/lock', [AccountingPeriodController::class, 'lock'])->name('accounting-periods.lock');
        Route::delete('accounting-periods/{accountingPeriod}', [AccountingPeriodController::class, 'destroy'])->name('accounting-periods.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Reports Module
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:view reports')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/profit-loss', [ReportController::class, 'profitLoss'])->name('profit-loss');
        Route::get('/balance-sheet', [ReportController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('/cash-flow', [ReportController::class, 'cashFlow'])->name('cash-flow');
        Route::get('/trial-balance', [ReportController::class, 'trialBalance'])->name('trial-balance');
        Route::get('/general-ledger', [ReportController::class, 'generalLedger'])->name('general-ledger');
        Route::get('/accounts-receivable', [ReportController::class, 'accountsReceivable'])->name('accounts-receivable');
        Route::get('/accounts-payable', [ReportController::class, 'accountsPayable'])->name('accounts-payable');
        Route::get('/sales-by-customer', [ReportController::class, 'salesByCustomer'])->name('sales-by-customer');
        Route::get('/sales-by-item', [ReportController::class, 'salesByItem'])->name('sales-by-item');
        Route::get('/purchase-by-vendor', [ReportController::class, 'purchaseByVendor'])->name('purchase-by-vendor');
        Route::get('/customer-statement', [ReportController::class, 'customerStatement'])->name('customer-statement');
        Route::get('/inventory-summary', [ReportController::class, 'inventorySummary'])->name('inventory-summary');
        Route::get('/payroll-summary', [ReportController::class, 'payrollSummary'])->name('payroll-summary');
        Route::get('/payroll-by-department', [ReportController::class, 'payrollByDepartment'])->name('payroll-by-department');
        Route::get('/employee-earnings', [ReportController::class, 'employeeEarnings'])->name('employee-earnings');
        Route::get('/payroll-register', [ReportController::class, 'payrollRegister'])->name('payroll-register');
        Route::get('/ytd-earnings', [ReportController::class, 'ytdEarnings'])->name('ytd-earnings');
        Route::get('/tax-liability-payroll', [ReportController::class, 'taxLiabilityPayroll'])->name('tax-liability-payroll');
        Route::get('/employer-contributions', [ReportController::class, 'employerContributions'])->name('employer-contributions');
        Route::get('/bank-disbursement', [ReportController::class, 'bankDisbursement'])->name('bank-disbursement');
        Route::get('/salary-revision-history', [ReportController::class, 'salaryRevisionHistory'])->name('salary-revision-history');
        
        // Comparative Reports
        Route::get('/comparative/profit-loss', [ReportController::class, 'comparativeProfitLoss'])->name('comparative.profit-loss');
        Route::get('/comparative/balance-sheet', [ReportController::class, 'comparativeBalanceSheet'])->name('comparative.balance-sheet');
        Route::get('/comparative/cash-flow', [ReportController::class, 'comparativeCashFlow'])->name('comparative.cash-flow');

        // Tax Reports
        Route::get('/vat-gst-return', [ReportController::class, 'vatGstReturn'])->name('vat-gst-return');
        Route::get('/tax-liability', [ReportController::class, 'taxLiability'])->name('tax-liability');

        // Custom Report Builder
        Route::get('/custom', [ReportController::class, 'customReportIndex'])->name('custom.index');
        Route::get('/custom/create', [ReportController::class, 'customReportCreate'])->name('custom.create');
        Route::post('/custom', [ReportController::class, 'customReportStore'])->name('custom.store');
        Route::get('/custom/{customReport}/edit', [ReportController::class, 'customReportEdit'])->name('custom.edit');
        Route::put('/custom/{customReport}', [ReportController::class, 'customReportUpdate'])->name('custom.update');
        Route::delete('/custom/{customReport}', [ReportController::class, 'customReportDestroy'])->name('custom.destroy');
        Route::get('/custom/{customReport}/run', [ReportController::class, 'customReportRun'])->name('custom.run');
        Route::post('/custom/{customReport}/toggle-favorite', [ReportController::class, 'customReportToggleFavorite'])->name('custom.toggle-favorite');
        Route::get('/custom/get-columns', [ReportController::class, 'customReportGetColumns'])->name('custom.get-columns');

        // Export Routes
        Route::get('/export/profit-loss', [ReportController::class, 'exportProfitLoss'])->name('export.profit-loss');
        Route::get('/export/balance-sheet', [ReportController::class, 'exportBalanceSheet'])->name('export.balance-sheet');
        Route::get('/export/cash-flow', [ReportController::class, 'exportCashFlow'])->name('export.cash-flow');
        Route::get('/export/trial-balance', [ReportController::class, 'exportTrialBalance'])->name('export.trial-balance');
        Route::get('/export/general-ledger', [ReportController::class, 'exportGeneralLedger'])->name('export.general-ledger');
        Route::get('/export/accounts-receivable', [ReportController::class, 'exportAccountsReceivable'])->name('export.accounts-receivable');
        Route::get('/export/accounts-payable', [ReportController::class, 'exportAccountsPayable'])->name('export.accounts-payable');
        Route::get('/export/sales-by-customer', [ReportController::class, 'exportSalesByCustomer'])->name('export.sales-by-customer');
        Route::get('/export/sales-by-item', [ReportController::class, 'exportSalesByItem'])->name('export.sales-by-item');
        Route::get('/export/purchase-by-vendor', [ReportController::class, 'exportPurchaseByVendor'])->name('export.purchase-by-vendor');
        Route::get('/export/inventory-summary', [ReportController::class, 'exportInventorySummary'])->name('export.inventory-summary');
        Route::get('/export/payroll-summary', [ReportController::class, 'exportPayrollSummary'])->name('export.payroll-summary');
        Route::get('/export/payroll-by-department', [ReportController::class, 'exportPayrollByDepartment'])->name('export.payroll-by-department');
        Route::get('/export/employee-earnings', [ReportController::class, 'exportEmployeeEarnings'])->name('export.employee-earnings');
        Route::get('/export/payroll-register', [ReportController::class, 'exportPayrollRegister'])->name('export.payroll-register');
        Route::get('/export/ytd-earnings', [ReportController::class, 'exportYtdEarnings'])->name('export.ytd-earnings');
        Route::get('/export/tax-liability-payroll', [ReportController::class, 'exportTaxLiabilityPayroll'])->name('export.tax-liability-payroll');
        Route::get('/export/employer-contributions', [ReportController::class, 'exportEmployerContributions'])->name('export.employer-contributions');
        Route::get('/export/bank-disbursement', [ReportController::class, 'exportBankDisbursement'])->name('export.bank-disbursement');
        Route::get('/export/salary-revision-history', [ReportController::class, 'exportSalaryRevisionHistory'])->name('export.salary-revision-history');
    });

    /*
    |--------------------------------------------------------------------------
    | Budgets Module (Professional & Enterprise Plans Only)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['plan:professional,enterprise', 'permission:view budgets'])->prefix('budgets')->name('budgets.')->group(function () {
        Route::get('/', [BudgetController::class, 'index'])->name('index');
        Route::get('/create', [BudgetController::class, 'create'])->name('create')->middleware('permission:create budgets');
        Route::post('/', [BudgetController::class, 'store'])->name('store')->middleware('permission:create budgets');
        Route::get('/import-template', [BudgetController::class, 'importTemplate'])->name('import-template');
        Route::get('/{budget}', [BudgetController::class, 'show'])->name('show');
        Route::get('/{budget}/edit', [BudgetController::class, 'edit'])->name('edit')->middleware('permission:edit budgets');
        Route::put('/{budget}', [BudgetController::class, 'update'])->name('update')->middleware('permission:edit budgets');
        Route::delete('/{budget}', [BudgetController::class, 'destroy'])->name('destroy')->middleware('permission:delete budgets');
        Route::post('/{budget}/activate', [BudgetController::class, 'activate'])->name('activate')->middleware('permission:edit budgets');
        Route::post('/{budget}/lock', [BudgetController::class, 'lock'])->name('lock')->middleware('permission:edit budgets');
        Route::get('/{budget}/vs-actual', [BudgetController::class, 'vsActual'])->name('vs-actual');
        Route::post('/{budget}/import', [BudgetController::class, 'import'])->name('import')->middleware('permission:edit budgets');
    });

    /*
    |--------------------------------------------------------------------------
    | Analytics Module
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:view reports')->prefix('analytics')->name('analytics.')->group(function () {
        Route::get('/', [AnalyticsController::class, 'index'])->name('index');
        Route::get('/chart-data', [AnalyticsController::class, 'chartData'])->name('chart-data');
    });

    /*
    |--------------------------------------------------------------------------
    | Settings Module
    |--------------------------------------------------------------------------
    */
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])
            ->middleware('permission:view settings')
            ->name('index');
        
        // Company Settings
        Route::middleware('permission:view settings')->group(function () {
            Route::get('/company', [SettingsController::class, 'company'])->name('company');
        });
        Route::post('/company', [SettingsController::class, 'updateCompany'])
            ->middleware('permission:edit settings')
            ->name('company.update');
        
        // User Management - Create routes MUST come before wildcard routes
        Route::middleware('permission:create users')->group(function () {
            Route::get('/users/create', [SettingsController::class, 'createUser'])->name('users.create');
            Route::post('/users', [SettingsController::class, 'storeUser'])->name('users.store');
        });
        Route::middleware('permission:view users')->group(function () {
            Route::get('/users', [SettingsController::class, 'users'])->name('users');
        });
        Route::middleware('permission:edit users')->group(function () {
            Route::get('/users/{user}/edit', [SettingsController::class, 'editUser'])->name('users.edit');
            Route::put('/users/{user}', [SettingsController::class, 'updateUser'])->name('users.update');
        });
        Route::delete('/users/{user}', [SettingsController::class, 'deleteUser'])
            ->middleware('permission:delete users')
            ->name('users.destroy');
        
        // Role Management - Create routes MUST come before wildcard routes
        Route::middleware('permission:create roles')->group(function () {
            Route::get('/roles/create', [SettingsController::class, 'createRole'])->name('roles.create');
            Route::post('/roles', [SettingsController::class, 'storeRole'])->name('roles.store');
        });
        Route::middleware('permission:view roles')->group(function () {
            Route::get('/roles', [SettingsController::class, 'roles'])->name('roles');
        });
        Route::middleware('permission:edit roles')->group(function () {
            Route::get('/roles/{role}/edit', [SettingsController::class, 'editRole'])->name('roles.edit');
            Route::put('/roles/{role}', [SettingsController::class, 'updateRole'])->name('roles.update');
        });
        Route::delete('/roles/{role}', [SettingsController::class, 'destroyRole'])
            ->middleware('permission:delete roles')
            ->name('roles.destroy');

        // Notification Settings
        Route::middleware('permission:view settings')->group(function () {
            Route::get('/notifications', [SettingsController::class, 'notifications'])->name('notifications');
        });
        Route::middleware('permission:edit settings')->group(function () {
            Route::put('/notifications', [SettingsController::class, 'updateNotifications'])->name('notifications.update');
            Route::post('/notifications/test', [SettingsController::class, 'sendTestEmail'])->name('notifications.test');
        });

        // Invoice Templates
        Route::middleware('permission:view settings')->group(function () {
            Route::get('/invoice-templates', [InvoiceTemplateController::class, 'index'])->name('invoice-templates.index');
            Route::get('/invoice-templates/create', [InvoiceTemplateController::class, 'create'])->name('invoice-templates.create');
            Route::get('/invoice-templates/{invoiceTemplate}/edit', [InvoiceTemplateController::class, 'edit'])->name('invoice-templates.edit');
        });
        Route::middleware('permission:edit settings')->group(function () {
            Route::post('/invoice-templates/{invoiceTemplate}/set-default', [InvoiceTemplateController::class, 'setDefault'])->name('invoice-templates.set-default');
            Route::delete('/invoice-templates/{invoiceTemplate}', [InvoiceTemplateController::class, 'destroy'])->name('invoice-templates.destroy');
        });

        // Subscription Management
        Route::get('/subscription', function () {
            return view('settings.subscription');
        })->name('subscription');
    });

    /*
    |--------------------------------------------------------------------------
    | Tax Configuration Module
    |--------------------------------------------------------------------------
    */
    // Tax Rates
    Route::middleware('permission:create tax-rates')->group(function () {
        Route::get('tax-rates/create', [TaxRateController::class, 'create'])->name('tax-rates.create');
        Route::post('tax-rates', [TaxRateController::class, 'store'])->name('tax-rates.store');
    });
    Route::middleware('permission:view tax-rates')->group(function () {
        Route::get('tax-rates', [TaxRateController::class, 'index'])->name('tax-rates.index');
        Route::get('tax-rates/{taxRate}', [TaxRateController::class, 'show'])->name('tax-rates.show');
    });
    Route::middleware('permission:edit tax-rates')->group(function () {
        Route::get('tax-rates/{taxRate}/edit', [TaxRateController::class, 'edit'])->name('tax-rates.edit');
        Route::put('tax-rates/{taxRate}', [TaxRateController::class, 'update'])->name('tax-rates.update');
        Route::post('tax-rates/{taxRate}/set-default', [TaxRateController::class, 'setDefault'])->name('tax-rates.set-default');
    });
    Route::delete('tax-rates/{taxRate}', [TaxRateController::class, 'destroy'])
        ->middleware('permission:delete tax-rates')
        ->name('tax-rates.destroy');

    // Tax Groups
    Route::middleware('permission:create tax-rates')->group(function () {
        Route::get('tax-groups/create', [TaxGroupController::class, 'create'])->name('tax-groups.create');
        Route::post('tax-groups', [TaxGroupController::class, 'store'])->name('tax-groups.store');
    });
    Route::middleware('permission:view tax-rates')->group(function () {
        Route::get('tax-groups', [TaxGroupController::class, 'index'])->name('tax-groups.index');
        Route::get('tax-groups/{taxGroup}', [TaxGroupController::class, 'show'])->name('tax-groups.show');
    });
    Route::middleware('permission:edit tax-rates')->group(function () {
        Route::get('tax-groups/{taxGroup}/edit', [TaxGroupController::class, 'edit'])->name('tax-groups.edit');
        Route::put('tax-groups/{taxGroup}', [TaxGroupController::class, 'update'])->name('tax-groups.update');
    });
    Route::delete('tax-groups/{taxGroup}', [TaxGroupController::class, 'destroy'])
        ->middleware('permission:delete tax-rates')
        ->name('tax-groups.destroy');

    /*
    |--------------------------------------------------------------------------
    | Activity Logs Module
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:view settings')->group(function () {
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('activity-logs/{activityLog}', [ActivityLogController::class, 'show'])->name('activity-logs.show');
    });

    /*
    |--------------------------------------------------------------------------
    | Data Export & Backup Module
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:export reports')->group(function () {
        Route::get('exports', [ExportController::class, 'index'])->name('exports.index');
        Route::get('exports/create', [ExportController::class, 'create'])->name('exports.create');
        Route::post('exports', [ExportController::class, 'store'])->name('exports.store');
        Route::post('exports/quick', [ExportController::class, 'quickExport'])->name('exports.quick');
        Route::get('exports/backup', [ExportController::class, 'backup'])->name('exports.backup');
        Route::post('exports/backup', [ExportController::class, 'processBackup'])->name('exports.backup.process');
        Route::post('exports/cleanup', [ExportController::class, 'cleanup'])->name('exports.cleanup');
        Route::get('exports/{export}', [ExportController::class, 'show'])->name('exports.show');
        Route::get('exports/{export}/download', [ExportController::class, 'download'])->name('exports.download');
        Route::delete('exports/{export}', [ExportController::class, 'destroy'])->name('exports.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Data Import Module
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:import data')->group(function () {
        Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
        Route::get('imports/create', [ImportController::class, 'create'])->name('imports.create');
        Route::post('imports/upload', [ImportController::class, 'upload'])->name('imports.upload');
        Route::get('imports/template', [ImportController::class, 'template'])->name('imports.template');
        Route::get('imports/{import}/mapping', [ImportController::class, 'mapping'])->name('imports.mapping');
        Route::post('imports/{import}/process', [ImportController::class, 'process'])->name('imports.process');
        Route::post('imports/{import}/retry', [ImportController::class, 'retry'])->name('imports.retry');
        Route::get('imports/{import}', [ImportController::class, 'show'])->name('imports.show');
        Route::delete('imports/{import}', [ImportController::class, 'destroy'])->name('imports.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Fixed Assets Module
    |--------------------------------------------------------------------------
    */
    // Fixed Assets - Create routes MUST come before wildcard routes
    Route::middleware('permission:create fixed-assets')->group(function () {
        Route::get('fixed-assets/create', [App\Http\Controllers\FixedAssetController::class, 'create'])->name('fixed-assets.create');
        Route::post('fixed-assets', [App\Http\Controllers\FixedAssetController::class, 'store'])->name('fixed-assets.store');
    });
    Route::middleware('permission:view fixed-assets')->group(function () {
        Route::get('fixed-assets', [App\Http\Controllers\FixedAssetController::class, 'index'])->name('fixed-assets.index');
        Route::get('fixed-assets/register', [App\Http\Controllers\FixedAssetController::class, 'register'])->name('fixed-assets.register');
        Route::get('fixed-assets/depreciation-schedule', [App\Http\Controllers\FixedAssetController::class, 'depreciationSchedule'])->name('fixed-assets.depreciation-schedule');
        Route::get('fixed-assets/{fixedAsset}', [App\Http\Controllers\FixedAssetController::class, 'show'])->name('fixed-assets.show');
        Route::get('fixed-assets/{fixedAsset}/schedule', [App\Http\Controllers\FixedAssetController::class, 'schedule'])->name('fixed-assets.schedule');
    });
    Route::middleware('permission:edit fixed-assets')->group(function () {
        Route::get('fixed-assets/{fixedAsset}/edit', [App\Http\Controllers\FixedAssetController::class, 'edit'])->name('fixed-assets.edit');
        Route::put('fixed-assets/{fixedAsset}', [App\Http\Controllers\FixedAssetController::class, 'update'])->name('fixed-assets.update');
        Route::patch('fixed-assets/{fixedAsset}', [App\Http\Controllers\FixedAssetController::class, 'update']);
        Route::post('fixed-assets/{fixedAsset}/depreciate', [App\Http\Controllers\FixedAssetController::class, 'depreciate'])->name('fixed-assets.depreciate');
        Route::post('fixed-assets/{fixedAsset}/dispose', [App\Http\Controllers\FixedAssetController::class, 'dispose'])->name('fixed-assets.dispose');
        Route::post('fixed-assets/run-depreciation', [App\Http\Controllers\FixedAssetController::class, 'runDepreciation'])->name('fixed-assets.run-depreciation');
        Route::post('fixed-asset-depreciations/{depreciation}/reverse', [App\Http\Controllers\FixedAssetController::class, 'reverseDepreciation'])->name('fixed-asset-depreciations.reverse');
    });
    Route::delete('fixed-assets/{fixedAsset}', [App\Http\Controllers\FixedAssetController::class, 'destroy'])
        ->middleware('permission:delete fixed-assets')
        ->name('fixed-assets.destroy');

    // Fixed Asset Categories - Create routes MUST come before wildcard routes
    Route::middleware('permission:create fixed-assets')->group(function () {
        Route::get('fixed-asset-categories/create', [App\Http\Controllers\FixedAssetCategoryController::class, 'create'])->name('fixed-asset-categories.create');
        Route::post('fixed-asset-categories', [App\Http\Controllers\FixedAssetCategoryController::class, 'store'])->name('fixed-asset-categories.store');
    });
    Route::middleware('permission:view fixed-assets')->group(function () {
        Route::get('fixed-asset-categories', [App\Http\Controllers\FixedAssetCategoryController::class, 'index'])->name('fixed-asset-categories.index');
        Route::get('fixed-asset-categories/{fixedAssetCategory}', [App\Http\Controllers\FixedAssetCategoryController::class, 'show'])->name('fixed-asset-categories.show');
    });
    Route::middleware('permission:edit fixed-assets')->group(function () {
        Route::get('fixed-asset-categories/{fixedAssetCategory}/edit', [App\Http\Controllers\FixedAssetCategoryController::class, 'edit'])->name('fixed-asset-categories.edit');
        Route::put('fixed-asset-categories/{fixedAssetCategory}', [App\Http\Controllers\FixedAssetCategoryController::class, 'update'])->name('fixed-asset-categories.update');
        Route::patch('fixed-asset-categories/{fixedAssetCategory}', [App\Http\Controllers\FixedAssetCategoryController::class, 'update']);
    });
    Route::delete('fixed-asset-categories/{fixedAssetCategory}', [App\Http\Controllers\FixedAssetCategoryController::class, 'destroy'])
        ->middleware('permission:delete fixed-assets')
        ->name('fixed-asset-categories.destroy');
});

require __DIR__.'/auth.php';
