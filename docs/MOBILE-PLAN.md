# MyBooks mobile app plan

The working plan for the MyBooks iOS and Android app. Like
`REBRAND-PLAN.md`, it has the rules every session follows, the routine each
session repeats, and one section per session. Each section stands on its own:
a new session reads **Rules**, **Routine** and its own section, and has
everything it needs to finish. When it is done it fills in its row in the
status table at the bottom.

## What we are building

One Flutter app for iPhone and Android, signed in with a MyBooks account,
working on the existing `/api/v1` API. The design is the canvas
"MyBooks Mobile UI": https://claude.ai/artifact/M9ZxcoQApYYJSZf6xpJDQT
(26 phone screens, each frame titled with the endpoints it uses). A session
opens it with the Artifact tool's `read` action and reads the screen files it
builds (for example `project/Invoices.dc.html`). Screen names below are those
file names.

## Decisions (made once, do not reopen)

| Topic | Choice | Why |
|---|---|---|
| Framework | Flutter (stable, version pinned in M1) | One codebase for both platforms; `docs/API.md` already targets Flutter. |
| Code home | New repo `smuhdfagge/mybooks-mobile` | `mybooks-staging` main is pulled by the server on merge; the app must not ride along. API changes still go to staging, then to `mybooks-live`, the usual way. |
| State | Riverpod | Testable, no global singletons. |
| Navigation | go_router | Deep links (for notifications) and nested tabs. |
| HTTP | dio | Interceptors for token, idempotency, retries. |
| Local data | drift (SQLite) | Offline lists and the outbox of unsent changes. |
| Secrets | flutter_secure_storage | The API token lives only here. |
| Biometrics | local_auth | Face ID / fingerprint unlock. |
| Money and dates | intl, `decimal` package | Exact amounts, ₦ formatting, Lagos dates. |
| Push (later) | Firebase Cloud Messaging for both platforms | One server integration. |
| Platform look | Shared design; iOS gets a tab bar, large titles, swipe back; Android gets Material 3 navigation bar, top app bar, FAB | See `AndroidHome` and `AndroidInvoices` on the canvas. |

## Rules for every session

**API facts** (from `routes/api.php`, `BaseApiController`, `AuthController`):

- Base URL: `{APP_URL}/api/v1`. Staging and production are separate build
  flavours; never point a debug build at production.
- Every response is `{ success, message, data }`; errors add `errors`
  (validation, per field). Lists are Laravel pages: read `data.data`,
  `data.current_page`, `data.last_page`; `per_page` is capped at 100.
- Sign-in: `POST /auth/login` with `email`, `password`, `device_name`.
  It may answer `two_factor_required: true`; resend with `two_factor_code`.
  `403` for an unverified email, a deactivated user or organisation.
- Tokens expire after `SANCTUM_TOKEN_EXPIRATION` minutes (480 by default).
  Call `POST /auth/refresh` before expiry; on `401` go to sign-in.
- What a user may do comes from `GET /auth/user` → `permissions`. Hide any
  tab, menu item or button the user lacks the permission for; never show a
  screen that will only fail with `403`.
- `403` with `subscription_required: true` means the trial or plan has
  ended: show the "subscription needed" screen with a link to the web, not an
  error.
- Limits: reads 120/min, writes 30/min, reports 30/min, sign-in 5/min.
  On `429` wait for `Retry-After`, then retry once.
- Every `POST`, `PUT`, `DELETE` sends an `Idempotency-Key` (a UUID made when
  the user taps Save, kept with the queued change). `409` = still running,
  retry later; `422` with a reused key = a bug, report it.
- Offline: `GET /sync?updated_since=&entities=&cursors[..]=` and
  `GET /sync/deleted`. Entities: customers, vendors, items, accounts,
  tax_rates, invoices, bills, expenses, payments_received, payments_made.

**App rules:**

- The server is the source of truth for every total, tax and balance. The
  phone may show a preview while typing; after saving it shows what the
  server returned.
- Amounts: parse to `Decimal`, never `double` arithmetic. Show as `₦1,247,000`
  (no kobo unless non-zero). Dates as `18 Nov 2026`, in Africa/Lagos.
- Colours and type come only from the app theme (M1): navy `#1F4E79`, ochre
  accent for small highlights (never white text on ochre), IBM Plex Sans.
  Status colours always mean the same thing: green paid/done/money in,
  red overdue/problem, amber waiting/part paid, navy open/info, grey draft.
- Every screen works in light and dark mode, at large text sizes, and has
  touch targets of at least 44 pt. Icon-only buttons carry a label for
  screen readers.
- No customer data, tokens or `.env` values in logs, crash reports, test
  fixtures or screenshots. Fixtures use made-up businesses.
- Sample data in the canvas is made up; build from the API, not the canvas
  numbers.

## The routine for every session

1. Read this file: Rules, Routine, your section, and the status table
   hand-over notes of the sessions yours depends on.
2. Make sure the repos you need are attached. App sessions:
   `mybooks-mobile` (push) and `mybooks-staging` (read, to check controllers
   and resources for exact field names). API sessions: `mybooks-staging`.
3. Branch from the latest `main`: `mobile/m3-shell`, `api/a1-mobile-gaps`
   and so on (the branch is in your row).
4. Open the canvas and read the screens your section lists.
5. Build. For each response you parse, add a fixture in
   `test/fixtures/<endpoint>.json` copied from the Resource or controller
   shape, with made-up values.
6. Check: `flutter analyze` clean, `flutter test` green, golden tests for each
   new screen at 390×844 in light and dark (these are the screenshots for
   the pull request). API sessions: full suite, Pint, PHPStan, as in staging.
7. Pull request with the golden images (or test output for API work). The
   owner reviews and merges; CI must be green.
8. Update your row in the status table: status, PR number, and a hand-over
   note naming anything the next session must know.

A session that cannot finish says so in its row and leaves the work on its
branch; the next session continues there rather than starting again.

---

## M0 Owner setup (Fagge, not a Claude session)

Before M1:

- Create the empty GitHub repo `smuhdfagge/mybooks-mobile` and attach it to
  the cloud environment.
- Decide the app identifiers (for example `cloud.mybooks.app`) and the app
  name shown under the icon.
- Apple Developer account and Google Play Console account (needed by M15;
  start early, Apple approval can take days).
- A staging test organisation with four users: owner, accountant, sales
  staff, and one with 2FA on. Share the sign-in details through the
  environment's secrets, not in chat or the repo.

## M1 Foundations

- **Repo:** mybooks-mobile · **Branch:** `mobile/m1-foundations` · **Depends on:** M0
- **Goal:** a Flutter project that builds for both platforms, with the theme
  and shared widgets every later screen uses, and CI.
- **Tasks:**
  1. `flutter create` with org from M0; pin the Flutter version (`.fvmrc` or
     README). Flavours `staging` and `production` with the API base URL per
     flavour.
  2. Cloud environment: a setup script that installs the pinned Flutter SDK
     and runs `flutter pub get`, so later sessions start ready.
  3. Folder layout: `lib/core` (api, auth, db, theme, widgets),
     `lib/features/<area>`, `test/` mirroring `lib/`.
  4. Theme: light and dark `ThemeData` from the brand colours above (copy
     the scales from `tailwind.config.js` in staging); IBM Plex Sans bundled
     as an asset (no font download at run time).
  5. Shared widgets, each with a golden test: `StatusBadge` (paid, part paid,
     unpaid, overdue, draft, approved, rejected, posted, reversed, in transit,
     received), `MoneyText`, `SummaryTile`, `ListRowCard`, `SectionHeader`,
     `PrimaryButton` / `SecondaryButton` / `DangerButton`, `EmptyState`,
     `ErrorState`, `FilterChips`, `QuantityStepper`, `BottomSheetScaffold`.
  6. App icon and splash from `public/images/brand` in staging ("Ledger
     total" mark).
  7. GitHub Actions: analyze + test on Ubuntu; Android debug APK; iOS build
     without signing on a macOS runner.
- **Done when:** the app opens to a "widget gallery" page showing every
  shared widget in light and dark; CI green.

## M2 API client and sign-in

- **Repo:** mybooks-mobile · **Branch:** `mobile/m2-auth` · **Depends on:** M1
- **Screens:** `Login`
- **Endpoints:** `POST /auth/login`, `/auth/forgot-password`,
  `/auth/logout`, `/auth/refresh`, `GET /auth/user`
- **Tasks:**
  1. dio client: base URL by flavour, JSON, envelope parsing into
     `ApiResult` / `ApiError` (validation errors by field), interceptors for
     bearer token, `Idempotency-Key` on writes, `429` with `Retry-After`,
     `401` → one refresh attempt → sign-in.
  2. Token in secure storage; refresh when less than an hour is left.
  3. Sign-in screen as drawn; two-factor code step; forgot password (email
     only, opens the reset link on the web); "Start a free trial" opens the
     web sign-up (there is no sign-up endpoint).
  4. Biometric unlock after first sign-in (opt-in), with fallback to
     password.
  5. `CurrentUser` provider holding user, organisation and permissions;
     a `can('view invoices')` helper used everywhere later.
  6. "Subscription needed" screen for `subscription_required`; error screens
     for deactivated user or organisation.
- **Done when:** a staging user can sign in (with and without 2FA), stay
  signed in across restarts, unlock with biometrics, and sign out; widget
  tests cover each error path.

## M3 App shell and Home

- **Repo:** mybooks-mobile · **Branch:** `mobile/m3-shell` · **Depends on:** M2
- **Screens:** `Main`, `AndroidHome`, `HomeDark`, `More`
- **Endpoints:** `GET /dashboard`, `/dashboard/trends`,
  `/dashboard/recent-transactions`, `/auth/user`
- **Tasks:**
  1. go_router with the five tabs (Home, Sales, Expenses, Reports, More):
     iOS tab bar, Android Material 3 navigation bar. Tabs hidden without
     permission.
  2. Placeholder route for every screen on the canvas, so later sessions only
     fill pages in.
  3. Home: revenue card, to collect / to pay tiles, quick actions, low-stock
     alert, 6-month chart, recent activity; pull to refresh; loading and
     error states.
  4. More: grouped menu, each item shown only with its view permission;
     profile row; sign out. Sync row shows "not set up yet" until M4.
- **Done when:** signing in lands on a working Home on both platforms in
  light and dark; More shows the right items for owner vs sales staff.

## M4 Offline data and sync

- **Repo:** mybooks-mobile · **Branch:** `mobile/m4-sync` · **Depends on:** M2 (M3 for the sync row)
- **Endpoints:** `GET /sync`, `/sync/status`, `/sync/deleted`, `/sync/{entity}`
- **Tasks:**
  1. drift database with tables for the ten sync entities (only the fields
     the screens show, plus `id`, `updated_at`, raw JSON).
  2. Sync engine: first full pull, then `updated_since` with per-entity
     cursors; apply deletions; run on app start, on pull to refresh, on
     regaining network, and every 15 minutes in the foreground.
  3. Outbox: every write is stored with its Idempotency-Key and sent in
     order when online; failed items stay visible with the server's message
     ("Couldn't save: …") and can be edited or discarded.
  4. Repositories that read from the database first, then refresh from the
     API (lists open instantly offline).
  5. Sync status row on More, and the "Synced 2 min ago" footer widget.
  6. Sign out wipes the database and outbox.
- **Done when:** with the network off, previously loaded lists open, a new
  record can be queued and is sent once back online without duplicates
  (test by sending twice with the same key).

## M5 Invoices

- **Repo:** mybooks-mobile · **Branch:** `mobile/m5-invoices` · **Depends on:** M3, M4
- **Screens:** `Invoices`, `InvoiceDetail`, `AndroidInvoices`
- **Endpoints:** `GET /invoices`, `/invoices/summary`, `/invoices/{id}`,
  `/invoices/{id}/pdf`, `POST /invoices/{id}/send`,
  `PUT /invoices/{id}/status`
- **Tasks:** list with search, status chips (all, unpaid, overdue, paid,
  draft) and summary tiles; infinite scroll; detail with balance, progress
  bar, items, VAT, history; Email (send), Share PDF (download then system
  share sheet), WhatsApp (share sheet with the PDF and a short message);
  "Record payment" opens the M6 sheet (stub until then).
- **Done when:** both platforms list, filter and open invoices from staging,
  share a PDF, and send by email.

## M6 New invoice and record payment

- **Repo:** mybooks-mobile · **Branch:** `mobile/m6-create` · **Depends on:** M5
- **Screens:** `NewInvoice`, `RecordPayment`
- **Endpoints:** `POST /invoices`, `PUT /invoices/{id}`,
  `GET /search/customers`, `/search/items`, `/tax-rates`,
  `/settings/payment-methods`, `/banks`, `POST /payments-received`
- **Tasks:** customer picker with search; dates (default terms from
  settings); item lines with stepper, add item, barcode scan if the item
  search accepts SKU; discount; VAT from the default tax rate; live preview
  total, then the server's total after saving; Save draft / Save & send;
  record payment sheet (full balance or other amount, method, paid into,
  date, reference) with the success state and Send receipt. Both writes go
  through the outbox.
- **Done when:** an invoice made on the phone appears on the web with the
  same totals, and a part payment updates its status to part paid.

## M7 Customers

- **Repo:** mybooks-mobile · **Branch:** `mobile/m7-customers` · **Depends on:** M4
- **Screens:** `Customers`, `CustomerDetail`
- **Endpoints:** `GET /customers`, `/customers/{id}`,
  `/customers/{id}/statistics`, `/reports/customer-statement`,
  `POST /customers`, `PUT /customers/{id}`
- **Tasks:** list with search and "Owe you" filter; detail with call, email,
  WhatsApp, map (system apps), statistics, New invoice (prefilled), Send
  statement (share the statement), their invoices; add and edit customer
  form.
- **Done when:** customers can be found, opened, added and edited, offline
  included.

## M8 Expenses and approvals

- **Repo:** mybooks-mobile · **Branch:** `mobile/m8-expenses` · **Depends on:** M4 (A1 for receipts)
- **Screens:** `Expenses`
- **Endpoints:** `GET /expenses`, `/expenses/summary`, `POST /expenses`,
  `POST /expenses/{id}/submit`, `/approve`, `/reject`
- **Tasks:** summary tiles; "Needs your approval" queue for users with
  approve permission; Approve / Reject (reject asks for a reason if the API
  takes one); recent list with statuses (draft, pending approval, approved,
  rejected, paid); add expense form and submit for approval. Receipt photo
  only if A1 has shipped; otherwise leave the button out.
- **Done when:** a staff user submits an expense on one phone and the owner
  approves it on another.

## M9 Bills and vendors

- **Repo:** mybooks-mobile · **Branch:** `mobile/m9-purchases` · **Depends on:** M4
- **Screens:** `Bills`, `Vendors`
- **Endpoints:** `GET /bills`, `/bills/summary`, `/vendors`,
  `/vendors/{id}/statistics`, `POST /payments-made`
- **Tasks:** bills total with overdue / this week / later bar; bill cards
  with Pay (payment sheet like M6, for money out); vendor list with search
  and summary card.
- **Done when:** paying a bill on the phone marks it paid on the web.

## M10 Reports

- **Repo:** mybooks-mobile · **Branch:** `mobile/m10-reports` · **Depends on:** M3
- **Screens:** `Reports`
- **Endpoints:** `GET /reports/profit-loss` and the others in
  `routes/api.php` (payroll reports need `view payroll`)
- **Tasks:** period switch (month, quarter, year to date) with the P&L card;
  list of reports the user may see; one generic report page (title, period,
  sections of label/amount rows, totals) used for balance sheet, cash flow,
  ageing, sales and tax reports; PDF/share where the web has it (if no API
  PDF, share as text table or leave out and note it). Mind the 30/min
  reports limit: cache each report for the period for 5 minutes.
- **Done when:** owner sees all reports; sales staff sees only those allowed.

## M11 Stock

- **Repo:** mybooks-mobile · **Branch:** `mobile/m11-stock` · **Depends on:** M4 (A1 for receive and finish)
- **Screens:** `Stock`, `StockTransfers`, `Assembly`
- **Endpoints:** `GET /inventory`, `/inventory/summary`, `/warehouses`,
  `POST /inventory/{id}/adjust` (`type` add/subtract/set, `quantity`,
  `reason`), `GET/POST /stock-transfers`, `GET /bill-of-materials`,
  `POST /assembly-orders` (`complete: true` to build now)
- **Tasks:** warehouse chips, value / low / out tiles, item rows with level
  bars, adjust sheet; new transfer with swap and available quantity, recent
  transfers; assemble with build / break down, parts needed vs available,
  build now or draft. Hide transfers and assembly when the organisation has
  those modules off (see A1 task 4). Receive transfer and finish draft only
  after A1.
- **Done when:** an adjustment, a transfer and a build made on the phone
  show the same stock on the web.

## M12 Accounting

- **Repo:** mybooks-mobile · **Branch:** `mobile/m12-accounting` · **Depends on:** M4
- **Screens:** `Journals`, `Accounts`, `Banks`, `TaxRates`
- **Endpoints:** `GET /journals`, `/journals/summary`,
  `POST /journals/{id}/post`, `/reverse`, `GET /accounts`,
  `/accounts/balance-summary`, `GET /banks`, `/banks/summary`,
  `GET/POST/PUT /tax-rates`
- **Tasks:** journals with balanced banner, expandable lines, Post and
  Reverse with a confirm step; chart of accounts grouped and collapsible
  with search; banks with total, account cards, recent movements, hide
  balances (remembered on the phone); tax rates with default and add form.
- **Done when:** an accountant user can post and reverse a draft journal
  from the phone.

## M13 Sales orders, employees, settings

- **Repo:** mybooks-mobile · **Branch:** `mobile/m13-more` · **Depends on:** M4
- **Screens:** `SalesOrders`, `Employees`, `Settings`
- **Endpoints:** `GET /sales-orders`, `/sales-orders/summary`,
  `PUT /sales-orders/{id}`, `GET /employees`, `/employees/summary`,
  `GET /settings`, `PUT /settings/organization`, `/tax`, `/accounting`
- **Tasks:** sales orders with counts and the next-step button, "Turn into
  invoice" opens M6 prefilled; employees list, pay shown only when
  `total_monthly_salary` is not null (the API hides it without
  `view payroll`); settings: business, tax and accounting values (editable
  only with `edit settings`), phone settings (biometrics, offline data,
  appearance light/dark/system).
- **Done when:** all More items open real screens and the role rules hold.

## M14 Push notifications

- **Repo:** mybooks-mobile · **Branch:** `mobile/m14-push` · **Depends on:** A2, M3
- **Tasks:** Firebase setup for both apps; ask permission after the first
  approval or invoice action, not at launch; register the device token with
  A2's endpoint after sign-in, remove it on sign out; tapping a notification
  opens the right screen through go_router (expense to approve, overdue
  invoice, payment received); notification switch in Settings.
- **Done when:** approving flow works end to end: staff submits, owner's
  phone is notified, tap opens the expense.

## M15 Release

- **Repo:** mybooks-mobile · **Branch:** `mobile/m15-release` · **Depends on:** M1–M13 (M14 optional)
- **Tasks:** production flavour on the live API; crash reporting with no
  personal data; minimum-version check (a small `GET` the app calls at start,
  added in A1 or A2 if needed); store listings, screenshots from the goldens,
  privacy answers (what data, why); signed builds in CI; TestFlight and Play
  internal testing; a short "how to release" section added to the repo
  README.
- **Done when:** the owner can install both test builds from the stores and
  sign in to production.

---

## A1 API additions for the app

- **Repo:** mybooks-staging (then ported to mybooks-live) · **Branch:** `api/a1-mobile-gaps` · **Depends on:** nothing; run alongside M1–M4
- **Tasks**, each with feature tests in `tests/Feature/Api` and a section in
  `docs/API.md`:
  1. Expense receipts: `POST /expenses/{id}/attachments` (image or PDF,
     size limit as the web upload), `GET` and `DELETE`; same permission as
     editing the expense.
  2. Stock transfers: `POST /stock-transfers/{id}/ship` and `/receive`,
     using the same actions as the web.
  3. Assembly: `POST /assembly-orders/{id}/complete` for a saved draft
     (`quantity_made` optional), using the existing CompleteAssemblyOrder.
  4. Modules: add the organisation's on/off modules (warehouses, stock
     transfers, assembly, quotations and so on) to `GET /auth/user` or
     `GET /settings`, so the app hides what is off.
  5. App version: `GET /app/version` (public) returning the minimum and
     latest app versions from config, for M15.
- **Done when:** tests, Pint and PHPStan green; PR merged on staging; same
  diff opened as a PR on mybooks-live.

## A2 API push notifications

- **Repo:** mybooks-staging (then mybooks-live) · **Branch:** `api/a2-push` · **Depends on:** A1 merged
- **Tasks:** `POST /devices` and `DELETE /devices/{token}` (token, platform,
  app version), tied to the user and their API token; send through Firebase
  Cloud Messaging (server key in `.env`, never committed) on a queue for:
  expense waiting for approval, invoice paid, invoice became overdue, bill
  due tomorrow; respect each user's permissions; drop tokens Firebase
  reports as invalid; tests with the sender faked.
- **Done when:** as A1, plus a short note in `docs/API.md` on the payload
  (`type`, `id`) the app uses to open the right screen.

---

## Order and what can run together

```
M0 ─ M1 ─ M2 ─ M3 ─ M4 ─┬─ M5 ─ M6
                        ├─ M7
                        ├─ M8 (receipts after A1)
                        ├─ M9
                        ├─ M10 (needs only M3)
                        ├─ M11 (receive/finish after A1)
                        ├─ M12
                        └─ M13 ─┐
A1 (any time) ─ A2 ─────────── M14 ─ M15
```

M5 to M13 do not depend on each other (except M6 after M5) and can run in
parallel sessions once M4 is merged.

## Sessions and status

| # | Session | Repo | Branch | Status | Notes / hand-over |
|---|---|---|---|---|---|
| M0 | Owner setup | | | Done | |
| M1 | Foundations | mobile | `mobile/m1-foundations` | Done | |
| M2 | API client and sign-in | mobile | `mobile/m2-auth` | Done | |
| M3 | App shell and Home | mobile | `mobile/m3-shell` | Done | |
| M4 | Offline data and sync | mobile | `mobile/m4-sync` | Done | |
| M5 | Invoices | mobile | `mobile/m5-invoices` | To do | |
| M6 | New invoice and record payment | mobile | `mobile/m6-create` | To do | |
| M7 | Customers | mobile | `mobile/m7-customers` | To do | |
| M8 | Expenses and approvals | mobile | `mobile/m8-expenses` | To do | |
| M9 | Bills and vendors | mobile | `mobile/m9-purchases` | To do | |
| M10 | Reports | mobile | `mobile/m10-reports` | To do | |
| M11 | Stock | mobile | `mobile/m11-stock` | To do | |
| M12 | Accounting | mobile | `mobile/m12-accounting` | To do | |
| M13 | Sales orders, employees, settings | mobile | `mobile/m13-more` | To do | |
| M14 | Push notifications | mobile | `mobile/m14-push` | To do | |
| M15 | Release | mobile | `mobile/m15-release` | To do | |
| A1 | API additions for the app | staging → live | `api/a1-mobile-gaps` | To do | |
| A2 | API push notifications | staging → live | `api/a2-push` | To do | |
