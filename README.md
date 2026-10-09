# MyBooks

MyBooks is online accounting and business software for small and medium businesses, built for Nigeria first (Naira, VAT, PAYE, pension). Each business that signs up is a separate organisation (tenant) with its own users, roles and data.

## What it does

- **Sales**: customers, invoices, cash sales (sales receipts), payments received, customer deposits, refunds, sales orders.
- **Purchases**: vendors, purchase orders, bills (including "convert to bill" from an order), payments made, expenses.
- **Items and stock**: products and services, stock on hand, reservations for open invoices, FIFO or weighted-average costing.
- **Accounting**: chart of accounts, double-entry journals posted automatically from every document, banks, budgets, fixed assets and depreciation, year-end close.
- **HR and payroll**: employees, departments, leave, salary structures, payroll runs and batches, payslips, statutory deductions.
- **Reports**: profit and loss, balance sheet, cash flow, trial balance, general ledger, ageing, sales and purchase reports, payroll reports, and a custom report builder.
- **Platform**: subscriptions paid through Paystack, roles and permissions per organisation, two-factor sign-in, activity log, data export, and a REST API for the mobile app ([docs/API.md](docs/API.md)).

Quotations, delivery notes, credit notes, warehouses, stock transfers and assembly have back-end code but no screens yet. They are switched off; see [Feature switches](#feature-switches).

## Built with

Laravel 12 (PHP 8.2+), Livewire 3, Tailwind CSS and Alpine.js, Spatie laravel-permission, Sanctum for the API, DomPDF for PDFs.

## Running it locally

You need PHP 8.2+ with the usual Laravel extensions (mbstring, pdo_sqlite or pdo_mysql, zip, gd, bcmath, intl), Composer, and Node 20.

```bash
git clone https://github.com/smuhdfagge/mybooks-staging.git mybooks
cd mybooks
composer setup          # installs packages, creates .env and the app key, migrates, builds assets
php artisan db:seed     # roles, permissions, plans, countries, tax templates
composer dev            # web server, queue worker, log viewer and Vite together
```

`.env.example` has safe server defaults; on your own machine set `APP_DEBUG=true` (and `SESSION_SECURE_COOKIE=false` if you use plain http). The default `.env` uses SQLite. For MySQL or MariaDB set `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD`, then run `php artisan migrate`.

Excel import needs one extra package: `composer require phpoffice/phpspreadsheet`. Without it, CSV import still works.

## Tests

```bash
php artisan test
```

The suite runs on SQLite in memory. To run it against MySQL or MariaDB:

```bash
DB_CONNECTION=mariadb DB_HOST=127.0.0.1 DB_DATABASE=mybooks_test DB_USERNAME=... DB_PASSWORD=... php artisan test
```

Before pushing, also run:

```bash
vendor/bin/pint            # formats the code (CI runs pint --test)
composer analyse           # PHPStan; new code must not add to phpstan-baseline.neon
```

`tests/Feature/Regression` holds a test for each fixed finding from the September 2026 review, named after the finding (C1, H4, M7 and so on). GitHub Actions runs the whole suite on every pull request.

## Deploying

1. **Document root.** Point the web server at the `public/` folder. The `.htaccess` in the project root is only a fallback for hosting where that can't be changed. It refuses dotfiles, and refuses everything if `mod_rewrite` is missing, so `.env` is never served.
2. **Environment.** Copy `.env.production.example` to `.env` (every other setting is listed, with its default, in `.env.example`) and set at least:
   - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `APP_KEY` (`php artisan key:generate`)
   - database and mail settings
   - `PAYSTACK_SECRET_KEY` and `PAYSTACK_PUBLIC_KEY` for subscription payments. In the Paystack dashboard, set the webhook URL to `https://<your domain>/billing/paystack/webhook`.
   - for SMS and WhatsApp to customers, the Termii keys and `MESSAGING_WEBHOOK_TOKEN` (see [SMS and WhatsApp](#sms-and-whatsapp) below).
   - for bank feeds, the Mono keys and webhook secret (see [Bank feeds (Mono)](#bank-feeds-mono) below).
   - for NRS e-invoicing, the NRS addresses (see [E-invoicing (NRS)](#e-invoicing-nrs) below). Leave `MYBOOKS_FEATURE_E_INVOICING` off until they are checked.
3. **Install and migrate.**
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   php artisan migrate --force
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
4. **Scheduler.** Add one cron entry. It expires subscriptions and sends renewal reminders, processes recurring transactions, posts the automatic reversals of accrual journals, releases the monthly prepaid expense and deferred revenue amounts, and sends payment and stock reminders.
   ```
   * * * * * cd /path/to/mybooks && php artisan schedule:run >> /dev/null 2>&1
   ```
5. **Queue.** Emails, large payroll batches, imports and exports run on the queue. The scheduler (step 4) empties it every minute, so shared hosting needs nothing more. If Supervisor keeps `php artisan queue:work --timeout=600` running, you can set `QUEUE_WORK_FROM_SCHEDULER=false`. Keep `DB_QUEUE_RETRY_AFTER` above 600.
6. **First time billing is switched on.** Run `php artisan subscriptions:grace --days=14` once (try `--dry-run` first). Organisations already past their end date then get two weeks to pay instead of being locked out immediately.

Take a database backup before every deploy (`php artisan mybooks:backup`). Deploy one phase of changes at a time.

## Backups

`php artisan mybooks:backup` runs every night at 01:30 from the scheduler. It makes one zip with a database dump (`mysqldump`, or a copy for SQLite) and the uploaded files, writes it to every disk in `BACKUP_DISKS`, and removes copies older than `BACKUP_KEEP_DAYS` (the newest one is always kept). If it fails, it emails `BACKUP_NOTIFY_EMAIL` (default: the support email).

By default the only disk is `backups` (`storage/app/backups` on the same server). That protects against mistakes, not against losing the server, so add an off-site copy:

1. Create a bucket on any S3-compatible storage (Cloudflare R2, Backblaze B2 or AWS S3) with a key that can only write to that bucket.
2. `composer require league/flysystem-aws-s3-v3 "^3.0"`
3. In `.env`: `BACKUP_DISKS=backups,offsite`, the `BACKUP_S3_*` settings (see `config/filesystems.php`), and `BACKUP_ARCHIVE_PASSWORD` to encrypt the zip (AES-256). Keep that password somewhere other than the server.
4. Run `php artisan mybooks:backup` once and check the file arrives.

**Restore test (do this every quarter):** download a backup, unzip it with the password (7-Zip or `unzip`), load `database.sql` into an empty database with `mysql new_db < database.sql`, and check the row counts look right. `files/` holds the uploads to copy back under `storage/app/`.

## VAT

The VAT return (Reports > VAT/GST Return) is read from the ledger: output VAT on Sales Tax Payable (2400) and input VAT on Input VAT (1410), for every posted invoice, cash sale, refund, credit note, bill and expense in the period, paid or not. When you file a return, press **Settle VAT for this period**: the period's output and input VAT move into VAT Payable (2410), ready for the payment to the tax authority. Each period can be settled once.

Before October 2026, input VAT was posted to Prepaid Expenses (1400). If you want earlier periods on the new basis, move that input VAT to 1410 with a manual journal (your accountant can tell you the amount from the old bills).

## Errors and logs

Logs go to `storage/logs/laravel-YYYY-MM-DD.log`, one file a day, kept for `LOG_DAILY_DAYS` (14). Set `LOG_LEVEL=warning` in production.

In production, server errors are also emailed to `ERROR_ALERT_EMAIL` (default: the support email). Each distinct error is sent at most once an hour, with at most 20 alerts an hour in total. The email holds the error, where it happened, the page and the user and business IDs, never form data. For a fuller service later (grouping, history), Sentry or Flare can replace this.

## Feature switches

Unfinished modules answer 404 until they are switched on in `.env`. Only switch one on once its screens exist and its tests pass.

| Setting | Module |
|---|---|
| `MYBOOKS_FEATURE_QUOTATIONS` | Quotations |
| `MYBOOKS_FEATURE_DELIVERY_NOTES` | Delivery notes |
| `MYBOOKS_FEATURE_CREDIT_NOTES` | Credit notes |
| `MYBOOKS_FEATURE_WAREHOUSES` | Warehouses |
| `MYBOOKS_FEATURE_STOCK_TRANSFERS` | Stock transfers |
| `MYBOOKS_FEATURE_ASSEMBLY` | Bills of materials and assembly orders |
| `MYBOOKS_FEATURE_INVENTORY_VALUATION` | Stock valuation page |
| `MYBOOKS_FEATURE_SMS_WHATSAPP` | SMS and WhatsApp reminders and receipts to customers (sends nothing until the keys below are set) |
| `MYBOOKS_FEATURE_BANK_FEEDS` | Bank feeds with Mono (reads nothing until the Mono keys below are set) |
| `MYBOOKS_FEATURE_E_INVOICING` | NRS e-invoicing. **Off by default**; see [E-invoicing (NRS)](#e-invoicing-nrs) before switching it on |
| `MYBOOKS_FEATURE_AUTO_RENEWAL` | Saved card and automatic renewal of subscriptions (`MYBOOKS_AUTO_RENEW_DAYS_BEFORE`, `MYBOOKS_AUTO_RENEW_RETRY_DAYS`, `MYBOOKS_CARD_EXPIRY_WARNING_DAYS`) |

Other settings in `config/mybooks.php` include support email, import size limit, data retention periods, and HSTS. Leave `MYBOOKS_HSTS_INCLUDE_SUBDOMAINS` and `MYBOOKS_HSTS_PRELOAD` off unless every subdomain is permanently on HTTPS.

## SMS and WhatsApp

Businesses can text their customers when an invoice is sent, before it is due, when it is overdue, and when a payment comes in (Settings > SMS & WhatsApp). MyBooks holds the provider account and pays for the messages; each plan has a monthly SMS and WhatsApp allowance, set by the platform admin under SMS & WhatsApp. Messages wait until 07:00 if they would go out between 21:00 and 07:00 Lagos time (receipts go at once).

To switch it on:

1. **Termii account** (termii.com). Put `TERMII_API_KEY` and the base URL from the dashboard (`TERMII_BASE_URL`) in `.env`.
2. **Sender ID.** Request one on Termii (3 to 11 letters, e.g. `MyBooks`) for transactional use, with a sample such as "Kano Traders Ltd: Hello Musa, invoice INV-000123 for NGN 125,000 is due on 20 Oct 2026." Set `TERMII_SENDER_ID` once approved. Messages go on the `dnd` route (`TERMII_SMS_CHANNEL`), which also reaches numbers on the NCC Do-Not-Disturb list; that is allowed for transactional messages about a customer's own invoice, not for marketing.
3. **Delivery reports.** Set `MESSAGING_WEBHOOK_TOKEN` to a long random string and, in the Termii dashboard, the webhook URL to `https://<your domain>/webhooks/messaging/termii/<token>`. Put Termii's secret key in `TERMII_SECRET_KEY` so reports are also checked by signature.
4. **WhatsApp (optional).** Either through Termii (`TERMII_WHATSAPP_DEVICE_ID`) or Meta's Cloud API (`WHATSAPP_META_TOKEN`, `WHATSAPP_META_PHONE_NUMBER_ID`, `WHATSAPP_META_APP_SECRET`, `WHATSAPP_META_VERIFY_TOKEN`; webhook `https://<your domain>/webhooks/messaging/whatsapp/<token>`). Submit these templates for approval, category **Utility**, language English, footer "Reply STOP to stop these messages.", and put each approved name (Meta) or template ID (Termii) in the matching `WHATSAPP_TEMPLATE_*` setting. On Termii, name the variables `customer`, `business`, `invoice`, `amount`, `due_date` and `balance` in the order shown.

| Template | Text | Values |
|---|---|---|
| `mybooks_invoice_sent` | Hello {{1}}, {{2}} has sent you invoice {{3}} for {{4}}, due on {{5}}. Thank you for your business. | customer, business, invoice, amount, due date |
| `mybooks_payment_reminder` | Hello {{1}}, this is a reminder from {{2}} that invoice {{3}} for {{4}} is due on {{5}}. Please pay on time. Thank you. | customer, business, invoice, amount, due date |
| `mybooks_invoice_overdue` | Hello {{1}}, {{2}} reminds you that invoice {{3}} for {{4}} was due on {{5}} and is not yet paid. Please pay or contact them. Thank you. | customer, business, invoice, amount, due date |
| `mybooks_payment_received` | Hello {{1}}, {{2}} has received your payment of {{3}} for invoice {{4}}. Balance left: {{5}}. Thank you. | customer, business, amount paid, invoice, balance |
| `mybooks_test_message` | This is a test message from {{1}} on MyBooks. WhatsApp messages are working. | business |

Without keys nothing is sent: messages are only written to the log, and the settings page says it is not set up yet. A customer who replies STOP on WhatsApp is opted out automatically; SMS replies can't reach a letters-only sender ID, so a business ticks "Does not want SMS messages" on the customer's page when asked.

## Bank feeds (Mono)

A business can link a naira bank account through Mono (Banks > Connect bank feed, or Accountant > Bank feeds). MyBooks reads the account's transactions, read only, and keeps them as bank lines to review. Each line is matched to something already in MyBooks (same amount, within 5 days, name or reference similar) or recorded from the line. Nothing is ever posted until a person clicks. MyBooks stores no bank login and only the last 4 digits of the account number. One Mono account serves all businesses; each plan can limit how many accounts a business links (`bank_feed_accounts_limit`, empty means unlimited; seeded 1, 3 and 10).

To switch it on:

1. **Mono account** (mono.co). Create an app in the dashboard with the Connect product and the Data product enabled. Put the keys in `.env`: `MONO_SECRET_KEY` and `MONO_PUBLIC_KEY`. Test with the sandbox keys first. `MONO_BASE_URL` stays `https://api.withmono.com`.
2. **Webhook.** In the Mono dashboard set the webhook URL to `https://<your domain>/webhooks/bank-feeds/mono` and a secret of your choosing, and put the same value in `MONO_WEBHOOK_SECRET`. Calls without the right `mono-webhook-secret` header are refused.
3. **Redirect.** The customer returns to `https://<your domain>/bank-feeds/callback`; allow that address if your Mono app restricts redirects.
4. **Scheduler.** `bankfeeds:sync` runs every three hours (the normal `schedule:run` cron). Mono's webhook and the "Sync now" button (once every 5 minutes per account) fetch sooner.

The first pull reads the last 90 days (`BANK_FEEDS_FIRST_PULL_DAYS`). Later pulls re-read 5 days back and add only transactions not seen before. If the bank asks for a new login, the account shows "Needs you to log in again" with a Reconnect button. Without the keys nothing is called and the screens say it is not set up yet. Mono is listed under data processors on the privacy page.

## E-invoicing (NRS)

The Nigeria Revenue Service (NRS, formerly FIRS) is bringing in e-invoicing through its Merchant Buyer Solution (MBS). For a business customer (B2B/B2G) the invoice is sent to NRS, which returns an invoice reference number (IRN), a cryptographic stamp (CSID) and a QR code; the invoice is not valid until NRS has issued these. A sale to a customer without a TIN (B2C) above N50,000 is reported to NRS within 24 hours. Reported dates (from vendors, not confirmed): large businesses (turnover N5bn and above) from 1 July 2026, medium (N1bn to N5bn) go-live 1 July 2026 with enforcement in Q1 2027, small (under N1bn) from 1 July 2027. **The feature is off by default** (`MYBOOKS_FEATURE_E_INVOICING=false`) and should stay off until the checks below are done.

What it does once on: each business turns it on under Settings > E-invoicing and enters its own NRS keys (API key, API secret, 8-character Service ID, Business ID, public key and certificate), encrypted in the database and never shown back in full. Invoices and credit notes get an E-invoice panel with status (Not submitted, Pending, Accepted, Rejected, Failed), a Send / Retry button, the IRN and the QR code; an E-invoices page lists them with filters and "Send selected to NRS". The IRN and QR are printed on the invoice once accepted. A business chooses "manual only" (default) or "automatic" (an invoice that is sent or issued, and a credit note that is posted, is queued for NRS). Sending only records a status in `e_invoice_submissions`; it never changes the books, and posting is never held up by NRS being down. An accepted invoice cannot be edited, cancelled or deleted (issue a credit note), and a credit note can only go after its invoice is accepted, because it refers to the invoice's IRN. The business TIN is the Tax Number on Company profile (printed on invoices); a customer with a TIN on their page is B2B, without is B2C.

Go-live steps:

1. **Check the unverified items below** against the NRS Postman collection and API documents (get them from your NRSMBS dashboard or an NRS-accredited Access Point Provider). Correct `.env` or `config/mybooks.php` (`einvoicing`) where they differ. Nothing in the code needs to change for the address, header names or endpoint paths.
2. **Set the addresses** the same for all businesses: `NRS_SANDBOX_BASE_URL` and `NRS_LIVE_BASE_URL`. While an address is empty nothing is sent for that environment and the settings page says "not set up yet". Header names: `NRS_KEY_HEADER`, `NRS_SECRET_HEADER`. Paths: `NRS_PATH_TEST` (a read-only call that needs the keys), `NRS_PATH_SUBMIT`, `NRS_PATH_CONFIRM` (`{irn}` is replaced). B2C limit: `EINVOICING_B2C_THRESHOLD`.
3. **Switch the feature on** (`MYBOOKS_FEATURE_E_INVOICING=true`). It adds the permissions `view e-invoices`, `submit e-invoices` and `manage e-invoicing` (admins; accountants get the first two) through the migration.
4. **Scheduler and queue.** `einvoice:retry` runs hourly (the normal `schedule:run` cron) and the queue worker must run for automatic sending. It asks NRS about documents left Pending, sends Failed ones again (waits of 10, 30 and 60 minutes, at most 5 tries in all, `einvoicing.max_attempts`; the Retry button always works) and sends a notice about B2C invoices above the limit that are within 4 hours of the 24-hour deadline and not yet accepted.
5. **Practise in the sandbox.** A business enters its sandbox keys, chooses Sandbox, uses "Test connection", then sends a few invoices and a credit note. Only then Live with the live keys.

There is no webhook: NRS's answer is read from the reply to the submission, and anything left Pending is followed up by asking NRS (`einvoice:retry`, or "Check with NRS"). A document only needs sending once; an accepted document is never sent again (the IRN, built from the document number, the Service ID and the date, is the same on every try). Payment status is sent as it is when the document is submitted; later payments are not reported to NRS.

`EINVOICING_DRIVER=log` runs a simulator that "accepts" documents locally (never on production), for trying the screens without NRS. With no keys, or no address, nothing is sent. NRS is listed under data processors on the privacy page.

**Unverified: check before going live.** NRS's own documents could not be read when this was built (they need JavaScript), so these come from third-party descriptions, including a partner's gateway document that differs from the direct MBS description in places:

- the base URLs for sandbox and live;
- the header names for the key and secret (`x-api-key`, `x-api-secret`) and whether other headers or a token step are needed;
- the endpoint paths for submit (sign), confirm and the test call, and whether MBS uses one call or separate validate, sign and transmit steps;
- what a success answer looks like: where the IRN, CSID and QR (base64 PNG) sit in the JSON (several likely names are tried), and whether the QR must be built from the public key and certificate instead;
- what a refusal looks like (the message is read from `error.public_message`, `errorMessage`, `details`, `message`) and the HTTP codes (4xx other than 401, 403, 408, 425 and 429 is treated as a refusal; 401, 403, 408, 425, 429 and 5xx as "try again");
- the invoice JSON: field names, `invoice_kind` (B2B, B2C), `due_date`, `tax_point_date`, `billing_reference` for credit notes, the tax category names (`STANDARD_VAT`, `ZERO_VAT`, `EXEMPTED`), the unit `EA`, and fields MyBooks does not hold and leaves out (LGA, HSN code, product category);
- the type codes: 380 for an invoice and 381 for a credit note (the UBL standard; one partner document lists them the other way round);
- the IRN format `NUMBER-SERVICEID-YYYYMMDD` and that only letters and digits of the document number are kept;
- whether NRS treats a repeated IRN as an error (so a lost reply is checked with Confirm before it is sent again), and whether B2C reports use the same call as B2B;
- the timeline and the N50,000 B2C limit, and whether the 24-hour clock starts at issue.

## Useful commands

| Command | What it does |
|---|---|
| `php artisan accounts:recalculate --dry-run` | Compares stored account balances with the journals; without `--dry-run` it corrects them |
| `php artisan subscriptions:expire` | Expires ended subscriptions and sends reminders (runs daily) |
| `php artisan subscriptions:auto-renew` | Charges saved cards for subscriptions ending tomorrow, retries failed charges, warns about expiring cards (runs daily at 06:00) |
| `php artisan messages:send-queued` | Sends SMS / WhatsApp messages held over the night and retries failed ones (runs every 5 minutes) |
| `php artisan einvoice:retry` | NRS e-invoicing: follows up Pending documents, sends Failed ones again, warns about unreported B2C invoices (runs hourly) |
| `php artisan bankfeeds:sync` | Reads new transactions for every linked bank account and removes abandoned link attempts (runs every 3 hours) |
| `php artisan subscriptions:grace --days=14` | Gives active subscriptions time to renew (one-off) |
| `php artisan bills:receive-pending-stock --dry-run` | Once after the October 2026 update: brings in stock for posted, unpaid bills (stock used to wait for payment) |
| `php artisan mybooks:backup` | Backs up the database and uploaded files now (`--only-db` for the database alone) |
| `php artisan mybooks:ensure-admin-roles` | Makes sure every organisation's first user has the admin role |

## Project notes

- `docs/API.md`: the mobile API.
- `docs/fix.md` and `docs/fixui.md`: earlier review notes. Where they disagree with this README, the README is current.
- `.github/copilot-instructions.md`: conventions for contributors and coding assistants (tenancy, journals, permissions).

## Contributing

- One change per commit. Start the message with the finding ID where there is one ("Fix M7: ..."), say what was wrong and what changed, in plain words.
- Every bug fix comes with a test that fails without the fix.
- Open a pull request; GitHub Actions runs the tests, Pint and PHPStan on it.
