# MyBooks home page plan

The full plan, with the sample design, is "MyBooks-Home-Page-Redesign-Plan"
(Word/PDF) and `mybooks-home-sample.html`. This file is the working copy:
the sessions, the rules, the decisions and the status of each session.

## Goal

A shorter, honest home page that shows the real product and leads with what
makes MyBooks different for a Nigerian business: VAT, WHT, PAYE and pension
handled for you, and customers reminded on WhatsApp. The page's one job: get
the right visitor to choose a plan, or to book a demo if they are not ready.

## The rules

- Every claim is checked in the code or confirmed by Fagge before it goes
  live. No made-up numbers, customers, quotes or certifications.
- Claims about optional features (SMS and WhatsApp, bank feeds, e-invoicing)
  show only when that feature is switched on and set up.
- Prices, limits and allowances come from the plans table, never typed in.
- Plain English, sentence case, buttons that say what happens.
- The app's colours and lettering (IBM Plex); one moving part (the opening
  scene), shown once; reduced motion respected.
- Phone first (390 px, no sideways scroll), dark mode, WCAG AA contrast,
  keyboard use.

## Branches

H1 goes straight into `main` (it fixes the live page). H2 to H5 branch from
`home/main` and merge into it; H6 merges `home/main` into `main`. Nothing
from H2 onwards goes live before H6.

## The routine for every session

1. Branch (H1 from `main`; the rest from the latest `home/main`).
2. Before screenshots (computer, phone, dark).
3. The work, with tests.
4. Readability check (light and dark), phone check, click-through.
5. Full test suite, Pint, PHPStan.
6. After screenshots; pull request; update the table below; wait for CI.
7. Before/after images and a short plain report to Fagge.

## Sessions and status

| # | Session | Needs decisions | Branch | Status | Notes |
|---|---|---|---|---|---|
| H1 | Honest claims on the current public pages | none | `home/h1-honest-claims` | Done, PR #67 | Take off the free-trial wording, the testimonials, "thousands of companies", "GDPR compliant", "daily backups", "bank-level encryption", and the About page's user, invoice, country and uptime figures. Into `main`, so it can be deployed on its own. |
| H2 | Foundations | 7, 9 | `home/h2-foundations` | Done, PR #68 | `x-public.layout`, `x-public.nav`, `x-public.footer` (not used by a page until H3/H4); IBM Plex Serif bundled for headings; `x-public.pricing` with a monthly/yearly switch, read from the plans table by `PublicPlans`; `LiveFeatures` says if SMS, WhatsApp, bank feeds and e-invoicing are really live. The guard test that the home page's prices match the table comes with H3, when the home page starts using the block. |
| H3 | The new home page | 1, 3, 4, 8, 10, 11, 12 | `home/h3-home` | To do | The ten sections from the plan: top bar, opening scene, four facts, product tour, Nigerian tax, owner and accountant, moving in, safety, plans, questions and closing. Phone and dark mode. |
| H4 | Matching pages and the demo | 2, 5, 6 | `home/h4-pages` | To do | About, Contact, Support, Terms and Privacy on the shared layout; the demo booking; data-protection and backup wording once confirmed. |
| H5 | Search, sharing and speed | none | `home/h5-search` | To do | Page titles and descriptions, share picture for WhatsApp and Facebook, structured data (product, prices in naira, questions), sitemap and robots, image sizes, speed check on a phone. |
| H6 | Checks and go-live | none | `home/h6-golive` | To do | Readability, phone, keyboard and speed checks on every public page; merge `home/main` into `main`; front-end build zip. |
| H7 | Measure (4 weeks) | none | none | To do | Clicks on "Choose a plan" and "Book a demo", payments finished, phone scrolling, tour tabs opened; a short report. |

## Decisions (from section 11 of the plan)

Unanswered decisions use the default shown, and the session report says so.

| # | Decision | Default until answered |
|---|---|---|
| 1 | Free trial, or pay first | Pay first; main button "Choose a plan"; demo offered |
| 2 | How "Book a demo" works | The contact form with "Book a demo" as the subject (chat was removed) |
| 3 | Real customer quotes | None on the page |
| 4 | Enterprise promises (24/7 phone, SLA, account manager) | Unchanged until answered |
| 5 | Data-protection statement | No statement (GDPR claim removed) |
| 6 | Daily backups | Not claimed; full backup download mentioned instead |
| 7 | Lock plan features, or change the plan lists | Plan lists shown as they are in the plans table |
| 8 | Opening line | "Invoices out, money in, tax worked out." |
| 9 | Headings lettering | IBM Plex Serif for headings |
| 10 | Tour pictures | Drawn tables |
| 11 | Help moving data in | Not offered on the page |
| 12 | SMS, WhatsApp, bank feeds live with real keys | Shown only when switched on and keys set |
