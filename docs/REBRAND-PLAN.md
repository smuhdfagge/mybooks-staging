# MyBooks rebrand plan

The full plan (with reasons, colour swatches and readability checks) is in
"MyBooks Rebrand Plan v2" (Word/PDF). This file is the working copy: the
rules every session follows and the status table each session updates.

## The look in one paragraph

One main colour, MyBooks navy (`brand-600`, `#1F4E79`), flat fills only, greys
for backgrounds and text, and a muted ochre accent (`accent-*`) for small
highlights only. Colour always means the same thing: green is done or money
in, red is a problem, amber is waiting, navy is open or information, grey is
draft. No indigo, purple, violet, fuchsia or pink, and no gradients, glows or
glass effects. Font: IBM Plex Sans (bundled, no outside font service). Logo:
Option A, "Ledger total".

## Where the colours live

- `tailwind.config.js`: the `brand` and `accent` scales.
- `config/brand.php`: the same values for PDFs, emails, charts, the manifest.
  `BrandColoursTest` checks the two agree.
- `resources/css/app.css`: shared names. Use these rather than raw colours:
  `btn-primary`, `btn-secondary`, `btn-danger`, `link`, `focus-ring`,
  `badge` with `badge-success | badge-danger | badge-warning | badge-info |
  badge-muted | badge-accent`, `nav-active`, `form-control`, `card`.

Never white text on ochre (fails readability). In dark mode, navy text uses
`dark:text-brand-300`.

## The routine for every session

1. Branch from the latest `rebrand/main`, e.g. `rebrand/r3-sales`.
2. Before screenshots of the session's main pages (desktop, phone, dark).
3. `php artisan rebrand:colours <folders>` to see the report, then again
   with `--apply`.
4. Fix every line the report lists "to decide" by hand (rules below).
5. Look at each changed page in light and dark mode; no sideways scroll on a phone.
6. Take the session's files off `tests/Feature/Rebrand/rebrand-todo.txt`.
   (The guard test fails if a finished file is still listed.)
7. Full test suite, Pint twice, PHPStan.
8. After screenshots next to the before ones in the pull request.
9. Pull request into `rebrand/main`; fill in the status table below.

Nothing goes live until R12 merges `rebrand/main` into `main` in one go.

## Replacement rules (what the command does)

| Old | New | Action |
|---|---|---|
| `indigo-N`, `blue-N` | `brand-N` | Automatic |
| `dark:text-…-400/500/600` (old families) | `dark:text-brand-300` | Automatic (readable on dark grey) |
| Navy text (`text-brand-500/600/700`) with no dark-mode colour and no light background of its own | adds `dark:text-brand-300` (and `dark:hover:text-brand-200`) | Automatic |
| `purple-N`, `violet-N` | `brand-N` | Automatic if the file has no indigo or blue, else flagged |
| `fuchsia`, `pink`, `rose`, `sky`, `cyan` | decide | Flagged |
| Gradients (`bg-gradient-*`, `from-/via-/to-`, `linear-gradient`) | flat `brand-600`, or `brand-900` for a big dark header | Flagged, line left alone |
| `#4F46E5`, `#3B82F6`, `#2563EB` | `#1F4E79` | Automatic |
| `#6366F1` | `#3A6798` | Automatic |
| `#1D4ED8`, `#4338CA` | `#183E61` | Automatic |
| `#DBEAFE`, `#E0E7FF` / `#EEF2FF`, `#EFF6FF` | `#D9E4EF` / `#EEF3F8` | Automatic |
| Purple and gradient hex codes (`#667EEA`, `#764BA2`, `#8B5CF6`…) | decide | Flagged |
| Coloured `rgb()` / `rgba()` | check against `config/brand.php` | Flagged |

Green, red, amber, yellow and grey are left alone. In status badges, yellow
becomes amber (done by hand in R2).

When deciding a flagged line: a second colour that must look different from
navy becomes accent (ochre) or grey; a decorative purple becomes navy; a
gradient becomes flat navy.

## Sessions and status

| # | Session | Branch | Status | Notes / hand-over |
|---|---|---|---|---|
| R0 | Logo choice | | Done | Option A "Ledger total". Files in MyBooks-Logo-OptionA.zip |
| R1 | Foundations | `rebrand/r1-foundations` | Done, PR #43 | Colours, config/brand.php, shared styles, font, `rebrand:colours`, guard test with 362 files to do. Nothing on screen changes yet except buttons and form fields that already used the shared styles. |
| R2 | Logo, icons, app frame, dashboard | `rebrand/r2-frame` | Done, PR #44 | New logo, favicon and phone icons in public/ (logo SVGs in public/images/brand). One shared icon block (partials/pwa-head). Manifest: navy, maskable icons, removed the two screenshots that never existed. Service worker cache v4. Old icon command and Breeze navigation removed. Sidebar navy with `nav-active`; admin sidebar marked "Admin". Status badge uses `badge-*`. Dashboard tiles coloured by meaning, quick actions all navy. Charts read `config('brand.chart')`. For later sessions: use `<x-brand-mark>` for the logo and `badge-*` for status pills. 337 files left on the to-do list. |
| R3 | Sales | `rebrand/r3-sales` | Done, PR #45 | 43 Sales screens. Payment method pills are now neutral grey (they are not statuses). Part-paid is amber, paid timeline dot green, deposit header flat green. Customer summary tiles wrap so amounts are not cut off. The command now also adds `dark:text-brand-300` to navy links and icons with no dark-mode colour (navy is unreadable on dark grey); rerun on R2 files too. Moved to R10: invoices/templates/preview and statements/document (printed documents). 294 files left. |
| R4 | Purchases and expenses | `rebrand/r4-purchases` | Done, PR #46 | 44 purchase screens. Payment method pills grey (as R3). Purchase order "billed" is green (done). Section header icons navy; timeline "inventory updated" grey, "paid" green. Payments made header flat navy (money out is not an error, so not red). Category stats: third tile ochre. Shared bulk-actions select can now shrink, so the Apply button is no longer cut off on 19 list pages. 250 files left. |
| R5 | Banking, accounting and tax | `rebrand/r5-banking` | Done, PR #47 | 51 screens in one go (no split needed). Purple tags (Year End, Compound tax, equity accounts) and tiles now ochre. Budget "vs actual" is a secondary button. Accounting period summary tiles navy except payments received (green). Note: WHT setup page on a phone: the rates table scrolls inside its box, page fits. 199 files left. |
| R6 | Stock and fixed assets | `rebrand/r6-stock` | Done, PR #48 | 38 screens. Stock value tiles, product tag and sub-category count in ochre. Reserved stock and accumulated depreciation amber instead of orange. Stock movement types coloured by direction: in and purchase green, out and sale red, return amber, adjustment and transfer navy, assembly grey. Item page quick actions use btn-primary / btn-secondary / btn-danger. 161 files left. |
| R7 | Payroll and HR | `rebrand/r7-payroll` | Done, PR #49 | 48 screens (payslip PDFs stay for R10). Purple buttons (Salary structures, Payslips, View payslip) are now secondary buttons. Section header icons navy; designation icons, employee tile and payslip links ochre. No demo employees in the screenshot database (no factory), so show pages were checked through the test suite only. 113 files left. |
| R8 | Report screens | `rebrand/r8-reports` | Done, PR #50 | 32 report screens (PDFs stay for R10). Reports index: all 30 report icons one soft navy tile instead of a different colour (and six gradients) each. Shared report export buttons (Print, PDF, CSV) are plain secondary buttons on all 20 reports that use them. Purple on statements and tax reports now ochre. Orange kept for overdue age bands (31-60, 61-90 days), as a scale between amber and red. 81 files left. |
| R9 | Settings, admin, system pages | `rebrand/r9-settings` | Done, PR #51 | 43 files in one go: settings, admin area (not sign-in), subscription page, imports, exports, activity log, profile, error pages, global search, Export model. Admin avatar and plan gradients now flat navy. Subscription page: paying is the main navy button, Change plan secondary. Invoice template editor "Set as default" secondary. Bug fixed on the way: admin "Days left" showed a negative decimal (-30.85 days); now whole days counting forward (AdminDaysLeftTest). Left for R10/R11 only: documents and PDFs, public and sign-in pages. 38 files left. |
| R10 | PDFs, print views, emails | `rebrand/r10-documents` | Done, PR open | 19 files plus emails and the export service. Report PDF layout reads its colours from config/brand.php; all PDF gradients flat; brand green (#2E7D32) and red (#C62828) in PDFs. Invoice templates: saved templates keep their colours (existing businesses still print in their saved colours); new defaults Classic navy, Modern deep navy; template headers flat in the business's own colour. Laravel mail layout published: MyBooks logo, navy buttons and links. Export PDFs navy. Checked: PDFs printed to black and white stay readable. 19 files left (all R11). |
| R11 | Public and sign-in pages | `rebrand/r11-public` | To do | Also offline page, API docs. Remove the Bunny font links and its CSP entries once no page uses them. Public pages can use `<x-brand-mark>`. Split point: after sign-in pages |
| R12 | Final check and go-live | | To do | To-do list must be empty |
