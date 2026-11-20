# MyBooks API Documentation

## Overview

The MyBooks API provides a RESTful interface for mobile applications to interact with the MyBooks accounting system. The API uses token-based authentication via Laravel Sanctum.

## Base URL

```
https://mybooks.cloud/api/v1
```

## Mobile app version

The app checks this public endpoint before opening. It does not require
authentication.

**Endpoint:** `GET /app/version`

The version policy is configured with `MYBOOKS_MOBILE_APP_MIN_VERSION`,
`MYBOOKS_MOBILE_APP_LATEST_VERSION`, `MYBOOKS_MOBILE_APP_IOS_STORE_URL` and
`MYBOOKS_MOBILE_APP_ANDROID_STORE_URL`. The staging defaults permit version
`0.1.0` and advertise `1.0.0` as an optional update; production should set the
minimum to its supported release.

**Response:**

```json
{
    "success": true,
    "message": "Success",
    "data": {
        "min_version": "0.1.0",
        "latest_version": "1.0.0",
        "ios_store_url": null,
        "android_store_url": null
    }
}
```

## Authentication

### Login

Authenticate a user and receive an access token.

**Endpoint:** `POST /auth/login`

**Request Body:**

```json
{
    "email": "user@example.com",
    "password": "your-password",
    "device_name": "Flutter Mobile App"
}
```

**Response:**

```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "user@example.com",
            "tenant": { ... },
            "roles": ["admin"],
            "permissions": ["view invoices", "create invoices", ...]
        },
        "token": "1|abcdefghijklmnop...",
        "token_type": "Bearer"
    }
}
```

### Using the Token

Include the token in the `Authorization` header for all authenticated requests:

```
Authorization: Bearer 1|abcdefghijklmnop...
```

### Logout

**Endpoint:** `POST /auth/logout`

Revokes the current token.

### Logout All Devices

**Endpoint:** `POST /auth/logout-all`

Revokes all tokens for the user.

### Get Current User

**Endpoint:** `GET /auth/user`

Returns the authenticated user's profile, roles and permissions, and
`modules`: which modules are switched on, so an app can hide what is off.
The same `modules` comes back in `user` on sign-in.

```json
"modules": {
    "assembly": true,
    "credit_notes": true,
    "quotations": true,
    "stock_transfers": true,
    "warehouses": true
}
```

(abridged: every module in `config/mybooks.php` → `features` is listed).
`stock_transfers` is only `true` when `warehouses` is on too.

### Update Profile

**Endpoint:** `PUT /auth/profile`

**Request Body:**

```json
{
    "name": "John Doe",
    "phone": "+1234567890"
}
```

### Update Password

**Endpoint:** `PUT /auth/password`

**Request Body:**

```json
{
    "current_password": "old-password",
    "password": "new-password",
    "password_confirmation": "new-password"
}
```

---

## Dashboard

### Get Dashboard Summary

**Endpoint:** `GET /dashboard`

Returns key metrics and counts.

**Response:**

```json
{
    "success": true,
    "data": {
        "revenue": {
            "total": 150000.0,
            "monthly": 25000.0
        },
        "outstanding": {
            "receivable": 35000.0,
            "payable": 15000.0
        },
        "counts": {
            "customers": 50,
            "employees": 10,
            "pending_invoices": 15,
            "pending_bills": 8,
            "low_stock_items": 5
        },
        "expenses": {
            "monthly": 12000.0
        }
    }
}
```

### Get Monthly Trends

**Endpoint:** `GET /dashboard/trends`

**Query Parameters:**

-   `year` (optional): Year for trends data (default: current year)

**Response:**

```json
{
    "success": true,
    "data": {
        "year": 2026,
        "trends": [
            {
                "month": "Jan",
                "month_number": 1,
                "revenue": 20000.00,
                "expenses": 8000.00,
                "profit": 12000.00
            },
            ...
        ]
    }
}
```

### Get Recent Transactions

**Endpoint:** `GET /dashboard/recent-transactions`

**Query Parameters:**

-   `limit` (optional): Number of records per type (default: 10)

---

## Reports

Financial reporting endpoints for generating key business reports.

### Profit & Loss Report

**Endpoint:** `GET /reports/profit-loss`

**Query Parameters:**

-   `start_date` (optional): Report start date (default: first day of current year)
-   `end_date` (optional): Report end date (default: today)

**Response:**

```json
{
    "success": true,
    "data": {
        "period": {
            "start_date": "2026-01-01",
            "end_date": "2026-12-31"
        },
        "income": {
            "total": 250000.00,
            "accounts": [
                {
                    "id": 1,
                    "code": "4000",
                    "name": "Sales Revenue",
                    "balance": 200000.00
                }
            ]
        },
        "expenses": {
            "total": 150000.00,
            "accounts": [...]
        },
        "cost_of_goods_sold": {
            "total": 50000.00,
            "accounts": [...]
        },
        "net_income": 50000.00,
        "gross_profit": 150000.00
    }
}
```

### Balance Sheet

**Endpoint:** `GET /reports/balance-sheet`

**Query Parameters:**

-   `as_of_date` (optional): Report date (default: today)

**Response:**

```json
{
    "success": true,
    "data": {
        "as_of_date": "2026-12-31",
        "assets": {
            "total": 500000.00,
            "accounts": [...]
        },
        "liabilities": {
            "total": 200000.00,
            "accounts": [...]
        },
        "equity": {
            "total": 300000.00,
            "accounts": [...]
        }
    }
}
```

### Cash Flow Statement

**Endpoint:** `GET /reports/cash-flow`

**Query Parameters:**

-   `start_date` (optional): Report start date
-   `end_date` (optional): Report end date

**Response:**

```json
{
    "success": true,
    "data": {
        "period": {
            "start_date": "2026-01-01",
            "end_date": "2026-12-31"
        },
        "operating": {
            "total": 75000.0,
            "details": {
                "net_income": 50000.0,
                "depreciation": 10000.0,
                "receivable_change": -5000.0,
                "payable_change": 3000.0,
                "inventory_change": -2000.0
            }
        },
        "investing": {
            "total": -25000.0,
            "details": {
                "fixed_assets": -30000.0,
                "asset_sales": 5000.0
            }
        },
        "financing": {
            "total": 10000.0,
            "details": {
                "equity_contributions": 15000.0,
                "distributions": -5000.0
            }
        },
        "net_cash_change": 60000.0,
        "beginning_cash": 100000.0,
        "ending_cash": 160000.0
    }
}
```

### Accounts Receivable Aging

**Endpoint:** `GET /reports/accounts-receivable`

**Query Parameters:**

-   `as_of_date` (optional): Aging date (default: today)

**Response:**

```json
{
    "success": true,
    "data": {
        "as_of_date": "2026-12-31",
        "summary": {
            "current": 25000.0,
            "30_days": 10000.0,
            "60_days": 5000.0,
            "90_days": 2000.0,
            "over_90_days": 1000.0,
            "total": 43000.0
        },
        "customers": [
            {
                "id": 1,
                "name": "ABC Company",
                "email": "abc@example.com",
                "current": 5000.0,
                "30_days": 2000.0,
                "60_days": 0.0,
                "90_days": 0.0,
                "over_90_days": 0.0,
                "total": 7000.0
            }
        ]
    }
}
```

### Accounts Payable Aging

**Endpoint:** `GET /reports/accounts-payable`

**Query Parameters:**

-   `as_of_date` (optional): Aging date (default: today)

### Sales Report

**Endpoint:** `GET /reports/sales`

**Query Parameters:**

-   `start_date` (optional): Report start date
-   `end_date` (optional): Report end date
-   `group_by` (optional): daily, weekly, monthly (default: monthly)

**Response:**

```json
{
    "success": true,
    "data": {
        "period": {
            "start_date": "2026-01-01",
            "end_date": "2026-12-31"
        },
        "summary": {
            "total_sales": 250000.00,
            "total_tax": 25000.00,
            "invoice_count": 150,
            "average_invoice": 1666.67
        },
        "by_period": [
            {
                "period": "2026-01",
                "sales": 20000.00,
                "tax": 2000.00,
                "invoice_count": 12
            }
        ],
        "top_customers": [...],
        "top_items": [...]
    }
}
```

### Tax Summary Report

**Endpoint:** `GET /reports/tax-summary`

**Query Parameters:**

-   `start_date` (optional): Report start date
-   `end_date` (optional): Report end date

**Response:**

```json
{
    "success": true,
    "data": {
        "period": {
            "start_date": "2026-01-01",
            "end_date": "2026-12-31"
        },
        "tax_collected": 25000.0,
        "tax_paid": 15000.0,
        "net_tax_liability": 10000.0,
        "by_rate": [
            {
                "rate_name": "VAT",
                "rate_percentage": 10.0,
                "tax_collected": 20000.0,
                "tax_paid": 12000.0,
                "net": 8000.0
            }
        ]
    }
}
```

### Trial Balance

**Endpoint:** `GET /reports/trial-balance`

**Query Parameters:**

-   `as_of_date` (optional): Trial balance date (default: today)

**Response:**

```json
{
    "success": true,
    "data": {
        "as_of_date": "2026-12-31",
        "accounts": [
            {
                "id": 1,
                "code": "1000",
                "name": "Cash",
                "type": "asset",
                "debit": 50000.0,
                "credit": 0.0
            }
        ],
        "totals": {
            "debit": 500000.0,
            "credit": 500000.0
        }
    }
}
```

---

## Search & Lookup

Endpoints for searching entities and providing autocomplete functionality.

### Global Search

**Endpoint:** `GET /search`

**Query Parameters:**

-   `q` (required): Search query (minimum 2 characters)
-   `types` (optional): Comma-separated types to search (customers,vendors,items,invoices,bills)
-   `limit` (optional): Results per type (default: 5)

**Response:**

```json
{
    "success": true,
    "data": {
        "query": "john",
        "results": {
            "customers": [
                {
                    "id": 1,
                    "name": "John Doe",
                    "email": "john@example.com",
                    "company_name": "ABC Corp"
                }
            ],
            "vendors": [...],
            "items": [...],
            "invoices": [...],
            "bills": [...]
        }
    }
}
```

### Customer Lookup

**Endpoint:** `GET /search/customers`

**Query Parameters:**

-   `q` (optional): Search query
-   `limit` (optional): Max results (default: 10)

Returns simplified customer data for autocomplete dropdowns.

### Vendor Lookup

**Endpoint:** `GET /search/vendors`

**Query Parameters:**

-   `q` (optional): Search query
-   `limit` (optional): Max results (default: 10)

### Item Lookup

**Endpoint:** `GET /search/items`

**Query Parameters:**

-   `q` (optional): Search query
-   `type` (optional): Filter by type (product/service)
-   `limit` (optional): Max results (default: 10)

**Response:**

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Widget A",
            "sku": "WID-001",
            "type": "product",
            "selling_price": 99.99,
            "tax_rate": 10.0,
            "track_inventory": true,
            "quantity_on_hand": 50
        }
    ]
}
```

### Account Lookup

**Endpoint:** `GET /search/accounts`

**Query Parameters:**

-   `q` (optional): Search query
-   `type` (optional): Filter by type (asset/liability/equity/income/expense)
-   `limit` (optional): Max results (default: 10)

---

## Settings

Endpoints for managing tenant settings.

### Get All Settings

**Endpoint:** `GET /settings`

**Response:**

```json
{
    "success": true,
    "data": {
        "organization": {
            "name": "My Company",
            "email": "info@company.com",
            "phone": "+1234567890",
            "address": "123 Main St",
            "city": "New York",
            "country": "US",
            "currency": "USD",
            "logo_url": "https://..."
        },
        "accounting": {
            "fiscal_year_start": "01-01",
            "default_payment_terms": 30,
            "default_income_account_id": 4000,
            "default_expense_account_id": 5000,
            "default_receivable_account_id": 1100,
            "default_payable_account_id": 2000
        },
        "tax": {
            "tax_registration_number": "TAX123",
            "default_tax_rate_id": 1,
            "prices_include_tax": false,
            "tax_per_line_item": true
        },
        "custom": {}
    }
}
```

### Update Organization Settings

**Endpoint:** `PUT /settings/organization`

**Request Body:**

```json
{
    "name": "My Company",
    "email": "info@company.com",
    "phone": "+1234567890",
    "address": "123 Main St",
    "city": "New York",
    "state": "NY",
    "country": "US",
    "postal_code": "10001",
    "currency": "USD"
}
```

### Update Accounting Settings

**Endpoint:** `PUT /settings/accounting`

**Request Body:**

```json
{
    "fiscal_year_start": "01-01",
    "default_payment_terms": 30,
    "default_income_account_id": 4000,
    "default_expense_account_id": 5000
}
```

### Update Tax Settings

**Endpoint:** `PUT /settings/tax`

**Request Body:**

```json
{
    "tax_registration_number": "TAX123",
    "default_tax_rate_id": 1,
    "prices_include_tax": false,
    "tax_per_line_item": true
}
```

### Update Custom Settings

**Endpoint:** `PUT /settings/custom`

**Request Body:**

```json
{
    "custom_field_1": "value1",
    "custom_field_2": "value2"
}
```

### Get Available Currencies

**Endpoint:** `GET /settings/currencies`

Returns list of supported currencies for selection.

### Get Payment Methods

**Endpoint:** `GET /settings/payment-methods`

Returns list of available payment methods.

---

## Journals

Manual journal entry management.

### List Journals

**Endpoint:** `GET /journals`

**Query Parameters:**

-   `search` - Search by journal number, reference, or description
-   `status` - Filter by status (draft/posted/reversed)
-   `from_date` - Filter by date range start
-   `to_date` - Filter by date range end
-   `sort_by` - Sort field (default: journal_date)
-   `sort_order` - asc/desc (default: desc)
-   `per_page` - Items per page

### Get Journal

**Endpoint:** `GET /journals/{id}`

### Create Journal

**Endpoint:** `POST /journals`

**Request Body:**

```json
{
    "journal_date": "2026-01-15",
    "reference": "ADJ-001",
    "description": "Adjusting entry for depreciation",
    "entries": [
        {
            "account_id": 5100,
            "description": "Depreciation expense",
            "debit": 1000.0,
            "credit": 0.0
        },
        {
            "account_id": 1800,
            "description": "Accumulated depreciation",
            "debit": 0.0,
            "credit": 1000.0
        }
    ]
}
```

Note: Total debits must equal total credits.

### Update Journal

**Endpoint:** `PUT /journals/{id}`

Only draft journals can be updated.

### Delete Journal

**Endpoint:** `DELETE /journals/{id}`

Only draft journals can be deleted.

### Post Journal

**Endpoint:** `POST /journals/{id}/post`

Posts a draft journal entry, making it permanent.

### Reverse Journal

**Endpoint:** `POST /journals/{id}/reverse`

**Request Body:**

```json
{
    "reversal_date": "2026-01-31",
    "description": "Reversal of ADJ-001"
}
```

Creates a reversing entry for a posted journal.

### Get Journal Summary

**Endpoint:** `GET /journals/summary`

Returns journal statistics.

---

## Customers

### List Customers

**Endpoint:** `GET /customers`

**Query Parameters:**

-   `search` - Search by name, email, phone, or company
-   `is_active` - Filter by active status (true/false)
-   `sort_by` - Sort field (default: name)
-   `sort_order` - asc/desc (default: asc)
-   `per_page` - Items per page (default: 15)

### Get Customer

**Endpoint:** `GET /customers/{id}`

### Create Customer

**Endpoint:** `POST /customers`

**Request Body:**

```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "company_name": "ABC Corp",
    "tax_number": "TAX123",
    "billing_address": "123 Main St",
    "shipping_address": "456 Oak Ave",
    "city": "New York",
    "state": "NY",
    "country": "USA",
    "postal_code": "10001",
    "credit_limit": 50000.0,
    "payment_terms": 30,
    "notes": "VIP customer",
    "is_active": true
}
```

### Update Customer

**Endpoint:** `PUT /customers/{id}`

### Delete Customer

**Endpoint:** `DELETE /customers/{id}`

### Get Customer Statistics

**Endpoint:** `GET /customers/{id}/statistics`

---

## Vendors

### List Vendors

**Endpoint:** `GET /vendors`

**Query Parameters:**

-   `search` - Search by name, email, phone, or company
-   `is_active` - Filter by active status
-   `sort_by` - Sort field
-   `sort_order` - asc/desc
-   `per_page` - Items per page

### Get Vendor

**Endpoint:** `GET /vendors/{id}`

### Create Vendor

**Endpoint:** `POST /vendors`

### Update Vendor

**Endpoint:** `PUT /vendors/{id}`

### Delete Vendor

**Endpoint:** `DELETE /vendors/{id}`

### Get Vendor Statistics

**Endpoint:** `GET /vendors/{id}/statistics`

---

## Items

### List Items

**Endpoint:** `GET /items`

**Query Parameters:**

-   `search` - Search by name, SKU, or description
-   `type` - Filter by type (product/service)
-   `category_id` - Filter by category
-   `is_active` - Filter by active status
-   `track_inventory` - Filter by inventory tracking
-   `sort_by` - Sort field
-   `sort_order` - asc/desc
-   `per_page` - Items per page

### Get Item

**Endpoint:** `GET /items/{id}`

### Create Item

**Endpoint:** `POST /items`

**Request Body:**

```json
{
    "name": "Widget A",
    "sku": "WID-001",
    "description": "A great widget",
    "type": "product",
    "unit": "pcs",
    "category_id": 1,
    "selling_price": 99.99,
    "cost_price": 50.0,
    "tax_rate": 10.0,
    "is_taxable": true,
    "track_inventory": true,
    "reorder_level": 10,
    "is_active": true
}
```

### Update Item

**Endpoint:** `PUT /items/{id}`

### Delete Item

**Endpoint:** `DELETE /items/{id}`

---

## Invoices

### List Invoices

**Endpoint:** `GET /invoices`

**Query Parameters:**

-   `search` - Search by invoice number, reference, or customer name
-   `status` - Filter by status (draft/unpaid/partial/paid/overdue/cancelled)
-   `customer_id` - Filter by customer
-   `from_date` - Filter by date range start
-   `to_date` - Filter by date range end
-   `overdue` - Show only overdue invoices (true/false)
-   `sort_by` - Sort field (default: created_at)
-   `sort_order` - asc/desc (default: desc)
-   `per_page` - Items per page

### Get Invoice

**Endpoint:** `GET /invoices/{id}`

### Create Invoice

**Endpoint:** `POST /invoices`

**Request Body:**

```json
{
    "customer_id": 1,
    "reference": "PO-12345",
    "invoice_date": "2026-01-15",
    "due_date": "2026-02-15",
    "status": "draft",
    "discount_amount": 100.0,
    "discount_type": "fixed",
    "notes": "Thank you for your business",
    "terms": "Net 30",
    "items": [
        {
            "item_id": 1,
            "description": "Widget A",
            "quantity": 5,
            "unit_price": 99.99,
            "discount": 0,
            "tax_rate": 10.0
        }
    ]
}
```

### Update Invoice

**Endpoint:** `PUT /invoices/{id}`

### Delete Invoice

**Endpoint:** `DELETE /invoices/{id}`

### Get Invoice Summary

**Endpoint:** `GET /invoices/summary`

### Send Invoice

**Endpoint:** `POST /invoices/{id}/send`

Sends the invoice to the customer via email.

**Request Body (optional):**

```json
{
    "message": "Please find your invoice attached.",
    "cc": ["accounts@company.com"]
}
```

**Response:**

```json
{
    "success": true,
    "message": "Invoice sent successfully",
    "data": {
        "invoice_number": "INV-00001",
        "sent_to": "customer@example.com",
        "sent_at": "2026-01-15T10:30:00Z"
    }
}
```

### Release Invoice (Deduct Inventory)

**Endpoint:** `POST /invoices/{id}/release`

Releases the invoice for delivery, deducting inventory for product items and generating a waybill number.

**Request Body (optional):**

```json
{
    "notes": "Driver: John, Vehicle: ABC-123"
}
```

**Response:**

```json
{
    "success": true,
    "message": "Invoice released successfully",
    "data": {
        "invoice_number": "INV-00001",
        "waybill_number": "WB-00001",
        "released_at": "2026-01-15T14:00:00Z",
        "items_released": 5
    }
}
```

### Download Invoice PDF

**Endpoint:** `GET /invoices/{id}/pdf`

Returns the invoice as a PDF file download.

**Response Headers:**

```
Content-Type: application/pdf
Content-Disposition: attachment; filename="INV-00001.pdf"
```

### Update Invoice Status

**Endpoint:** `PUT /invoices/{id}/status`

**Request Body:**

```json
{
    "status": "unpaid"
}
```

Allowed changes:

- `unpaid`: issue a draft invoice (posts it to the ledger).
- `cancelled`: cancel a draft, sent, unpaid or overdue invoice that has no payments or credits applied. The ledger entry is reversed and reserved stock is released.

Anything else returns 422. `paid`, `partial` and `overdue` are set by payments and due dates, not by this endpoint.

---

## Bills

### List Bills

**Endpoint:** `GET /bills`

### Get Bill

**Endpoint:** `GET /bills/{id}`

### Create Bill

**Endpoint:** `POST /bills`

### Update Bill

**Endpoint:** `PUT /bills/{id}`

### Delete Bill

**Endpoint:** `DELETE /bills/{id}`

### Get Bill Summary

**Endpoint:** `GET /bills/summary`

---

## Expenses

### List Expenses

**Endpoint:** `GET /expenses`

### Get Expense

**Endpoint:** `GET /expenses/{id}`

### Create Expense

**Endpoint:** `POST /expenses`

**Request Body:**

```json
{
    "vendor_id": 1,
    "expense_account_id": 5001,
    "bank_id": 1,
    "name": "Office Supplies",
    "expense_date": "2026-01-15",
    "amount": 250.0,
    "tax_amount": 25.0,
    "payment_method": "credit_card",
    "reference": "REC-12345",
    "description": "Monthly office supplies",
    "notes": "Staples, paper, pens",
    "status": "draft"
}
```

### Update Expense

**Endpoint:** `PUT /expenses/{id}`

### Delete Expense

**Endpoint:** `DELETE /expenses/{id}`

### Submit Expense for Approval

**Endpoint:** `POST /expenses/{id}/submit`

### Approve Expense

**Endpoint:** `POST /expenses/{id}/approve`

Needs the admin role, and the approver can't be the person who raised the expense (403 otherwise). Rejecting also needs the admin role. New expenses can only be created as `draft` or `pending_approval`.

### Reject Expense

**Endpoint:** `POST /expenses/{id}/reject`

**Request Body:**

```json
{
    "rejection_reason": "Missing receipt"
}
```

### Get Expense Summary

**Endpoint:** `GET /expenses/summary`

### Expense Receipts

Photos or PDFs attached to an expense. Files are private: download them
through the API, signed in.

| Method | Endpoint | Permission |
|---|---|---|
| `GET` | `/expenses/{id}/attachments` | view expenses |
| `POST` | `/expenses/{id}/attachments` | edit expenses |
| `GET` | `/expenses/{id}/attachments/{attachment_id}` (the file) | view expenses |
| `DELETE` | `/expenses/{id}/attachments/{attachment_id}` | edit expenses |

Upload as `multipart/form-data` with one field, `file`: JPG, PNG, WebP or
PDF, at most 5 MB, at most 10 receipts per expense (`422` otherwise).
Send an `Idempotency-Key` so a retried upload is not added twice; the key
covers the file's content.

**Response (upload):**

```json
{
    "success": true,
    "message": "Receipt added.",
    "data": {
        "id": 12,
        "expense_id": 311,
        "file_name": "fuel receipt.jpg",
        "mime_type": "image/jpeg",
        "size": 482113,
        "uploaded_by": { "id": 4, "name": "Musa Ibrahim" },
        "created_at": "2026-11-19T09:12:00.000000Z",
        "download_url": "https://your-domain.com/api/v1/expenses/311/attachments/12"
    }
}
```

Deleting an expense keeps its receipts until the expense is deleted for
good.

---

## Payments Received

### List Payments Received

**Endpoint:** `GET /payments-received`

**Query Parameters:**

-   `search` - Search by payment number, reference, or customer name
-   `customer_id` - Filter by customer
-   `invoice_id` - Filter by invoice
-   `is_deposit` - Filter deposits only
-   `payment_method` - Filter by payment method
-   `from_date` - Filter by date range start
-   `to_date` - Filter by date range end

### Get Payment Received

**Endpoint:** `GET /payments-received/{id}`

### Create Payment Received

**Endpoint:** `POST /payments-received`

**Request Body:**

```json
{
    "customer_id": 1,
    "invoice_id": 5,
    "payment_date": "2026-01-15",
    "amount": 500.0,
    "payment_method": "bank_transfer",
    "bank_id": 1,
    "reference": "TXN-12345",
    "notes": "Partial payment",
    "is_deposit": false
}
```

### Delete Payment Received

**Endpoint:** `DELETE /payments-received/{id}`

### Get Payment Summary

**Endpoint:** `GET /payments-received/summary`

---

## Payments Made

### List Payments Made

**Endpoint:** `GET /payments-made`

### Get Payment Made

**Endpoint:** `GET /payments-made/{id}`

### Create Payment Made

**Endpoint:** `POST /payments-made`

### Delete Payment Made

**Endpoint:** `DELETE /payments-made/{id}`

### Get Payment Summary

**Endpoint:** `GET /payments-made/summary`

---

## Inventory

### List Inventory

**Endpoint:** `GET /inventory`

**Query Parameters:**

-   `search` - Search by item name or SKU
-   `low_stock` - Show only low stock items (true/false)
-   `category_id` - Filter by category

### Get Inventory Item

**Endpoint:** `GET /inventory/{id}`

### Adjust Inventory

**Endpoint:** `POST /inventory/{id}/adjust`

**Request Body:**

```json
{
    "quantity": 10,
    "type": "add",
    "reason": "Stock received from supplier",
    "reference": "PO-12345"
}
```

Type can be: `add`, `subtract`, or `set`

### Get Inventory History

**Endpoint:** `GET /inventory/{id}/history`

### Get Inventory Summary

**Endpoint:** `GET /inventory/summary`

---

## Stock Transfers

Only when the warehouses and stock transfers modules are on (`404`
otherwise). Reading needs `view inventory`; everything else
`adjust inventory`.

| Method | Endpoint | What it does |
|---|---|---|
| `GET` | `/stock-transfers` | List (`status`: draft, in_transit, received, cancelled; `warehouse_id`) |
| `GET` | `/stock-transfers/{id}` | One transfer with its lines |
| `POST` | `/stock-transfers` | Create a draft; add `"ship": true` to ship it, or `"receive": true` to ship and receive at once |
| `POST` | `/stock-transfers/{id}/ship` | Ship a draft: the goods leave the source warehouse |
| `POST` | `/stock-transfers/{id}/receive` | Receive a shipped transfer |

**Receive request body** (all optional; a line not listed is received in full):

```json
{
    "received_date": "2026-11-19",
    "shortfall": "return",
    "items": [
        { "id": 55, "quantity_received": 3 }
    ]
}
```

`shortfall`: what happened to goods that did not arrive. `return` (the
default) puts them back in the source warehouse; `lost` writes them off to
Stock Losses. `id` is the transfer line's id from `items[].id`. Shipping
more than is free, shipping twice, or receiving a draft returns `422`.

---

## Assembly

Only when the assembly module is on (`404` otherwise). Reading needs
`view inventory` (bills of materials: `view items`); creating and
completing need `adjust inventory`.

| Method | Endpoint | What it does |
|---|---|---|
| `GET` | `/bill-of-materials` | Bills of materials with components and extra costs |
| `GET` | `/assembly-orders` | List (`status`, `kind`: build or breakdown) |
| `GET` | `/assembly-orders/{id}` | One order |
| `POST` | `/assembly-orders` | Create a draft; add `"complete": true` (and optionally `quantity_made`) to complete it at once |
| `POST` | `/assembly-orders/{id}/complete` | Complete a saved draft |

**Complete request body** (all optional, each defaults to the plan):

```json
{
    "quantity_made": 39,
    "items": [{ "id": 101, "quantity": 690 }],
    "costs": [{ "id": 7, "amount": 21000 }]
}
```

`items[].id` and `costs[].id` are the order's own line ids. Not enough free
stock, or completing an order that is not a draft, returns `422`.

---

## Chart of Accounts

### List Accounts

**Endpoint:** `GET /accounts`

**Query Parameters:**

-   `search` - Search by code or name
-   `type` - Filter by type (asset/liability/equity/income/expense)
-   `sub_type` - Filter by sub-type
-   `is_active` - Filter by active status
-   `root_only` - Show only root accounts

### Get Account

**Endpoint:** `GET /accounts/{id}`

### Create Account

**Endpoint:** `POST /accounts`

### Update Account

**Endpoint:** `PUT /accounts/{id}`

### Delete Account

**Endpoint:** `DELETE /accounts/{id}`

### Get Account Types

**Endpoint:** `GET /accounts/types`

### Get Balance Summary

**Endpoint:** `GET /accounts/balance-summary`

---

## Banks

### List Banks

**Endpoint:** `GET /banks`

### Get Bank

**Endpoint:** `GET /banks/{id}`

### Create Bank

**Endpoint:** `POST /banks`

### Update Bank

**Endpoint:** `PUT /banks/{id}`

### Delete Bank

**Endpoint:** `DELETE /banks/{id}`

### Get Bank Types

**Endpoint:** `GET /banks/types`

### Get Bank Summary

**Endpoint:** `GET /banks/summary`

---

## Sales Orders

### List Sales Orders

**Endpoint:** `GET /sales-orders`

### Get Sales Order

**Endpoint:** `GET /sales-orders/{id}`

### Create Sales Order

**Endpoint:** `POST /sales-orders`

### Update Sales Order

**Endpoint:** `PUT /sales-orders/{id}`

### Delete Sales Order

**Endpoint:** `DELETE /sales-orders/{id}`

### Get Sales Order Summary

**Endpoint:** `GET /sales-orders/summary`

---

## Employees

### List Employees

**Endpoint:** `GET /employees`

### Get Employee

**Endpoint:** `GET /employees/{id}`

### Create Employee

**Endpoint:** `POST /employees`

### Update Employee

**Endpoint:** `PUT /employees/{id}`

### Delete Employee

**Endpoint:** `DELETE /employees/{id}`

### Get Employee Summary

**Endpoint:** `GET /employees/summary`

---

## Tax Rates

### List Tax Rates

**Endpoint:** `GET /tax-rates`

### Get Tax Rate

**Endpoint:** `GET /tax-rates/{id}`

### Create Tax Rate

**Endpoint:** `POST /tax-rates`

### Update Tax Rate

**Endpoint:** `PUT /tax-rates/{id}`

### Delete Tax Rate

**Endpoint:** `DELETE /tax-rates/{id}`

---

## Mobile App Version

**Endpoint:** `GET /app/version` (public, no token)

The app calls this at start. Below `min_version` it must be updated before
use; below `latest_version` it may offer the update. Store links are
`null` until set.

```json
{
    "success": true,
    "message": "Success",
    "data": {
        "min_version": "1.0.0",
        "latest_version": "1.0.0",
        "ios_store_url": null,
        "android_store_url": null
    }
}
```

Set in `.env`: `MYBOOKS_APP_MIN_VERSION`, `MYBOOKS_APP_LATEST_VERSION`,
`MYBOOKS_APP_IOS_URL`, `MYBOOKS_APP_ANDROID_URL` (then `php artisan optimize`).

---

## Push Notifications

### Register a Device

**Endpoint:** `POST /devices` (signed in; no extra permission)

Call after signing in, and again whenever Firebase gives the app a new
token. The device belongs to the signed-in user and to this API token:
signing out (`/auth/logout`) forgets it. A token registered again by
another user moves to that user.

```json
{
    "token": "<Firebase registration token>",
    "platform": "android",
    "app_version": "1.0.0"
}
```

`platform`: `ios` or `android`. `201` the first time, `200` after.

### Remove a Device

**Endpoint:** `DELETE /devices/{token}`

For when the user turns notifications off. Only the user's own device is
removed; an unknown token still returns `200`.

### What Is Sent

| `type` | Sent to | When |
|---|---|---|
| `expense_awaiting_approval` | Admins who could approve it (not the person who raised it) | An expense is submitted for approval |
| `invoice_paid` | Users with `view invoices` | An invoice becomes paid in full |
| `invoice_overdue` | Users with `view invoices` | 08:00 Lagos, the day after the due date |
| `bill_due` | Users with `view bills` | 08:00 Lagos, the day before the due date |

Only active users of the same business with a registered device get a
push. Each push carries a title and body to show, and `data` for the app
to open the right screen (values are strings):

```json
{ "type": "invoice_paid", "id": "142" }
```

`id` is the expense, invoice or bill id: open `GET /expenses/{id}`,
`GET /invoices/{id}` or `GET /bills/{id}`. A token Firebase reports as
no longer valid is removed. Nothing is sent until the server has
`FCM_CREDENTIALS` (the path to the Firebase service account key file)
in `.env`.

---

## Error Handling

All API responses follow a consistent format:

### Success Response

```json
{
    "success": true,
    "message": "Success message",
    "data": { ... }
}
```

### Error Response

```json
{
    "success": false,
    "message": "Error message",
    "errors": { ... }
}
```

### Common HTTP Status Codes

| Code | Description      |
| ---- | ---------------- |
| 200  | Success          |
| 201  | Created          |
| 400  | Bad Request      |
| 401  | Unauthorized     |
| 403  | Forbidden        |
| 404  | Not Found        |
| 422  | Validation Error |
| 500  | Server Error     |

---

## Pagination

Paginated responses include metadata:

```json
{
    "success": true,
    "message": "Success",
    "data": [ ... ],
    "meta": {
        "current_page": 1,
        "last_page": 10,
        "per_page": 15,
        "total": 150,
        "from": 1,
        "to": 15
    },
    "links": {
        "first": "https://api.example.com/v1/customers?page=1",
        "last": "https://api.example.com/v1/customers?page=10",
        "prev": null,
        "next": "https://api.example.com/v1/customers?page=2"
    }
}
```

---

## Rate Limiting

API requests are rate-limited to prevent abuse and ensure fair usage:

| Endpoint Type                                   | Rate Limit   | Window   | Counted per    |
| ----------------------------------------------- | ------------ | -------- | -------------- |
| Login, forgot/reset password                    | 5 requests   | 1 minute | IP address     |
| Reads (`GET`) when signed in                    | 120 requests | 1 minute | user           |
| Writes (`POST`/`PUT`/`DELETE`) when signed in   | 30 requests  | 1 minute | user           |
| Reports (`/reports/*`)                          | 30 requests  | 1 minute | user           |
| Invoice PDF                                     | 10 requests  | 1 minute | user           |
| Health check                                    | 120 requests | 1 minute | IP address     |

Rate limit headers are included in responses:

-   `X-RateLimit-Limit`: Maximum requests per minute
-   `X-RateLimit-Remaining`: Remaining requests
-   `X-RateLimit-Reset`: Unix timestamp when limit resets

When rate limited, you'll receive a `429 Too Many Requests` response:

```json
{
    "success": false,
    "message": "Too Many Attempts."
}
```

The `Retry-After` header says how many seconds to wait.

---

## Idempotency-Key

Any `POST`, `PUT` or `DELETE` may send an `Idempotency-Key` header (any unique string up to 255 characters, e.g. a UUID). If the request succeeds, its response is kept for 24 hours. Sending the same request again with the same key returns that stored response (with the header `Idempotent-Replayed: true`) instead of creating a second invoice, payment or journal. Use this when retrying after a time-out.

-   Reusing a key for a different request gets `422`.
-   A retry while the first request is still running gets `409`; wait and retry.
-   Failed requests are not kept, so the key can be reused after fixing the request.
-   Keys belong to the signed-in user.

---

## Flutter Integration Example

```dart
import 'dart:convert';
import 'package:http/http.dart' as http;

class MyBooksApi {
  static const String baseUrl = 'https://your-domain.com/api/v1';
  String? _token;

  Future<Map<String, dynamic>> login(String email, String password) async {
    final response = await http.post(
      Uri.parse('$baseUrl/auth/login'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({
        'email': email,
        'password': password,
        'device_name': 'Flutter App',
      }),
    );

    final data = jsonDecode(response.body);
    if (data['success']) {
      _token = data['data']['token'];
    }
    return data;
  }

  Future<Map<String, dynamic>> getDashboard() async {
    final response = await http.get(
      Uri.parse('$baseUrl/dashboard'),
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $_token',
      },
    );
    return jsonDecode(response.body);
  }

  Future<Map<String, dynamic>> getCustomers({int page = 1}) async {
    final response = await http.get(
      Uri.parse('$baseUrl/customers?page=$page'),
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $_token',
      },
    );
    return jsonDecode(response.body);
  }

  Future<Map<String, dynamic>> createInvoice(Map<String, dynamic> invoice) async {
    final response = await http.post(
      Uri.parse('$baseUrl/invoices'),
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $_token',
      },
      body: jsonEncode(invoice),
    );
    return jsonDecode(response.body);
  }
}
```

---

## Support

For API support or to report issues, please contact:

-   Email: support@mybooks.cloud
-   Documentation: https://mybooks.cloud/docs
