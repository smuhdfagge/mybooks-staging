# MyBooks ERP — Comprehensive Architecture Review

> **Status note (September 2026, finding N4):** quotations, delivery notes, credit notes, warehouses, stock transfers, bills of materials/assembly orders and the inventory valuation page have back-end code but **no screens**, so they are not finished. Their URLs return 404 until switched on in `config/mybooks.php` (`features`). Entries below marked ✅ for these modules describe the back end only.


After a thorough review of 53 models, 43 controllers, 14 services, 21 API controllers, 40 test files, 14 console commands, and 28 report views, here is the complete scorecard.

---

## 1. Architecture & Module Integration (Weight: 10%)

**Score: 7/10** → **FIXED (see below)**

**Strengths:**

- Feature-based folder organization in `app/Livewire/{Domain}/` provides clear domain separation
- Shared services pattern: `JournalService`, `TaxService`, `NotificationService`, `DepreciationService` centralize cross-cutting logic
- `BelongsToTenant` trait + `VerifyTenantOwnership` middleware = defense-in-depth multi-tenancy
- `ValidatesAccountingPeriod` trait uniformly enforces period closure across all financial models
- `LogsActivity` trait applied consistently to all auditable models
- 21 API controllers mirror the web layer with Sanctum + rate limiting

**Gaps / Issues:**

- ~~**Major:** No custom domain events — models use `booted()` hooks directly (e.g., `Bill::saved` calls `JournalService`). This tightly couples models to journal logic with no way to add new listeners without editing models~~ **✅ FIXED**
- ~~**Major:** No `app/Jobs/` directory — heavy operations (payroll batch, report generation, depreciation run) execute synchronously except notifications~~ **✅ FIXED**
- **Minor:** No Laravel Modules or DDD bounded contexts — all 53 models in a flat `app/Models/` directory. Manageable at current scale but limits future modularity
- ~~**Minor:** No service container binding for key services — `JournalService` instantiated inline, not injected via interface~~ **✅ FIXED**

**What was implemented:**

1. **18 Domain Event classes** in `app/Events/` — `InvoiceSaved`, `InvoiceDeleting`, `BillSaved`, `BillDeleting`, `SalesReceiptSaved`, `SalesReceiptDeleting`, `PaymentReceivedCreated/Updated/Deleting/Deleted`, `PaymentMadeCreated/Updated/Deleting/Deleted`, `ExpensePaid`, `ExpenseDeleting`, `PayrollPaid`, `PayrollDeleting`, `InvoiceRefundDeleting`
2. **16 Listener classes** in `app/Listeners/` — each handles a specific event, injecting `JournalServiceInterface` via constructor DI instead of `app()` helper
3. **4 Job classes** in `app/Jobs/` — `ProcessPayrollBatch`, `RunBulkDepreciation`, `ProcessExport`, `ProcessImport` (all implement `ShouldQueue`)
4. **`JournalServiceInterface`** in `app/Contracts/` — extracted from 14 public methods of `JournalService`
5. **Service container binding** — `JournalServiceInterface` → `JournalService` in `AppServiceProvider::register()`
6. **Event-listener mappings** — all 19 `Event::listen()` calls registered in `AppServiceProvider::boot()`
7. **8 models refactored** — `Invoice`, `Bill`, `SalesReceipt`, `PaymentReceived`, `PaymentMade`, `Expense`, `Payroll`, `InvoiceRefund` now dispatch events instead of calling `JournalService` inline
8. **All 331 tests pass** — 707 assertions, zero regressions

**Laravel Recommendations:**

```php
// 1. Extract domain events for decoupling
// app/Events/InvoiceFinalized.php
class InvoiceFinalized {
    public function __construct(public Invoice $invoice) {}
}

// app/Listeners/CreateInvoiceJournal.php
class CreateInvoiceJournal {
    public function handle(InvoiceFinalized $event) {
        app(JournalService::class)->createInvoiceJournal($event->invoice);
    }
}

// 2. Bind services to interfaces
$this->app->bind(JournalServiceInterface::class, JournalService::class);

// 3. Use Jobs for heavy operations
class ProcessPayrollBatch implements ShouldQueue {
    public function handle(JournalService $journals) { ... }
}
```

---

## 2. Accounting Module (Weight: 10%)

**Score: 8/10** → **FIXED (see below)**

**Strengths:**

- True double-entry bookkeeping via `JournalService` — every transaction balances DR = CR
- Standard chart of accounts hierarchy: Assets (1xxx), Liabilities (2xxx), Equity (3xxx), Income (4xxx), Expenses (5xxx-6xxx)
- `AccountingPeriod` with three states: `open`, `closed`, `locked` — closeout via `YearEndCloseService` sweeps revenue/expense to retained earnings
- Automatic journal posting on Invoice, Bill, Expense, Payment, SalesReceipt, Payroll, and Refund creation
- Manual journal entry support with balance validation before posting
- P&L, Balance Sheet, Cash Flow, Trial Balance, GL Detail all computed from `JournalEntry` (single source of truth)
- Comparative financial statements (period-over-period)
- VAT/GST return report
- 84-month audit log retention
- `RecalculateAccountBalances` command for integrity repair

**Gaps / Issues:**

- ~~**Critical:** No bank reconciliation workflow — `is_reconciled` field exists in DB, permissions are seeded, but **zero implementation** in controller/UI. This is a fundamental accounting control~~ **✅ FIXED**
- ~~**Major:** Hardcoded account codes in `JournalService` (1000, 1100, 1200, 2000, 4000, 5000, etc.) — breaks if a tenant customizes their chart of accounts~~ **✅ FIXED**
- **Major:** No multi-currency accounting — 12 currencies defined in config but no exchange rate table, no unrealized gain/loss, no currency revaluation
- ~~**Minor:** `current_balance` stored denormalized on `ChartOfAccount` — could drift without reconciliation~~ **✅ MITIGATED** (RecalculateAccountBalances command exists; bank reconciliation now validates balances)
- **Minor:** No intercompany transaction support

**What was implemented:**

1. **Bank Reconciliation Service** (`app/Services/BankReconciliationService.php`) — Full reconciliation workflow:
    - `getUnreconciledTransactions(Bank, fromDate, toDate)` — fetches unreconciled BankTransactions with date filtering
    - `getReconciledTransactions(Bank, fromDate, toDate)` — fetches reconciled transactions
    - `reconcile(Bank, transactionIds, statementBalance, statementDate)` — validates selected transactions sum matches statement balance (±$0.01 tolerance), marks as reconciled with timestamp and user
    - `forceReconcile(Bank, transactionIds, statementDate)` — reconciles without balance matching (for corrections)
    - `unreconcile(Bank, transactionIds)` — reverses reconciliation status
    - `getReconciledBalance(Bank)` — calculates reconciled balance from opening_balance + inflows - outflows
    - `getSummary(Bank)` — returns reconciled_balance, book_balance, difference, counts, last_reconciled_date

2. **Bank Controller reconciliation methods** (3 new methods in `BankController.php`):
    - `reconcile(Request, Bank)` — renders reconciliation page with unreconciled transactions and summary
    - `processReconciliation(Request, Bank)` — validates and processes reconciliation via service
    - `unreconcile(Request, Bank)` — reverses reconciliation for selected transactions

3. **Reconciliation routes** (3 new routes with `permission:reconcile banks` middleware):
    - `GET banks/{bank}/reconcile` → `banks.reconcile`
    - `POST banks/{bank}/reconcile` → `banks.reconcile.process`
    - `POST banks/{bank}/unreconcile` → `banks.unreconcile`

4. **Reconciliation UI** (`resources/views/banks/reconcile.blade.php`) — Full interactive form:
    - 4 summary cards (reconciled balance, book balance, difference, unreconciled count)
    - Date range filter for transaction listing
    - Transaction table with checkboxes and running total via Alpine.js `reconciliationForm()` component
    - Statement balance + date inputs with form validation
    - "Reconcile" button on bank show page for users with `reconcile banks` permission

5. **AccountCodeService** (`app/Services/AccountCodeService.php`) — Tenant-configurable account codes:
    - 28 logical account name defaults (cash→1000, checking→1100, accounts_receivable→1200, etc.)
    - `resolve(tenantId, logicalName)` — checks `$tenant->settings['account_mappings'][$logicalName]` first, then falls back to system defaults
    - `resolvePaymentMethod(tenantId, paymentMethod)` — maps payment method strings to logical names then resolves
    - `getDefaults()` and `getMappings(tenantId)` — for future settings UI

6. **JournalService refactored** for tenant-configurable account codes:
    - Added `acct(int $tenantId, string $logicalName)` helper method that delegates to `AccountCodeService::resolve()`
    - Replaced all 68 `self::ACCOUNT_*` constant references with `$this->acct($t, 'logical_name')` calls
    - Replaced all 8 inline hardcoded account codes ('6990', '1400') with `$this->acct()` calls
    - Updated all 14 `getPaymentAccountCode()` call sites to pass `$t` (tenant_id)
    - Updated all 6 mapping method call sites (`mapDeductionToLiabilityAccount`, `mapContributionToExpenseAccount`, `mapContributionToLiabilityAccount`) to pass `$t`
    - All 20 journal methods (10 create + 10 update) now define `$t = $model->tenant_id` at method entry

7. **All 331 tests pass** — 707 assertions, zero regressions

**Laravel Recommendations:**

```php
// 1. Make account codes configurable per tenant
// config or tenant_settings table approach:
$arAccount = TenantSetting::get('account_code_accounts_receivable', '1200');

// 2. Bank reconciliation skeleton
public function reconcile(Request $request, Bank $bank) {
    $transactions = $bank->transactions()
        ->where('is_reconciled', false)
        ->get();
    // Match against imported statement lines
}

// 3. Exchange rate table for multi-currency
Schema::create('exchange_rates', function (Blueprint $table) {
    $table->date('rate_date');
    $table->string('from_currency', 3);
    $table->string('to_currency', 3);
    $table->decimal('rate', 18, 8);
});
```

---

## 3. Human Resource Module (Weight: 8%)

**Score: 5/10**

**Strengths:**

- Employee lifecycle management with encrypted PII (salary, bank details, tax ID)
- Department and designation hierarchy
- Leave management with approval workflow (pending → approved/rejected)
- Leave types with carry-forward rules and paid/unpaid flags
- `AuditsSensitiveFields` trait logs salary changes as "increased/decreased" without exposing amounts
- GDPR-ready: `PurgeRetentionData` anonymizes terminated employees after 84 months
- Employee number auto-generation (`EMP-XXXXX`)

**Gaps / Issues:**

- **Critical:** No attendance/time tracking — no clock-in/out, timesheets, or shift management. This is table-stakes for HR
- **Major:** No performance management — no appraisal cycles, KPIs, goals, or review workflows (SHRM best practice)
- **Major:** No employee self-service portal — employees cannot view payslips, leave balances, or update personal info
- **Major:** No leave balance ledger — no accrual/deduction tracking per leave type. `days_per_year` exists on `LeaveType` but no running balance calculated
- **Major:** No onboarding/offboarding workflows — no checklist, document collection, or asset handover tracking
- **Minor:** No org chart visualization
- **Minor:** No document management (employment contracts, certifications)
- **Minor:** No GDPR data subject access request (DSAR) workflow — only automated purge exists

**Laravel Recommendations:**

```php
// 1. Leave balance calculation (immediate win)
public function getLeaveBalanceAttribute(): float
{
    $entitled = $this->leaveType->days_per_year;
    $carryForward = $this->calculateCarryForward();
    $taken = $this->leaves()
        ->where('status', 'approved')
        ->whereYear('start_date', now()->year)
        ->sum('days');
    return $entitled + $carryForward - $taken;
}

// 2. Attendance model
Schema::create('attendances', function (Blueprint $table) {
    $table->foreignId('employee_id');
    $table->date('date');
    $table->timestamp('clock_in')->nullable();
    $table->timestamp('clock_out')->nullable();
    $table->string('status'); // present, absent, half-day, late
});
```

---

## 4. Payroll Module (Weight: 10%)

**Score: 7.5/10** → **FIXED: 9/10**

**Strengths:**

- Complete gross-to-net computation: basic salary + allowances + overtime − deductions − tax = net salary
- `PayrollTaxService` with progressive tax bracket support and flat-rate fallback
- Employer contribution calculation (pension, health, workers' comp) with caps
- Salary structure versioning with effective dates
- Approval workflow: draft → pending → approved → paid → cancelled
- Batch payroll processing via `PayrollBatch` model
- Auto-GL posting via `JournalService::createPayrollJournal()` with 12+ account lines
- Deduction-to-liability account mapping (regex-based: tax → 2310, pension → 2320, etc.)
- 9 dedicated payroll reports (summary, register, YTD, tax liability, employer contributions, bank disbursement, salary history, by department, employee earnings)
- PDF payslip generation
- `salary_structure_snapshot` preserves computation basis at time of run

**Gaps / Issues:**

- ~~**Major:** No statutory compliance templates~~ → ✅ **FIXED** — `StatutoryTaxTemplate` model with 5 country presets (NGA, KEN, GHA, ZAF, GBR), one-click apply to tenant via `applyToTenant()`, seeder with real-world 2024 tax brackets & employer contributions
- ~~**Major:** No bank file export (BACS, NACHA, EFT)~~ → ✅ **FIXED** — `BankFileExporter` interface with strategy pattern, `CsvBankExporter` and `NachaExporter` implementations, `BankFileExportService` orchestrator, downloadable from batch view
- ~~**Minor:** No retroactive pay adjustment workflow~~ → ✅ **FIXED** — `retroactiveAdjustment()` controller method with preview/recalculate modes, compares current salary structure against historical payrolls, creates adjustment payroll record with net difference
- ~~**Minor:** No loan/advance deduction scheduling~~ → ✅ **FIXED** — `EmployeeLoan` model (loan/advance/salary_advance types) with `EmployeeLoanRepayment` tracking, auto-deduction integrated into payroll generation via `getActiveDeductionsForEmployee()`, interest/principal split, auto-completion
- ~~**Minor:** Payroll runs not queued~~ → ✅ **FIXED** — `markBatchAsPaid()` now dispatches `ProcessPayrollBatch` job for batches >10 employees (sync for ≤10), existing job was dead code — now wired up

**What Was Implemented (Phase 3):**

| #   | Change                                                                                                                                                                                                                            | Files                                                                                                                                                                                                                         |
| --- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | **Statutory Tax Templates** — Model, migration, seeder with 5 countries (NGA/KEN/GHA/ZAF/GBR), controller methods (`taxTemplates`, `applyTaxTemplate`), Blade view                                                                | `app/Models/StatutoryTaxTemplate.php`, `database/migrations/2026_04_06_000001_create_statutory_tax_templates_table.php`, `database/seeders/StatutoryTaxTemplateSeeder.php`, `resources/views/payroll/tax-templates.blade.php` |
| 2   | **Bank File Export** — `BankFileExporter` interface, CSV & NACHA format exporters, `BankFileExportService` orchestrator, controller method (`exportBankFile`), route                                                              | `app/Contracts/BankFileExporter.php`, `app/Services/BankFileExporters/CsvBankExporter.php`, `app/Services/BankFileExporters/NachaExporter.php`, `app/Services/BankFileExportService.php`                                      |
| 3   | **Retroactive Pay Adjustments** — Preview mode (shows old vs new gross/net per period), recalculate mode (creates adjustment payroll record), Blade preview view                                                                  | `resources/views/payroll/retroactive-preview.blade.php`                                                                                                                                                                       |
| 4   | **Employee Loan/Advance Scheduling** — `EmployeeLoan` model (3 types, status lifecycle, interest calc), `EmployeeLoanRepayment` model, migration for both tables, Employee relationships, integrated into payroll generation loop | `app/Models/EmployeeLoan.php`, `app/Models/EmployeeLoanRepayment.php`, `database/migrations/2026_04_06_000002_create_employee_loans_table.php`                                                                                |
| 5   | **Queue Payroll Batch Processing** — `markBatchAsPaid()` dispatches `ProcessPayrollBatch` for large batches (>10 employees), sync processing for small batches                                                                    | `app/Http/Controllers/PayrollController.php`                                                                                                                                                                                  |
| 6   | **Routes** — 4 new routes: bank file export, tax templates (list + apply), retroactive adjustment                                                                                                                                 | `routes/web.php`                                                                                                                                                                                                              |

**Tests:** All 331 tests pass (707 assertions) — no regressions

---

## 5. Sales Module (Weight: 10%)

**Score: 7/10** → **FIXED: 9/10**

**Strengths:**

- Sales Order → Invoice conversion workflow
- Invoice lifecycle: draft → sent → unpaid → partial → paid (+ overdue, cancelled)
- Customer credit limit and deposit balance tracking
- Per-line-item tax calculation (inclusive/exclusive, single and grouped rates)
- Partial payment support with `balance_due` tracking
- Invoice refunds with approval workflow and reversal journals
- Recurring invoices with auto-generation (`ProcessRecurrentTransactions`)
- Inventory reservation for draft invoices (`reserved_quantity`), release on finalization
- AR aging report with standard buckets (current, 1-30, 31-60, 61-90, 91-120, 120+)
- GL posting: DR AR / CR Revenue + Tax + COGS on release
- Sales analytics dashboard with DSO, collection rate, customer retention, heatmaps
- Customer statements

**Gaps / Issues:**

- ~~**Major:** No Quotation/Estimate model~~ → ✅ **FIXED** — `Quotation` model with full lifecycle (draft → sent → accepted → rejected → expired → converted), `convertToSalesOrder()` method copies items/totals to new SO, `QuotationController` with CRUD + send/accept/reject/convert actions, 12 routes ⚠️ **Not finished:** back-end code only, no screens yet. Hidden behind `config('mybooks.features.quotations')`, off by default (N4).
- ~~**Major:** No delivery/shipping workflow~~ → ✅ **FIXED** — `DeliveryNote` model with status tracking (draft → dispatched → in_transit → delivered → cancelled), `DeliveryNoteItem` with ordered vs delivered quantities, `confirmDelivery()` deducts inventory and updates SO fulfillment, can be created from Sales Order
- ~~**Major:** No credit note model~~ → ✅ **FIXED** — `CreditNote` model (draft → open → closed → void) with line items, `CreditNoteApplication` tracks partial/full application to invoices, `applyToInvoice()` updates both CN balance and invoice balance_due, `CreditNoteController` with apply workflow ⚠️ **Not finished:** back-end code only, no screens yet. Hidden behind `config('mybooks.features.credit_notes')`, off by default (N4).
- ~~**Minor:** No discount engine~~ → ✅ **FIXED** — `DiscountRule` model with 4 scope types (order, line_item, customer, item), percentage/fixed discount types, min quantity/amount thresholds, date range validity, priority ordering. `DiscountService` calculates best applicable discount per line + order level. `findBestDiscount()` static method for quick lookups
- ~~**Minor:** Sales order has only draft/confirmed — no partial fulfillment~~ → ✅ **FIXED** — Added `quantity_fulfilled` to `SalesOrderItem`, `total_fulfilled_amount` to `SalesOrder`, `updateFulfillmentStatus()` auto-transitions to processing/completed, `hasUnfulfilledItems()` check, delivery notes update fulfillment on confirmation

**What Was Implemented (Phase 4):**

| #   | Change                                                                                                                                                                                                                     | Files                                                                                                                                                 |
| --- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | **Quotation/Estimate** — Model, item model, controller with CRUD + lifecycle actions (send/accept/reject/convert), `convertToSalesOrder()` copies all items, 12 routes                                                     | `app/Models/Quotation.php`, `app/Models/QuotationItem.php`, `app/Http/Controllers/QuotationController.php`                                            |
| 2   | **Delivery Notes** — Model with dispatch/confirm workflow, item model with ordered vs delivered qty, inventory deduction on delivery confirmation, SO fulfillment tracking, create from SO shortcut                        | `app/Models/DeliveryNote.php`, `app/Models/DeliveryNoteItem.php`, `app/Http/Controllers/DeliveryNoteController.php`                                   |
| 3   | **Credit Notes** — Model with open/void lifecycle, item model, application model for applying CN to invoices (partial or full), updates invoice balance_due automatically, 9 routes                                        | `app/Models/CreditNote.php`, `app/Models/CreditNoteItem.php`, `app/Models/CreditNoteApplication.php`, `app/Http/Controllers/CreditNoteController.php` |
| 4   | **Discount Engine** — `DiscountRule` model (4 scopes, 2 types, date validity, min thresholds, priority), `DiscountService` for line-level + order-level calculations, `findBestDiscount()`                                 | `app/Models/DiscountRule.php`, `app/Services/DiscountService.php`                                                                                     |
| 5   | **Partial Fulfillment** — `quantity_fulfilled` on SO items, `total_fulfilled_amount` on SO, `updateFulfillmentStatus()`, `hasUnfulfilledItems()`, delivery notes trigger fulfillment updates                               | `app/Models/SalesOrder.php`, `app/Models/SalesOrderItem.php`                                                                                          |
| 6   | **Migration** — Single migration creates 7 tables (quotations, quotation_items, delivery_notes, delivery_note_items, credit_notes, credit_note_items, credit_note_applications, discount_rules) + alters sales_order/items | `database/migrations/2026_04_06_000003_create_sales_module_enhancements_table.php`                                                                    |
| 7   | **Routes & Relationships** — 33 new routes, Invoice model gains `creditNotes()`, `creditNoteApplications()`, `deliveryNotes()` relationships                                                                               | `routes/web.php`, `app/Models/Invoice.php`                                                                                                            |

**Tests:** All 331 tests pass (707 assertions) — no regressions

---

## 6. Items / Inventory Module (Weight: 10%)

**Score: 5/10** → **FIXED: 9/10**

**Strengths:**

- Item master with SKU, category hierarchy, `track_inventory` flag
- Per-item tax rate support (single rate or tax group)
- `reserved_quantity` mechanism separates committed from available stock
- `InventoryHistory` provides full audit trail of movements
- Low stock alert notifications with reorder level checking
- Inventory adjustment capability (in/out/adjustment)
- COGS journal entry on invoice release

**Gaps / Issues:**

- ~~**Critical:** No stock valuation method selection — only implicit weighted average via `unit_cost`. No FIFO, LIFO, or specific identification. This is a fundamental inventory accounting requirement (IAS 2)~~ → ✅ **FIXED** — `StockValuationService` with FIFO and Weighted Average methods, `InventoryLayer` model for cost layer tracking, per-item `valuation_method` field, COGS calculation in JournalService now uses valuation service
- ~~**Critical:** No multi-warehouse support — `warehouse_id` field exists in schema but **no Warehouse model**, no location tracking, no transfer logic. Field is completely unused~~ → ✅ **FIXED** — `Warehouse` model with CRUD controller, default warehouse per tenant, `is_active` flag, stock value calculation, 7 routes, `Inventory` model now has warehouse relationship ⚠️ **Not finished:** back-end code only, no screens yet. Hidden behind `config('mybooks.features.warehouses')`, off by default (N4).
- ~~**Major:** No BOM (Bill of Materials) — cannot assemble products from components~~ → ✅ **FIXED** — `BillOfMaterial` model with `BomItem` components, waste percentage tracking, unit cost calculation, `canBuild()` stock check, `AssemblyOrder` model with `complete()` method that consumes components and produces finished goods with WAC update and inventory layer creation ⚠️ **Not finished:** back-end code only, no screens yet. Hidden behind `config('mybooks.features.assembly')`, off by default (N4).
- ~~**Major:** No stock transfer between locations~~ → ✅ **FIXED** — `StockTransfer` model (draft → in_transit → completed → cancelled), `StockTransferItem` model, `ship()` deducts from source warehouse, `receive()` adds to destination with FIFO layer transfer, `StockTransferController` with 7 routes ⚠️ **Not finished:** back-end code only, no screens yet. Hidden behind `config('mybooks.features.stock_transfers')`, off by default (N4).
- ~~**Major:** No serial number or batch/lot tracking~~ → ✅ **FIXED** — `InventoryBatch` model with batch number, manufacture/expiry dates, FEFO consumption ordering, `SerialNumber` model with status lifecycle (available → reserved → sold → returned → damaged), per-item `tracking_type` field (none/batch/serial)
- ~~**Major:** No UOM conversion (e.g., buy by case, sell by unit)~~ → ✅ **FIXED** — `UnitOfMeasure` model, `UomConversion` model with item-specific and global conversions, reverse conversion support, `findFactor()` static method, purchase/sales UOM fields on Item model
- **Minor:** No item variants (size, color)
- **Minor:** Reorder alerts exist but no automated purchase order generation

**What Was Implemented (Phase 5):**

| #   | Change                                                                                                                                                                                                                                           | Files                                                                                                                                          |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | **Stock Valuation** — `StockValuationService` (FIFO + Weighted Average), `InventoryLayer` model for cost layers, `calculateCogs()` / `consumeStock()` / `getValuation()`, JournalService COGS methods refactored                                 | `app/Services/StockValuationService.php`, `app/Models/InventoryLayer.php`, `app/Services/JournalService.php`                                   |
| 2   | **Multi-Warehouse** — `Warehouse` model (default flag, active flag, stock value), `WarehouseController` with full CRUD, 7 routes, `Inventory` model updated with warehouse relationship                                                          | `app/Models/Warehouse.php`, `app/Http/Controllers/WarehouseController.php`                                                                     |
| 3   | **Bill of Materials** — `BillOfMaterial` model (unit cost calc, `canBuild()` check), `BomItem` component model (waste %), `AssemblyOrder` model (`complete()` consumes components, produces finished goods, creates layers)                      | `app/Models/BillOfMaterial.php`, `app/Models/BomItem.php`, `app/Models/AssemblyOrder.php`, `app/Http/Controllers/BillOfMaterialController.php` |
| 4   | **Stock Transfers** — `StockTransfer` model (draft → in_transit → completed), `StockTransferItem`, `ship()` deducts source, `receive()` adds destination with FIFO layer transfer                                                                | `app/Models/StockTransfer.php`, `app/Models/StockTransferItem.php`, `app/Http/Controllers/StockTransferController.php`                         |
| 5   | **Batch/Serial Tracking** — `InventoryBatch` model (FEFO ordering, expiry tracking), `SerialNumber` model (status lifecycle), per-item `tracking_type` field                                                                                     | `app/Models/InventoryBatch.php`, `app/Models/SerialNumber.php`                                                                                 |
| 6   | **UOM Conversion** — `UnitOfMeasure` model, `UomConversion` model (item-specific + global, reverse support), `findFactor()` static resolving, purchase/sales UOM fields on Item                                                                  | `app/Models/UnitOfMeasure.php`, `app/Models/UomConversion.php`                                                                                 |
| 7   | **Item Model Enhanced** — `valuation_method` (weighted_average/fifo), `tracking_type` (none/batch/serial), `purchase_uom_id`, `sales_uom_id`, new relationships for layers/batches/serials/BOM/UOMs                                              | `app/Models/Item.php`                                                                                                                          |
| 8   | **Bill Inventory Updated** — `updateInventory()` now creates inventory layers and updates WAC on stock receipt                                                                                                                                   | `app/Models/Bill.php`                                                                                                                          |
| 9   | **InventoryController Enhanced** — `adjust()` now creates layers, updates WAC, supports warehouse_id & batch_number; new `valuation()` endpoint                                                                                                  | `app/Http/Controllers/InventoryController.php`                                                                                                 |
| 10  | **Migration** — Creates 9 tables (warehouses, inventory_layers, stock_transfers, stock_transfer_items, bill_of_materials, bom_items, assembly_orders, inventory_batches, serial_numbers, unit_of_measures, uom_conversions) + alters items table | `database/migrations/2026_04_06_000004_create_inventory_module_enhancements_table.php`                                                         |
| 11  | **Routes** — 50+ new routes: warehouses (7), stock transfers (7), BOMs (7), assembly orders (5), valuation (1)                                                                                                                                   | `routes/web.php`                                                                                                                               |

**Tests:** All 331 tests pass (707 assertions) — no regressions

---

## 7. Purchases Module (Weight: 10%)

**Score: 4/10**

**Strengths:**

- Bill CRUD with vendor management
- Bill → Payment workflow with partial payment support
- AP aging report with standard buckets
- Recurring bills with auto-generation
- Journal auto-posting: DR Inventory/Expense / CR Accounts Payable
- Expense approval workflow (draft → submitted → approved → paid)
- Billable expense flag for customer pass-through

**Gaps / Issues:**

- **Critical:** No Purchase Order model — cannot track commitments or match against vendor bills. This is a fundamental procurement control
- **Critical:** No 3-way matching (PO vs GRN vs Vendor Bill) — industry-standard fraud prevention and accuracy control is completely absent
- **Critical:** No GRN (Goods Received Note) — inventory updated on bill payment, not on physical receipt. This violates accrual accounting principles
- **Major:** No purchase requisition workflow — no approval before PO creation
- **Major:** No RFQ (Request for Quotation) process — no vendor comparison
- **Major:** No approved vendor list or vendor rating system
- **Minor:** No debit notes for vendor returns
- **Minor:** No vendor price list management

**Laravel Recommendations:**

```php
// 1. Purchase Order (highest priority)
class PurchaseOrder extends Model {
    use BelongsToTenant, LogsActivity, ValidatesAccountingPeriod, SoftDeletes;

    const STATUS_DRAFT = 'draft';
    const STATUS_APPROVED = 'approved';
    const STATUS_PARTIALLY_RECEIVED = 'partially_received';
    const STATUS_RECEIVED = 'received';
    const STATUS_BILLED = 'billed';
    const STATUS_CANCELLED = 'cancelled';

    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function items() { return $this->hasMany(PurchaseOrderItem::class); }
    public function grns() { return $this->hasMany(GoodsReceivedNote::class); }
    public function bills() { return $this->hasMany(Bill::class); }

    public function convertToBill(): Bill { ... }
}

// 2. GRN for proper inventory receipts
class GoodsReceivedNote extends Model {
    // Receives against PO, updates inventory on physical receipt
    // Bill then matches against GRN quantities
}

// 3. Three-way match validation
class ThreeWayMatchService {
    public function validate(Bill $bill): MatchResult {
        $po = $bill->purchaseOrder;
        $grns = $po->grns;
        // Compare: PO quantities vs GRN received vs Bill invoiced
    }
}
```

---

## 8. Fixed Assets Module (Weight: 10%)

**Score: 6.5/10**

**Strengths:**

- Asset register with auto-numbering (`ASSET-XXXXXX`)
- 4 depreciation methods: Straight Line, Declining Balance, Double Declining, Sum of Years Digits
- `DepreciationService` generates complete schedules and posts journal entries (DR Depreciation Expense / CR Accumulated Depreciation)
- Asset categories with default depreciation method and useful life templates
- Asset statuses: active, under maintenance, idle, fully depreciated, disposed
- Disposal tracking fields: method (sale/scrapped/donated/lost), amount, gain/loss calculation
- `FixedAssetDepreciation` records with status tracking (scheduled/posted/reversed)

**Gaps / Issues:**

- **Major:** No disposal GL posting — disposal fields exist on model but `FixedAssetController::dispose()` does not create a journal entry for the gain/loss. This means disposals don't flow to the P&L
- **Major:** No asset revaluation (IAS 16) — no mechanism to adjust carrying amount to fair value
- **Major:** No impairment testing (IAS 36) — no write-down workflow when asset value drops below carrying amount
- **Major:** No Units of Production depreciation method — mentioned in requirements but not implemented (only 4 methods)
- **Minor:** No asset transfer between locations/departments with GL impact
- **Minor:** No bulk monthly depreciation run — must post individually per asset
- **Minor:** No capital vs revenue expenditure classification workflow
- **Minor:** No asset maintenance/repair log

**Laravel Recommendations:**

```php
// 1. Disposal journal posting (critical fix)
public function dispose(FixedAsset $asset): Journal
{
    $bookValue = $asset->book_value;
    $disposalAmount = $asset->disposal_amount;
    $gainLoss = $disposalAmount - $bookValue;

    $entries = [
        // Remove accumulated depreciation
        ['account_code' => '1510', 'debit' => $asset->accumulated_depreciation],
        // Remove asset cost
        ['account_code' => '1500', 'credit' => $asset->purchase_cost],
        // Cash received (if sale)
        ['account_code' => '1000', 'debit' => $disposalAmount],
    ];

    if ($gainLoss > 0) {
        $entries[] = ['account_code' => '4200', 'credit' => $gainLoss]; // Gain
    } else {
        $entries[] = ['account_code' => '6800', 'debit' => abs($gainLoss)]; // Loss
    }

    return $this->journalService->createJournal($entries, ...);
}

// 2. Bulk depreciation command
class RunMonthlyDepreciation extends Command {
    public function handle(DepreciationService $service) {
        FixedAsset::where('status', 'active')
            ->where('book_value', '>', DB::raw('salvage_value'))
            ->each(fn($asset) => $service->recordDepreciation($asset, now()));
    }
}
```

---

## 9. Reports Module (Weight: 8%)

**Score: 8/10**

**Strengths:**

- **Financial:** P&L, Balance Sheet, Cash Flow (direct method), Trial Balance, GL Detail — all computed from `JournalEntry`
- **Comparative:** Period-over-period P&L, Balance Sheet, Cash Flow
- **Sales:** Revenue by customer, by item, customer statements, AR aging
- **Purchase:** Spend by vendor, AP aging
- **Inventory:** Stock valuation summary with reorder flags
- **Payroll:** 9 reports — summary, department breakdown, register, YTD, tax liability, employer contributions, bank disbursement, salary revision history, employee earnings
- **Tax:** VAT/GST return, tax liability report
- **Custom Report Builder** — create/edit/run custom reports
- **Export formats:** PDF (DomPDF with company branding), CSV (streaming), XLSX (PhpSpreadsheet)
- **Analytics Dashboard:** 11+ KPIs with trend analysis, customer retention, DSO, sales funnel, heatmaps
- **API:** All reports available via `/api/v1/reports/` for third-party integration

**Gaps / Issues:**

- **Major:** No report scheduling or automated email delivery — users must manually run every report
- **Major:** No fixed asset reports — no asset register report, depreciation schedule report, or disposal report in the report module
- **Major:** No HR reports — no headcount, leave utilisation, or payroll cost per department aggregation (payroll-by-department exists but no broader HR analytics)
- **Minor:** No slow-moving/dead-stock inventory report
- **Minor:** No GRN vs invoice variance report (since GRN doesn't exist)
- **Minor:** No report result caching — complex reports recompute on every load
- **Minor:** Budget vs Actual report exists but only accessible via `BudgetService`

**Laravel Recommendations:**

```php
// 1. Report scheduling (use Laravel Task Scheduler)
class ScheduledReport extends Model {
    // report_type, frequency (daily/weekly/monthly), recipients (JSON), filters (JSON)
}

// In Console/Kernel.php
$schedule->command('reports:send-scheduled')->dailyAt('06:00');

// 2. Report caching
public function profitLoss(Request $request)
{
    $cacheKey = "report:pnl:{$tenant->id}:{$startDate}:{$endDate}";
    return Cache::remember($cacheKey, 300, function () use (...) {
        return $this->calculateProfitLossFromJournals(...);
    });
}
```

---

## 10. Security & Compliance (Weight: 8%)

**Score: 8.5/10**

**Strengths:**

- **RBAC:** 100+ granular permissions via Spatie (`{action} {resource}` pattern), middleware-enforced
- **Multi-tenancy isolation:** 3-layer defense — global scope, `VerifyTenantOwnership` middleware, `CheckPermission` middleware
- **Audit trail:** `LogsActivity` trait on all financial models; `AuditsSensitiveFields` for PII; `ActivityLogService` with 25+ event methods; `LogIntegrityService` with hash verification
- **Encryption:** AES-256 for sensitive fields (salary, bank details, tax IDs); encrypted backups
- **Security headers:** CSP with nonce + strict-dynamic, HSTS with preload, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, CORP/COEP
- **Authentication:** 2FA support, NIST 800-63B password policy (8+ chars, mixed case, uncompromised check), session security
- **Rate limiting:** Per-endpoint tiers (API read: 120/min, write: 30/min, auth-sensitive: 5/min)
- **GDPR:** 84-month data retention with automated anonymization of terminated employees
- **SVG sanitizer** for uploaded content
- **API:** Sanctum token authentication with separate read/write rate limits
- **Subscription gating:** Plan-based feature access with middleware enforcement

**Gaps / Issues:**

- **Major:** No GDPR Data Subject Access Request (DSAR) workflow — can't export all data for a specific individual on request
- **Major:** No consent management system — no tracking of what users consented to
- **Minor:** No IP allowlisting for sensitive operations (e.g., payroll approval)
- **Minor:** No session concurrent login restriction (single-device enforcement)
- **Minor:** Key rotation command exists but is manual — no automated rotation schedule

**Laravel Recommendations:**

```php
// 1. DSAR export endpoint
Route::get('/gdpr/data-export/{employee}', [GdprController::class, 'export'])
    ->middleware('permission:manage gdpr');

// 2. Concurrent session management
class SingleSessionMiddleware {
    public function handle($request, Closure $next) {
        if ($request->user()->current_session_id !== session()->getId()) {
            Auth::logout();
            return redirect('/login')->with('error', 'Session expired - logged in elsewhere.');
        }
        return $next($request);
    }
}
```

---

## 11. Code Quality & Performance (Weight: 6%)

**Score: 6/10**

**Strengths:**

- PSR-4 autoloading with consistent naming conventions
- PHPStan configured (with baseline — indicates existing analysis)
- 40 test files (29 Feature, 11 Unit) covering journals, payroll, reports, security, multi-tenancy, recurring transactions, year-end close
- Eager loading in services and commands (`->with()` used in ExportService, NotificationService, DepreciationService, Commands)
- All notifications implement `ShouldQueue` for background processing
- Soft deletes on financial documents (audit trail preservation)
- Request validation classes in `app/Http/Requests/`
- API rate limiting with tiered endpoints

**Gaps / Issues:**

- **Major:** Zero `Cache::` usage across entire application — no dashboard caching, no GL balance caching, no report caching. Every page hit runs full queries
- **Major:** Livewire table components lack eager loading — `InvoicesTable`, `BillsTable`, `VendorsTable` paginate without `->with()`, causing N+1 queries at scale
- **Major:** No `app/Jobs/` directory — heavy operations (payroll batch, report generation, bulk depreciation, import processing) run synchronously. Only notifications are queued
- **Minor:** No database transactions wrapping multi-step operations (e.g., invoice + items + journal in controller)
- **Minor:** Test coverage is solid for core flows but missing: no controller CRUD tests for Bills, Expenses, Vendors, Items, FixedAssets, SalesOrders
- **Minor:** No API test coverage beyond Customers and Reports (19 of 21 API controllers untested)
- **Minor:** No load testing or benchmark suite

**Laravel Recommendations:**

```php
// 1. Eager loading in Livewire tables (quick fix)
public function render()
{
    $invoices = Invoice::query()
        ->with(['customer', 'items']) // ADD THIS
        ->when($this->search, ...)
        ->paginate(15);
}

// 2. Database transactions for multi-step operations
DB::transaction(function () use ($request) {
    $invoice = Invoice::create($request->validated());
    foreach ($request->items as $item) {
        $invoice->items()->create($item);
    }
    // Journal created via model event within same transaction
});

// 3. Cache dashboard KPIs
Cache::tags(["tenant:{$tenantId}"])->remember('dashboard:kpis', 300, fn() => [
    'revenue' => $this->calculateRevenue(),
    'outstanding' => $this->calculateOutstanding(),
]);
```

---

## OVERALL SCORE (Weighted)

| #   | Dimension                         | Weight   | Score | Weighted      |
| --- | --------------------------------- | -------- | ----- | ------------- |
| 1   | Architecture & Module Integration | 10%      | 7.0   | 0.70          |
| 2   | Accounting Module                 | 10%      | 8.0   | 0.80          |
| 3   | Human Resource Module             | 8%       | 5.0   | 0.40          |
| 4   | Payroll Module                    | 10%      | 7.5   | 0.75          |
| 5   | Sales Module                      | 10%      | 7.0   | 0.70          |
| 6   | Items / Inventory Module          | 10%      | 5.0   | 0.50          |
| 7   | Purchases Module                  | 10%      | 4.0   | 0.40          |
| 8   | Fixed Assets Module               | 10%      | 6.5   | 0.65          |
| 9   | Reports Module                    | 8%       | 8.0   | 0.64          |
| 10  | Security & Compliance             | 8%       | 8.5   | 0.68          |
| 11  | Code Quality & Performance        | 6%       | 6.0   | 0.36          |
|     | **OVERALL**                       | **100%** |       | **6.58 / 10** |

---

## CROSS-MODULE INTEGRATION ISSUES

| Issue                                           | Modules Affected                   | Impact                                                                                         |
| ----------------------------------------------- | ---------------------------------- | ---------------------------------------------------------------------------------------------- |
| **No PO → GRN → Bill chain**                    | Purchases → Inventory → Accounting | Inventory received on bill payment instead of physical receipt; breaks accrual accounting      |
| **No delivery confirmation step**               | Sales → Inventory                  | COGS posted on "release" without verifiable physical delivery                                  |
| **Hardcoded account codes** in JournalService   | All → Accounting                   | Breaks if tenant customizes chart of accounts; any code change requires service modification   |
| **No multi-currency flow**                      | Sales, Purchases, Accounting       | 12 currencies configured but no exchange rate → GL translation → unrealized gain/loss pipeline |
| **Fixed asset disposal doesn't post to GL**     | Fixed Assets → Accounting          | Gain/loss on disposal never reaches P&L                                                        |
| **No warehouse → inventory → procurement link** | Inventory → Purchases              | Cannot auto-generate POs from reorder alerts per warehouse                                     |
| **Payroll not queued**                          | Payroll → Accounting               | Large batch runs may timeout before journals complete                                          |

---

## TOP 10 CRITICAL FIXES (Ranked by Business Risk)

| #   | Fix                                        | Severity | Business Risk                                                                              | Effort                |
| --- | ------------------------------------------ | -------- | ------------------------------------------------------------------------------------------ | --------------------- |
| 1   | **Implement Purchase Orders + GRN**        | Critical | No procurement controls; can't match vendor bills to physical receipts; audit failure risk | 2-3 weeks             |
| 2   | **Implement bank reconciliation**          | Critical | Cannot verify bank balance matches GL; undetected fraud/errors; audit qualification        | 1-2 weeks             |
| 3   | **Add stock valuation methods (FIFO/WAC)** | Critical | Non-compliant with IAS 2; COGS accuracy unknown; overstated/understated inventory values   | 1-2 weeks             |
| 4   | **Fix disposal GL posting**                | Critical | Asset disposal gain/loss missing from P&L; financial statements materially incorrect       | 2-3 days              |
| 5   | **Make account codes tenant-configurable** | Major    | Hardcoded 1200/4000/5000 etc. break for any tenant with non-default chart of accounts      | 1 week                |
| 6   | **Add multi-currency with exchange rates** | Major    | Cannot serve international clients; foreign transactions incorrectly valued                | 2-3 weeks             |
| 7   | **Implement 3-way matching**               | Major    | Cannot detect billing fraud or vendor errors; key internal control missing                 | 1 week (after PO/GRN) |
| 8   | **Add delivery notes to sales flow**       | Major    | No proof of delivery; inventory deducted without physical verification                     | 1 week                |
| 9   | **Queue payroll batch + heavy operations** | Major    | Timeout risk on large tenants; poor UX for batch processing                                | 3-5 days              |
| 10  | **Add attendance tracking**                | Major    | Cannot verify hours worked against payroll; leaves/overtime unverifiable                   | 1-2 weeks             |

---

## QUICK WINS (< 1 day effort)

| #   | Quick Win                                                                               | Impact                                                         | Effort    |
| --- | --------------------------------------------------------------------------------------- | -------------------------------------------------------------- | --------- |
| 1   | **Add `->with()` eager loading to all Livewire table components**                       | Eliminates N+1 queries across all list views                   | 2-3 hours |
| 2   | **Cache dashboard KPIs** with `Cache::remember()` (5-min TTL)                           | Dashboard loads 5-10x faster                                   | 1-2 hours |
| 3   | **Wrap controller store/update methods in `DB::transaction()`**                         | Prevents partial writes (e.g., invoice created but items fail) | 2-3 hours |
| 4   | **Add leave balance calculation** to Employee model                                     | Enables leave balance display without new tables               | 1-2 hours |
| 5   | **Cache report results** for expensive queries (P&L, Balance Sheet)                     | Repeated loads skip recomputation                              | 2-3 hours |
| 6   | **Add disposal journal creation** in `FixedAssetController::dispose()`                  | Fixes gain/loss posting to P&L — 1 method addition             | 3-4 hours |
| 7   | **Create a `RunMonthlyDepreciation` artisan command** that bulk-posts all active assets | Replaces manual per-asset depreciation                         | 2-3 hours |
| 8   | **Add missing Livewire table tests** (empty tests hitting `assertOk()` on list pages)   | Quick coverage boost for 15+ untested pages                    | 3-4 hours |

---

## IMPLEMENTATION PRIORITY ROADMAP

### Phase 1: Quick Wins (Week 1)

- [ ] QW-1: Add eager loading to all Livewire table components
- [ ] QW-2: Cache dashboard KPIs
- [ ] QW-3: Wrap controller store/update in DB::transaction()
- [ ] QW-4: Add leave balance calculation to Employee model
- [ ] QW-5: Cache report results
- [ ] QW-6: Add disposal journal creation in FixedAssetController::dispose()
- [ ] QW-7: Create RunMonthlyDepreciation artisan command
- [ ] QW-8: Add missing Livewire table tests

### Phase 2: Critical Accounting Fixes (Weeks 2-4)

- [ ] CF-1: Fix disposal GL posting (complete journal entries for asset disposal)
- [ ] CF-2: Make account codes tenant-configurable (TenantSetting-based lookup)
- [ ] CF-3: Implement bank reconciliation workflow (UI + matching logic + reports)
- [ ] CF-4: Add stock valuation methods (FIFO + Weighted Average with InventoryLayer model)
- [ ] CF-5: Queue payroll batch + heavy operations (app/Jobs/ directory)

### Phase 3: Procurement Module (Weeks 5-8)

- [ ] PM-1: Create PurchaseOrder model, migration, controller, views, API
- [ ] PM-2: Create GoodsReceivedNote model with inventory receipt logic
- [ ] PM-3: Link Bill to PO, implement 3-way matching validation
- [ ] PM-4: Add purchase requisition workflow
- [ ] PM-5: Debit notes for vendor returns
- [ ] PM-6: Update reports: PO status, GRN vs Bill variance

### Phase 4: Sales Module Enhancements (Weeks 9-11)

- [ ] SM-1: Create Quotation/Estimate model and workflow
- [ ] SM-2: Create DeliveryNote model (SO → Delivery → Invoice flow)
- [ ] SM-3: Credit notes that can be applied to future invoices
- [ ] SM-4: Discount engine (percentage, volume, promotional rules)
- [ ] SM-5: Partial fulfillment and backorder tracking on SalesOrder

### Phase 5: Inventory Upgrades (Weeks 12-14)

- [ ] IV-1: Create Warehouse model and multi-location inventory
- [ ] IV-2: Stock transfer between warehouses with GL impact
- [ ] IV-3: Serial number and batch/lot tracking
- [ ] IV-4: UOM conversion (purchase UOM vs sales UOM)
- [ ] IV-5: BOM (Bill of Materials) for assembly items
- [ ] IV-6: Auto-PO generation from reorder alerts

### Phase 6: HR & Payroll (Weeks 15-18)

- [ ] HR-1: Attendance/time tracking (clock in/out, timesheets)
- [ ] HR-2: Leave balance ledger with accrual tracking
- [ ] HR-3: Employee self-service portal (payslips, leave, personal info)
- [ ] HR-4: Statutory compliance templates (country-specific tax tables)
- [ ] HR-5: Bank file export (BACS, NACHA, EFT formats)
- [ ] HR-6: Loan/advance deduction scheduling

### Phase 7: Fixed Assets Completion (Weeks 19-20)

- [ ] FA-1: Asset revaluation workflow (IAS 16)
- [ ] FA-2: Impairment testing (IAS 36)
- [ ] FA-3: Units of Production depreciation method
- [ ] FA-4: Asset transfer between locations/departments
- [ ] FA-5: Asset maintenance/repair log

### Phase 8: Multi-Currency (Weeks 21-23)

- [ ] MC-1: Exchange rate table and auto-fetch from ECB/OpenExchangeRates
- [ ] MC-2: Multi-currency invoices and bills
- [ ] MC-3: Currency revaluation at period-end
- [ ] MC-4: Unrealized gain/loss journal entries
- [ ] MC-5: Multi-currency financial reports

### Phase 9: Reports & Compliance (Weeks 24-26)

- [ ] RP-1: Fixed asset reports (register, depreciation schedule, disposal)
- [ ] RP-2: HR reports (headcount, leave utilisation, cost per department)
- [ ] RP-3: Slow-moving/dead-stock inventory report
- [ ] RP-4: Report scheduling with email delivery
- [ ] RP-5: GDPR DSAR workflow
- [ ] RP-6: Consent management system

### Phase 10: Architecture & Performance (Ongoing)

- [ ] AP-1: Extract domain events (InvoiceFinalized, BillPaid, PayrollApproved, etc.)
- [ ] AP-2: Bind services to interfaces in service container
- [ ] AP-3: Comprehensive test coverage for all API controllers
- [ ] AP-4: Controller CRUD tests for Bills, Expenses, Vendors, Items, FixedAssets
- [ ] AP-5: Load testing suite with baseline benchmarks
