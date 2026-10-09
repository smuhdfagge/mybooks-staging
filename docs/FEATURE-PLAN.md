# Phase F feature plan: one feature per session

Each session builds one feature from start to finish: code, tests on
SQLite and MariaDB, a browser check, its own pull request, and CI green.
Merge each pull request before starting the next session, so every session
starts from an up-to-date `main`.

To start a session, say: **"Do session N of docs/FEATURE-PLAN.md"**.

## Status

| # | Feature | Branch | State |
|---|---------|--------|-------|
| 1 | Payroll statutory remittances: PAYE by state, pension by PFA, NHF, NSITF, ITF | `feature/payroll-remittances` | Merged (#17) |
| 2 | Supplier credits, purchase returns, supplier advances | `feature/supplier-credits` | Merged (#18) |
| 3 | Quotations: screens, email with PDF, expiry, convert | `feature/quotations` | Merged (#19) |
| 4 | Delivery notes: partial deliveries, printable note | `feature/delivery-notes` | Merged (#23) |
| 5 | Withholding tax on sales and purchases, WHT credits, schedules | `feature/withholding-tax` | Merged (#24) |
| 6 | VAT return in the NRS format: standard, zero-rated and exempt supplies | `feature/vat-return` | Merged (#25) |
| 7 | Customer credit notes, with returned goods back into stock (A15) | `feature/customer-credit-notes` | Merged (#28) |
| 8 | Auto-reversing journals (accruals) | `feature/auto-reversing-journals` | Merged (#29) |
| 9 | Prepaid expense and deferred revenue schedules | `feature/prepaid-deferred-schedules` | Merged (#30) |
| 10 | Customer and supplier statements (PDF, email, bulk send) and AR/AP control reconciliation | `feature/statements-reconciliation` | Merged (#31) |
| 11 | Audit lock dates: staff and adviser locks, logged reopen with reason | `feature/audit-lock-dates` | Merged (#33) |
| 12 | Warehouses: stock per location, default warehouse for existing stock | `feature/warehouses` | Merged (#34) |
| 13 | Stock transfers between warehouses | `feature/stock-transfers` | Merged (#35) |
| 14 | Assembly / bill of materials | `feature/assembly` | Merged (#36) |
| — | Follow-up: journals for stock adjustments, opening stock, bulk asset disposal | `fix/stock-and-disposal-journals` | Merged (#37) |
| 15 | Paystack auto-renewal: saved card, automatic renewal charge, retries, webhooks | `feature/paystack-auto-renewal` | Done, PR open |
| 16 | SMS and WhatsApp reminders (Termii): overdue invoices, payment receipts, opt-out | `feature/sms-whatsapp-reminders` | Done, PR open (#39, needs #38 first) |
| 17 | Bank feeds with Mono: link accounts, pull transactions, match in bank reconciliation | `feature/bank-feeds` | Done, PR open (#40, needs #39 first) |
| 18 | NRS e-invoicing (MBS): TIN, IRN, QR code, submit invoices and credit notes | `feature/e-invoicing` | Done, PR open (needs #40 first) |

`wip/payroll-remit-alt` is an earlier duplicate of item 1; ignore it and delete it once #17 merges.

Sessions 15 to 18 are stacked: each branch starts from the one before,
so merge their PRs in order. Okra shut down in May 2025, so bank feeds
use Mono only, behind a driver so another provider can be added later.

Later, not in this batch: multi-currency.

## Rules every session follows

- Branch `feature/<name>` from the latest `main`. For a started feature,
  bring over only that feature's files from its `wip/...` branch, finish
  it, and delete the WIP branch after its pull request merges.
- Every document that touches the books posts a balanced journal through
  `JournalService`; deleting or cancelling posts a reversal. Use
  `App\Support\Money`, `DocumentTotals`, the Actions pattern, status
  enums with `GuardsStatusTransitions`, `HasDocumentNumber`.
- Every new table has `tenant_id` and `BelongsToTenant`, with a test that
  another business can't see or change the records.
- New permissions come in a migration, granted to the admin role.
- Rates from Nigerian law (Nigeria Tax Act 2025, NRS rules) are editable
  defaults with the source and date checked in a comment.
- Tests in `tests/Feature/Features/`. Before the PR: full suite on SQLite
  and MariaDB, Pint run twice, PHPStan with no errors, and a click-through
  in headless Chromium.
- If the screens or scripts change, attach a fresh `public/build` zip for
  the server (Hostinger has no Node).
