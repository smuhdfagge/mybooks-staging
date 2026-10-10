# MyBooks tables plan

The full plan, with the sample design, is "MyBooks-Tables-Plan" (Word/PDF).
This file is the working copy: the rules, the shared pieces, and the status
of each session.

## Decisions (10 Oct 2026)

Row actions in one "⋯" menu; currency once ("Amounts in ₦") with plain
figures in cells (phone cards keep ₦); 25 rows per page; status tabs with
counts; brackets for negatives on financial statements; totals row on lists.

## The rules

- Page header: title, one line about the list ending "Amounts in ₦", the
  main button (`btn-new`), everything else under "More".
- Status tabs with counts instead of a Status dropdown.
- One toolbar row: search, two or three filters (`x-table.select`), Export.
  On a phone the filters fold behind "Filters".
- Headers in sentence case on a light band; one text size, one row height.
- First column: the record number or name as a link (`tbl-link`).
- Dates "10 Oct 2026"; overdue dates `tbl-late`.
- Amounts right-aligned with even-width digits (`num`), two decimals, zero
  as a muted dash (`tbl-zero`).
- Status: `<x-status-badge>`.
- One "⋯" menu per row, last column; Delete last, in red, after a line.
- Ticking rows turns the toolbar into the bulk bar.
- Totals row for money columns (everything the filter matches), then
  `<x-table.footer>`: range, rows per page, pages.
- Empty: `<x-table.empty>` (nothing yet, or `filtered` with Clear filters).
- Phones: `<x-table.card>` per row instead of the table.

## The shared pieces

| Piece | Use |
|---|---|
| `<x-table>` | Frame: header band, rows, totals (`foot` slot) |
| `<x-table.th>` | Header; `field` makes it sortable, `num` right-aligns |
| `<x-table.page-header>` | Title, description, `more` and `actions` slots |
| `<x-table.tabs>` | Status tabs with counts (chips on a phone) |
| `<x-table.toolbar>` | Search, `filters`, `end`; `bulk` slot when rows are ticked |
| `<x-table.select>` | Small filter picker |
| `<x-table.bulk-button>` | A bulk action button (`runBulk`) |
| `<x-table.dropdown>` + `<x-table.menu-item>` | "⋯" row menu and "More" |
| `<x-table.footer>` | Range, rows per page, pages |
| `<x-table.empty>` | Nothing to show |
| `<x-table.card>` | A row as a card on a phone |
| `ListTable` (Livewire trait) | Search, tab, sort (whitelisted), rows per page, ticked rows, bulk actions |

A list component uses `ChecksPermissions, ListTable` and provides
`filteredQuery()`, `sortable()` (first = default sort), and optionally
`rowRelations()` and `filterProperties()`. See `InvoicesTable` for the
pattern, including tab counts in one grouped query and the totals row.

## The routine for every session

1. Branch from the latest `tables/main`, e.g. `tables/t2-sales`.
2. Before screenshots (computer, phone, dark).
3. Move each screen onto the shared pieces; take it off
   `tests/Feature/Tables/tables-todo.txt` (the guard test fails otherwise).
4. Readability check (light and dark), phone check (no sideways scroll).
5. Full test suite, Pint, PHPStan.
6. After screenshots; pull request into `tables/main`; update the table below.

Nothing goes live until T8 merges `tables/main` into `main`.

## Sessions and status

| # | Session | Branch | Status | Notes |
|---|---|---|---|---|
| T1 | Shared pieces and the pilot (Invoices) | `tables/t1-foundations` | Done, PR #58 | Shared pieces, ListTable trait, guard test (157 screens to do); Invoices list. Also: status labels for confirmed, processing and invoiced orders; closed credit notes say "Used up"; sorting limited to listed columns on every sales list; 25 rows per page. |
| T2 | Sales lists | `tables/t2-sales` | Done, PR #59 | Customers, quotations, sales orders, sales receipts, delivery notes, credit notes, payments received. Shared filter pieces added (`pick`, `check-all`, `check`, `tick-all-matching`, `veil`). Also: status labels for confirmed, processing and invoiced orders; closed credit notes say "Used up"; sorting limited to listed columns on every sales list; 25 rows per page. |
| T3 | Purchases and expenses | `tables/t3-purchases` | Done, PR #60 | Vendors, purchase orders, bills, payments made, expenses, supplier credits, supplier advances, recurrent bills, recurrent expenses (the last four are now Livewire lists with search, sort and pages). Fixed on the way: vendor and payments-made search used a column that does not exist; the bills status filter offered "Pending" (no such status) and had no "Unpaid"; deleting a bill from its row skipped the normal delete rules; bulk delete could remove a supplier advance that had already been used; bills took any sort column from the address bar; New buttons on vendors, bills, payments made and expenses showed to people without permission; recurrent lists loaded every row on one page. The guard test skipped any screen with "vendor" in its path; fixed, and five more screens added to the to-do list. |
| T4 | Stock, banking and accounting | `tables/t4-stock-banking` | Done, PR #61 | Items, item categories, stock, warehouses, stock transfers, assembly orders, bills of materials, banks, bank lines to review, chart of accounts, journals, budgets, tax rates, tax groups, prepaid and deferred schedules, fixed assets, asset categories, accounting periods (with lock date history), e-invoices. Bills of materials is now a Livewire list; warehouses and accounting periods stay plain pages (the footer now works on plain pages too). Fixed on the way: the items and categories lists ran extra queries for every row (stock, category, item counts); a bank account could be deleted while payments still pointed at it; tax groups and rates could be deleted while items used them; system accounts could be bulk-made inactive; the asset categories list checked permissions the routes do not use; row toggles on tax rates flashed messages nobody saw; the low-stock alert now opens the Running low tab. Removed an unused categories-table view. |
| T5 | Payroll, HR, settings, admin | | To do | |
| T6 | Reports | | To do | |
| T7 | Record pages and forms | | To do | Includes the dashboard's "Show as table". Also from T4: stock history per item (its "all time" in/out cards only add up the page shown), bank money in and out, bank reconcile. |
| T8 | Checks and go-live | | To do | To-do list must be empty |
