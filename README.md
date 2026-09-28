# MyBooks

MyBooks is online accounting and business software for small and medium businesses, built for Nigeria first (Naira, VAT, PAYE, pension). It runs at [mybooks.cloud](https://mybooks.cloud). Each business that signs up is a separate organisation (tenant) with its own users, roles and data.

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

The default `.env` uses SQLite. For MySQL or MariaDB set `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD`, then run `php artisan migrate`.

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
2. **Environment.** Copy `.env.example` to `.env` and set at least:
   - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `APP_KEY` (`php artisan key:generate`)
   - database and mail settings
   - `PAYSTACK_SECRET_KEY` and `PAYSTACK_PUBLIC_KEY` for subscription payments. In the Paystack dashboard, set the webhook URL to `https://<your domain>/billing/paystack/webhook`.
3. **Install and migrate.**
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   php artisan migrate --force
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
4. **Scheduler.** Add one cron entry. It expires subscriptions and sends renewal reminders, processes recurring transactions, and sends payment and stock reminders.
   ```
   * * * * * cd /path/to/mybooks && php artisan schedule:run >> /dev/null 2>&1
   ```
5. **Queue.** Emails, large payroll batches, imports and exports run on the queue. The scheduler (step 4) empties it every minute, so shared hosting needs nothing more. If Supervisor keeps `php artisan queue:work --timeout=600` running, you can set `QUEUE_WORK_FROM_SCHEDULER=false`. Keep `DB_QUEUE_RETRY_AFTER` above 600.
6. **First time billing is switched on.** Run `php artisan subscriptions:grace --days=14` once (try `--dry-run` first). Organisations already past their end date then get two weeks to pay instead of being locked out immediately.

Take a database backup before every deploy. Deploy one phase of changes at a time.

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

Other settings in `config/mybooks.php` include support email, import size limit, data retention periods, and HSTS. Leave `MYBOOKS_HSTS_INCLUDE_SUBDOMAINS` and `MYBOOKS_HSTS_PRELOAD` off unless every subdomain is permanently on HTTPS.

## Useful commands

| Command | What it does |
|---|---|
| `php artisan accounts:recalculate --dry-run` | Compares stored account balances with the journals; without `--dry-run` it corrects them |
| `php artisan subscriptions:expire` | Expires ended subscriptions and sends reminders (runs daily) |
| `php artisan subscriptions:grace --days=14` | Gives active subscriptions time to renew (one-off) |
| `php artisan mybooks:ensure-admin-roles` | Makes sure every organisation's first user has the admin role |

## Project notes

- `docs/API.md`: the mobile API.
- `docs/fix.md` and `docs/fixui.md`: earlier review notes. Where they disagree with this README, the README is current.
- `.github/copilot-instructions.md`: conventions for contributors and coding assistants (tenancy, journals, permissions).

## Contributing

- One change per commit. Start the message with the finding ID where there is one ("Fix M7: ..."), say what was wrong and what changed, in plain words.
- Every bug fix comes with a test that fails without the fix.
- Open a pull request; GitHub Actions runs the tests, Pint and PHPStan on it.
