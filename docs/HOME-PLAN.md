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
| H3 | The new home page | 1, 3, 4, 8, 10, 11, 12 | `home/h3-home` | Done, PR #70 | All ten sections, on the shared layout. Scene and tour in `resources/js/home.js` (plays once when seen, reduced motion respected, arrow keys on the tabs); styles in `resources/css/home.css`. SMS, WhatsApp, bank feeds and e-invoicing wording follow `LiveFeatures`; with nothing live the page says "by email" and shows e-invoicing as "Being prepared". "Book a demo" opens the contact form with a new "Book a demo" subject picked. |
| H4 | Matching pages and the demo | 2, 5, 6 | `home/h4-pages` | Done, PR #71 | About, Contact, Support, Privacy and Terms on the shared layout (old `partials/public-nav` and `public-footer` removed). About rewritten in plain words, user limit from the plans table. Contact: "Book a demo" box, dead social links and the "within 24 hours" promise removed. Support: sentence case, no reply-time promise. Privacy and Terms: layout only, legal wording unchanged (decisions 5 and 6 left as the defaults). |
| H5 | Search, sharing and speed | none | `home/h5-search` | Done, PR #72 | Canonical address and share tags on every public page; share picture `public/images/share/mybooks-share.png` (1200 x 630, rebuilt with `scripts/share-image/build.mjs`); structured data on the home page (organisation, product with prices in naira from the plans table, the questions on the page); `/sitemap.xml` and `/robots.txt` generated (static robots.txt removed). Public pages load `public.js` (Alpine only, 44 KB) instead of the app script (350 KB): home page on a slow phone 742 KB to 441 KB, first paint 2.4 s to 1.6 s. Contact questions now open without a script. |
| H6 | Checks and go-live | none | `home/h6-golive` | Done, PR #74 | Accessibility check (axe, WCAG 2.1 A and AA) on all public pages, light, dark and phone: 3 findings fixed, now 0. Keyboard: every stop shows a focus ring. Plans click through to sign-up with the plan and cycle picked. Every public page 350 to 440 KB and first paint 1.4 to 1.7 s on a slow phone. Brings H5 into `main` (PR #73 went in before #72). Front-end build zip sent. |
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

## Go-live (H6)

1. Merge the H6 pull request into `main`.
2. On the server: `bash scripts/deploy.sh` (it pulls `main`, which also
   removes the old static `public/robots.txt`).
3. Upload the front-end build zip and unzip it over `public/build`. The
   new pages need it: `public.js`, `home.js` and `home.css` are new files.
4. In the server's `.env`, `APP_URL` must be the address people use
   (`https://staging.mybooks.cloud` for now), then run
   `php artisan config:cache`. Page addresses, the sitemap and the share
   pictures are built from it.
5. Check: the home page, `/robots.txt`, `/sitemap.xml`, and a link pasted
   into WhatsApp shows the share picture. (WhatsApp keeps old previews for
   a while; Facebook's Sharing Debugger refreshes them.)
6. If the staging address should not appear in Google, say so: robots.txt
   currently lets search engines in.

