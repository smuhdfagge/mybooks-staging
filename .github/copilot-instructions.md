# MyBooks - AI Coding Instructions

## Project Overview

MyBooks is a multi-tenant SaaS accounting application built with **Laravel 12**, **Livewire 3**, **Alpine.js**, and **Tailwind CSS**. It implements double-entry bookkeeping with automatic journal entries, subscription-based access control, and role-based permissions.

## Architecture

### Multi-Tenancy Pattern

All tenant-scoped models use the `BelongsToTenant` trait ([app/Traits/BelongsToTenant.php](app/Traits/BelongsToTenant.php)):

-   Automatically sets `tenant_id` on creation from `auth()->user()->tenant_id`
-   Applies global scope to filter queries by tenant
-   **Critical:** Always use `withoutGlobalScopes()` when querying across tenants (e.g., generating unique numbers)

```php
// Correct pattern for cross-tenant queries
Invoice::withoutGlobalScopes()->where('tenant_id', $tenantId)->latest('id')->first();
```

### Double-Entry Accounting

The `JournalService` ([app/Services/JournalService.php](app/Services/JournalService.php)) handles automatic journal creation:

-   Invoices, Bills, Expenses, Payments trigger journal entries via model `saved` events
-   Account codes follow standard numbering: Assets (1xxx), Liabilities (2xxx), Equity (3xxx), Income (4xxx), Expenses (5xxx-6xxx)
-   Models use `ValidatesAccountingPeriod` trait to prevent transactions in closed periods

### Key Model Traits

| Trait                       | Purpose                         | Location                                                                             |
| --------------------------- | ------------------------------- | ------------------------------------------------------------------------------------ |
| `BelongsToTenant`           | Multi-tenancy isolation         | [app/Traits/BelongsToTenant.php](app/Traits/BelongsToTenant.php)                     |
| `LogsActivity`              | Audit trail for CRUD operations | [app/Traits/LogsActivity.php](app/Traits/LogsActivity.php)                           |
| `ValidatesAccountingPeriod` | Block changes in closed periods | [app/Traits/ValidatesAccountingPeriod.php](app/Traits/ValidatesAccountingPeriod.php) |

## File Structure Conventions

### Livewire Components

Organized by domain in `app/Livewire/{Domain}/`:

-   Table components (e.g., `InvoicesTable.php`) handle filtering, sorting, pagination, and bulk operations
-   Use `WithBulkOperations` trait ([app/Livewire/Traits/WithBulkOperations.php](app/Livewire/Traits/WithBulkOperations.php)) for bulk actions
-   Query string parameters persist filters: `protected $queryString = ['search', 'status', ...]`

### Controllers

-   Standard CRUD in `app/Http/Controllers/` - handle form validation, business logic, and view rendering
-   Admin panel controllers in `app/Http/Controllers/Admin/` - separate authentication system

### Views

-   Blade views in `resources/views/{domain}/` mirror controller structure
-   Use `<x-app-layout>` component for authenticated pages
-   Alpine.js for interactive forms (searchable dropdowns, dynamic line items)

## Development Commands

```bash
# Full dev environment (server + queue + logs + vite)
composer dev

# Run tests
composer test

# Initial setup
composer setup
```

## Middleware Stack

Routes apply middleware in this order:

1. `auth` - Authentication
2. `verified` - Email verification
3. `subscription` - Active subscription check ([app/Http/Middleware/CheckSubscription.php](app/Http/Middleware/CheckSubscription.php))
4. `permission:{name}` - Spatie permission check

## Subscription & Permissions

### Subscription Enforcement

-   `CheckSubscription` middleware blocks access without active subscription
-   Exempt routes: `profile.*`, `settings.subscription`, `logout`
-   Plans stored in `plans` table, active subscription in `subscriptions` table

### Permission Naming

Permissions follow `{action} {resource}` pattern:

```php
'view invoices', 'create invoices', 'edit invoices', 'delete invoices', 'send invoices'
```

## Key Patterns

### Inventory Management

-   `track_inventory` flag on Items determines if stock is tracked
-   Creating or editing an invoice checks free stock and reserves it (`Invoice::stockShortages()`, `Invoice::reserveInventory()`); web and API both use these
-   COGS is posted when the invoice is first posted (not on release). `StockValuationService::issue()` takes cost from the FIFO lots or the weighted average, and the cost is stored on each line as `unit_cost`, so later price changes don't rewrite past COGS
-   Lots used by a sale are recorded in `inventory_layer_consumptions`; cancelling, deleting or editing the document gives them back (`returnStock()`)
-   Release invoice → deducts the reserved quantity from on-hand stock (no new journal)
-   Cash sales (sales receipts) check stock and reduce on-hand at once
-   Bill lots are costed net of VAT

### Document Number Generation

Always use model's `generateNumber()` method with tenant context:

```php
$invoiceNumber = Invoice::generateNumber(auth()->user()->tenant_id);
```

### Tax Handling

-   Per-line-item tax support via `TaxRate` and `TaxGroup` models
-   Tenant settings: `prices_include_tax`, `tax_per_line_item`

## Database Conventions

-   All tenant tables have `tenant_id` foreign key with cascade delete
-   Use soft deletes (`SoftDeletes` trait) on financial documents
-   Decimal precision: `decimal(15, 2)` for monetary amounts

## Testing

-   Feature tests in `tests/Feature/`
-   Run `php artisan config:clear` before tests (handled by `composer test`)
