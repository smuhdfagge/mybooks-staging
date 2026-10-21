{{--
    The home page (home page plan H3), built from the sample design.

    Every claim here was checked in the code. Optional features (SMS,
    WhatsApp, bank feeds, e-invoicing) are mentioned only while they are
    really live (LiveFeatures), and prices and limits come from the plans
    table (PublicPlans). The figures in the drawn tables are examples and are
    labelled so.
--}}
@php
    $live = app(\App\Support\PublicSite\LiveFeatures::class);
    $plans = app(\App\Support\PublicSite\PublicPlans::class)->all();

    $wa = $live->whatsapp();
    $sms = $live->sms();
    $feeds = $live->bankFeeds();
    $nrs = $live->eInvoicing();

    // How customers get reminded, in words, from what is really switched on.
    $remindVia = match (true) {
        $wa && $sms => 'on WhatsApp or by SMS',
        $wa => 'on WhatsApp',
        $sms => 'by SMS',
        default => 'by email',
    };
    $channels = array_merge(['email'], $sms ? ['SMS'] : [], $wa ? ['WhatsApp'] : []);
    $channelList = count($channels) > 1
        ? implode(', ', array_slice($channels, 0, -1)).' and '.end($channels)
        : $channels[0];
    $reminder = $wa
        ? ['dot' => 'W', 'head' => 'WhatsApp reminder, sent by MyBooks']
        : ($sms ? ['dot' => 'S', 'head' => 'SMS reminder, sent by MyBooks'] : ['dot' => '@', 'head' => 'Email reminder, sent by MyBooks']);

    $maxUsers = (int) $plans->max('users');

    // "Yearly costs the same as ten months", only when that is true of every
    // plan that offers both.
    $both = $plans->filter(fn ($p) => $p['monthly'] !== null && $p['annual'] !== null);
    $months = $both->map(fn ($p) => round($p['annual'] / $p['monthly'], 2))->unique();
    $yearlyNote = $both->isNotEmpty() && $months->count() === 1 && fmod((float) $months->first(), 1.0) === 0.0 && $months->first() < 12
        ? 'Yearly costs the same as '.\Illuminate\Support\Number::spell((int) $months->first()).' months.'
        : null;
    $cycleNote = match (true) {
        $plans->isNotEmpty() && $plans->every(fn ($p) => $p['annual_only']) => 'Paid yearly.',
        $plans->isNotEmpty() && $plans->every(fn ($p) => $p['monthly_only']) => 'Paid monthly.',
        default => 'Pay monthly or yearly.',
    };

    $demoUrl = route('contact', ['subject' => 'demo']).'#contact-form';

    // Questions: shown on the page and given to search engines, from one list.
    $privacyLink = '<a href="'.e(route('privacy-policy')).'">privacy policy</a>';
    $faq = [
        ['Can I try MyBooks before I pay?', 'Book a demo and we will walk you through MyBooks and answer your questions before you choose a plan.'],
        ['Does it work for VAT and WHT in Nigeria?', 'Yes. VAT at 7.5% is added on invoices and bills, and your monthly VAT return is filled in from them. WHT is taken off supplier payments and grouped by tax authority.'],
        ['Can my accountant use it too?', 'Yes. Add your accountant as a user with the accountant role. They get the ledger, the reports and the month-end tools.'],
        ['Can I use it on my phone?', "Yes. MyBooks works in your phone's browser, and lists turn into easy-to-read cards on a small screen."],
        ['What happens to my data if I stop?', 'Download a full backup of your books before you go. Your records are shared only with the services listed in our '.$privacyLink.'.'],
        ['How do I pay for MyBooks?', 'Through Paystack.'.(config('mybooks.features.auto_renewal') ? ' You can save a card so your plan renews by itself, or pay each time.' : '')],
    ];

    // Structured data (H5): who we are, the product with its prices in
    // naira straight from the plans table, and the questions above.
    $offers = [];
    foreach ($plans as $p) {
        foreach (['monthly' => 'month', 'annual' => 'year'] as $cycle => $unit) {
            if ($p[$cycle] !== null) {
                $offers[] = [
                    '@type' => 'Offer',
                    'name' => $p['name'].', paid every '.$unit,
                    'price' => number_format($p[$cycle], 2, '.', ''),
                    'priceCurrency' => 'NGN',
                    'url' => $p[$cycle.'_url'],
                ];
            }
        }
    }
    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => array_values(array_filter([
            [
                '@type' => 'Organization',
                '@id' => route('home').'#org',
                'name' => 'MyBooks',
                'url' => route('home'),
                'logo' => asset('images/brand/mybooks-logo-email-300.png'),
                'email' => config('mybooks.support_email'),
                // Where the business actually is. Google uses this to place
                // MyBooks in Nigeria rather than guessing from the text.
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => config('mybooks.office.street'),
                    'addressLocality' => config('mybooks.office.locality'),
                    'addressRegion' => config('mybooks.office.region'),
                    'addressCountry' => config('mybooks.office.country'),
                ],
            ],
            [
                '@type' => 'SoftwareApplication',
                'name' => 'MyBooks',
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'Web browser',
                'description' => 'Accounting software for Nigerian businesses: invoices, VAT, WHT, PAYE, payroll, stock and reports.',
                'url' => route('home'),
                'publisher' => ['@id' => route('home').'#org'],
                'offers' => $offers ?: null,
            ],
            [
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn ($q) => [
                    '@type' => 'Question',
                    'name' => $q[0],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($q[1])],
                ], $faq),
            ],
        ])),
    ];
    $schema['@graph'][1] = array_filter($schema['@graph'][1], fn ($v) => $v !== null);
    $check = '<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8.5l3 3 7-7" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
@endphp

<x-public.layout
    title="Accounting software for Nigerian businesses"
    description="Invoices, VAT, WHT, PAYE, payroll, stock and reports for Nigerian businesses, in one place. Prices in naira, paid through Paystack."
    :schema="$schema"
    class="mb-home">
    <x-slot:head>
        @vite(['resources/css/home.css', 'resources/js/home.js'])
    </x-slot:head>

    {{-- 1. Opening scene --}}
    <section class="hero" aria-labelledby="home-title">
        <div class="wrap">
            <div>
                <h1 id="home-title">Invoices out, money in, tax worked out.</h1>
                <p class="lede">MyBooks is accounting software for Nigerian businesses. Send invoices, remind customers {{ $remindVia }}, keep your stock right and pay your staff. Your VAT, WHT, PAYE and pension figures are ready when they fall due.</p>
                <div class="hero-cta">
                    <a class="btn btn-main" href="#pricing">Choose a plan</a>
                    <a class="btn btn-quiet" href="{{ $demoUrl }}">Book a demo</a>
                </div>
                <p class="hero-note"><strong>Prices in naira.</strong> Pay through Paystack.@if ($maxUsers > 1) Up to {{ $maxUsers }} people on one account.@endif</p>
            </div>

            <div class="scene" data-scene data-cash-from="4218400" data-amount="1531875" data-paid-text="+₦1,531,875.00 from Danjuma Foods">
                <p class="sr-only">Example: an invoice is sent, the customer is reminded {{ $remindVia }}, and the payment arrives in the bank.</p>
                <div class="panel inv" aria-hidden="true">
                    <div class="inv-top">
                        <div><div class="inv-no">Invoice INV-0142</div><div class="inv-to">Danjuma Foods, due 14 Oct 2026</div></div>
                        <span class="pill paid" data-scene-pill>Paid</span>
                    </div>
                    <table>
                        <thead><tr><th>Item</th><th class="n">Qty</th><th class="n">Amount</th></tr></thead>
                        <tbody>
                            <tr><td>Rice 50kg</td><td class="n">20</td><td class="n">1,040,000.00</td></tr>
                            <tr><td>Groundnut oil 25L</td><td class="n">10</td><td class="n">385,000.00</td></tr>
                            <tr><td>VAT 7.5%</td><td class="n"></td><td class="n">106,875.00</td></tr>
                        </tbody>
                        <tfoot><tr><td>Total</td><td></td><td class="n">₦1,531,875.00</td></tr></tfoot>
                    </table>
                </div>

                <div class="panel remind" aria-hidden="true">
                    <div class="remind-head"><span class="remind-dot">{{ $reminder['dot'] }}</span>{{ $reminder['head'] }}</div>
                    <div class="bubble">Good morning Danjuma Foods. Invoice <b>INV-0142</b> for <b>₦1,531,875.00</b> was due on 14 Oct. You can pay to Access Bank 0123456789. Thank you, Kano Traders.<small>09:02</small></div>
                </div>

                <div class="panel cash" aria-hidden="true">
                    <div class="cash-label">Cash in bank</div>
                    <div class="cash-fig" data-scene-cash>₦5,750,275.00</div>
                    <div class="cash-in" data-scene-cash-in>+₦1,531,875.00 from Danjuma Foods</div>
                    <div class="spark"><i class="h40"></i><i class="h52"></i><i class="h46"></i><i class="h61"></i><i class="h55"></i><i class="h58 h92" data-scene-bar></i></div>
                </div>
                <button class="replay" type="button" data-scene-replay hidden>Play again</button>
            </div>
        </div>
    </section>

    {{-- 2. Four facts --}}
    <div class="facts">
        <ul>
            <li><strong>Built for Nigeria</strong>VAT, WHT, PAYE and pension rules</li>
            <li><strong>Everything in one place</strong>Sales, purchases, stock, payroll, reports</li>
            <li><strong>Works on your phone</strong>Check money owed from anywhere</li>
            <li><strong>Your data is yours</strong>Download a full copy any time</li>
        </ul>
    </div>

    {{-- 3. Product tour --}}
    <section class="section" id="tour" aria-labelledby="tour-title">
        <div class="wrap">
            <div class="section-head">
                <h2 id="tour-title">The day-to-day work, done in one place</h2>
                <p>Pick a part of your business to see what MyBooks does for it.</p>
            </div>
            <div class="tour">
                <div class="tabs" role="tablist" aria-label="Parts of the business" data-tour-tabs>
                    <button type="button" class="tab" role="tab" aria-selected="true" aria-controls="p-sell" id="t-sell"><strong>Selling and getting paid</strong><span>Quotes, invoices, receipts, reminders</span></button>
                    <button type="button" class="tab" role="tab" aria-selected="false" aria-controls="p-buy" id="t-buy" tabindex="-1"><strong>Buying and bills</strong><span>Purchase orders, bills, supplier payments</span></button>
                    <button type="button" class="tab" role="tab" aria-selected="false" aria-controls="p-stock" id="t-stock" tabindex="-1"><strong>Stock and warehouses</strong><span>Every bag and carton, in every store</span></button>
                    <button type="button" class="tab" role="tab" aria-selected="false" aria-controls="p-pay" id="t-pay" tabindex="-1"><strong>Staff and payroll</strong><span>Payslips, PAYE, pension, leave</span></button>
                    <button type="button" class="tab" role="tab" aria-selected="false" aria-controls="p-rep" id="t-rep" tabindex="-1"><strong>Reports</strong><span>Profit, cash, what you owe and are owed</span></button>
                </div>

                <div class="panel stage">
                    <div role="tabpanel" id="p-sell" aria-labelledby="t-sell" tabindex="0">
                        <h3>Know who owes you, and get it in</h3>
                        <ul class="points">
                            <li>Quote, sales order, delivery note, invoice: one record follows the sale.</li>
                            <li>Payment reminders by {{ $channelList }}, and customer statements by email.</li>
                            <li>Part payments, deposits, credit notes and refunds, all kept straight.</li>
                        </ul>
                        <table class="mini">
                            <thead><tr><th>Invoice</th><th class="hide-xs">Customer</th><th class="hide-sm">Due</th><th>Status</th><th class="n">Balance due</th></tr></thead>
                            <tbody>
                                <tr><td class="doc">INV-0142</td><td class="hide-xs">Danjuma Foods</td><td class="hide-sm">14 Oct 2026</td><td><span class="pill late">Overdue</span></td><td class="n">1,531,875.00</td></tr>
                                <tr><td class="doc">INV-0141</td><td class="hide-xs">Hauwa Traders</td><td class="hide-sm">30 Oct 2026</td><td><span class="pill sent">Sent</span></td><td class="n">212,500.00</td></tr>
                                <tr><td class="doc">INV-0139</td><td class="hide-xs">Bala Stores</td><td class="hide-sm">2 Oct 2026</td><td><span class="pill paid">Paid</span></td><td class="n">—</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div role="tabpanel" id="p-buy" aria-labelledby="t-buy" tabindex="0" hidden>
                        <h3>Pay suppliers on time, and no more than you owe</h3>
                        <ul class="points">
                            <li>Purchase orders become bills when the goods arrive.</li>
                            <li>WHT is worked out when you pay a supplier, and shown on the payment.</li>
                            <li>Repeat bills like rent and internet are made for you on schedule.</li>
                        </ul>
                        <table class="mini">
                            <thead><tr><th>Bill</th><th class="hide-xs">Supplier</th><th class="hide-sm">Due</th><th>Status</th><th class="n">To pay</th></tr></thead>
                            <tbody>
                                <tr><td class="doc">BILL-0360</td><td class="hide-xs">Olam Nigeria</td><td class="hide-sm">24 Oct 2026</td><td><span class="pill sent">Unpaid</span></td><td class="n">298,635.00</td></tr>
                                <tr><td class="doc">BILL-0359</td><td class="hide-xs">KEDCO</td><td class="hide-sm">23 Oct 2026</td><td><span class="pill sent">Unpaid</span></td><td class="n">100,190.00</td></tr>
                                <tr><td class="doc">BILL-0342</td><td class="hide-xs">Flour Mills</td><td class="hide-sm">12 Sep 2026</td><td><span class="pill paid">Paid</span></td><td class="n">—</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div role="tabpanel" id="p-stock" aria-labelledby="t-stock" tabindex="0" hidden>
                        <h3>Stock that matches the shelf</h3>
                        <ul class="points">
                            <li>Stock goes down when you sell and up when goods arrive, in each warehouse.</li>
                            <li>Move goods between stores with a stock transfer.</li>
                            <li>Make products from parts with a bill of materials.</li>
                        </ul>
                        <table class="mini">
                            <thead><tr><th>Item</th><th class="n hide-xs">Kano</th><th class="n hide-sm">Kaduna</th><th class="n">In stock</th><th>Status</th></tr></thead>
                            <tbody>
                                <tr><td>Rice 50kg</td><td class="n hide-xs">80</td><td class="n hide-sm">40</td><td class="n">120</td><td><span class="pill paid">In stock</span></td></tr>
                                <tr><td>Groundnut oil 25L</td><td class="n hide-xs">6</td><td class="n hide-sm">—</td><td class="n">6</td><td><span class="pill late">Running low</span></td></tr>
                                <tr><td>Semovita 10kg</td><td class="n hide-xs">45</td><td class="n hide-sm">15</td><td class="n">60</td><td><span class="pill paid">In stock</span></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div role="tabpanel" id="p-pay" aria-labelledby="t-pay" tabindex="0" hidden>
                        <h3>Payroll without the spreadsheet</h3>
                        <ul class="points">
                            <li>PAYE and pension worked out for each person from the current tax bands.</li>
                            <li>Payslips for everyone in one download, and a bank file to pay salaries.</li>
                            <li>Leave requests and approvals in the same place.</li>
                        </ul>
                        <table class="mini">
                            <thead><tr><th>Employee</th><th class="n hide-sm">Gross</th><th class="n">PAYE</th><th class="n hide-sm">Pension</th><th class="n">Net pay</th></tr></thead>
                            <tbody>
                                <tr><td>Aisha Bello</td><td class="n hide-sm">195,000.00</td><td class="n">22,950.00</td><td class="n hide-sm">15,600.00</td><td class="n">156,450.00</td></tr>
                                <tr><td>Chinedu Okafor</td><td class="n hide-sm">87,000.00</td><td class="n">4,550.00</td><td class="n hide-sm">6,960.00</td><td class="n">75,490.00</td></tr>
                                <tr><td>Yusuf Ahmed</td><td class="n hide-sm">435,000.00</td><td class="n">63,000.00</td><td class="n hide-sm">34,800.00</td><td class="n">337,200.00</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div role="tabpanel" id="p-rep" aria-labelledby="t-rep" tabindex="0" hidden>
                        <h3>See the business clearly, any day of the month</h3>
                        <ul class="points">
                            <li>Profit and loss, balance sheet, cash flow and trial balance, at any date.</li>
                            <li>Who owes you and whom you owe, by how late it is.</li>
                            <li>Print, or download as PDF or CSV for your accountant.</li>
                        </ul>
                        <table class="mini">
                            <thead><tr><th>Profit and loss, Oct 2026</th><th class="n">This month</th><th class="n hide-sm">Last month</th></tr></thead>
                            <tbody>
                                <tr><td>Sales</td><td class="n">5,239,343.00</td><td class="n hide-sm">4,880,120.00</td></tr>
                                <tr><td>Cost of goods sold</td><td class="n">(3,410,000.00)</td><td class="n hide-sm">(3,190,500.00)</td></tr>
                                <tr><td>Running costs</td><td class="n">(1,102,700.00)</td><td class="n hide-sm">(1,045,300.00)</td></tr>
                                <tr><td><b>Profit</b></td><td class="n"><b>726,643.00</b></td><td class="n hide-sm"><b>644,320.00</b></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="example">Example figures for a sample business.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- 4. Nigerian tax --}}
    <section class="section rules" id="rules" aria-labelledby="rules-title">
        <div class="wrap">
            <div class="section-head">
                <h2 id="rules-title">Nigerian tax, already set up</h2>
                <p>The rules are in MyBooks from day one, so the figures are there when your accountant or the tax office asks.</p>
            </div>
            <table class="ledger">
                <thead><tr><th scope="col">Tax</th><th scope="col">What MyBooks does</th><th scope="col">Where you see it</th></tr></thead>
                <tbody>
                    <tr><td>VAT<small>7.5% on sales and purchases</small></td><td>Adds VAT to invoices and bills, and fills in your monthly VAT return from them.</td><td>VAT return (Form 002), ready to print</td></tr>
                    <tr><td>Withholding tax<small>On supplier payments</small></td><td>Takes WHT off when you pay a supplier, groups it by tax authority, and tracks the WHT your customers take off you until you get the credit note.</td><td>WHT payable schedule and WHT credit notes</td></tr>
                    <tr><td>PAYE and pension<small>Nigeria Tax Act 2025 bands</small></td><td>Works out each person's tax and pension every payroll run, and what the business pays on top.</td><td>Payslips, and the tax and pension to pay each month</td></tr>
                    <tr><td>E-invoicing<small>NRS Merchant Buyer Solution</small></td>
                        <td>@if ($nrs) Sends your invoices to the tax office's system once your business is set up for it. @else Sends your invoices to the tax office's system when your business is required to join.<br><span class="soon">Being prepared</span> @endif</td>
                        <td>Settings, E-invoicing</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    {{-- 5. Owner and accountant --}}
    <section class="section" id="accountants" aria-labelledby="acc-title">
        <div class="wrap">
            <div class="section-head">
                <h2 id="acc-title">One set of books for you and your accountant</h2>
            </div>
            <div class="split">
                <div>
                    <h3>For the owner</h3>
                    <p>Open MyBooks on your phone and see what needs you today.</p>
                    <ul class="checks">
                        <li>{!! $check !!}<span>Money in, money out and profit for the month at a glance</span></li>
                        <li>{!! $check !!}<span>Overdue invoices, bills due this week, stock running low</span></li>
                        <li>{!! $check !!}<span>Give each staff member only the parts they need</span></li>
                    </ul>
                    <div class="panel attn" role="img" aria-label="Example of the dashboard's Needs your attention list: overdue invoices, the VAT return due, and items low on stock.">
                        <div class="attn-row" aria-hidden="true"><i class="bad"></i><div><b>24 invoices overdue</b><span>₦1,716,646.00 waiting.@if ($sms || $wa) Send reminders {{ $wa && $sms ? 'by SMS or WhatsApp' : $remindVia }}.@endif</span></div></div>
                        <div class="attn-row" aria-hidden="true"><i></i><div><b>VAT return for September due 21 Oct</b><span>Review it and mark it filed once submitted.</span></div></div>
                        <div class="attn-row" aria-hidden="true"><i></i><div><b>7 items low on stock</b><span>Groundnut oil 25L, Semovita 10kg and more.</span></div></div>
                    </div>
                </div>
                <div>
                    <h3>For the accountant</h3>
                    <p>Proper double-entry books, so month-end is a check, not a rebuild.</p>
                    <ul class="checks">
                        <li>{!! $check !!}<span>Every sale, bill and payment posts its own journal, so the trial balance always balances</span></li>
                        <li>{!! $check !!}<span>A general ledger that links each figure to its document</span></li>
                        <li>{!! $check !!}<span>Lock a month once it is closed, so nobody changes it by mistake</span></li>
                        <li>{!! $check !!}<span>Bank reconciliation{{ $feeds ? ' with bank feeds' : '' }}, fixed assets and depreciation, prepaid and deferred schedules</span></li>
                        <li>{!! $check !!}<span>Budgets, on the Professional and Enterprise plans</span></li>
                        <li>{!! $check !!}<span>An activity log of who changed what, and when</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- 6. Moving in --}}
    <section class="section band" aria-labelledby="move-title">
        <div class="wrap move">
            <div>
                <div class="section-head">
                    <h2 id="move-title">Moving from Excel or another system</h2>
                    <p>Bring what you already have, from a spreadsheet.</p>
                </div>
                <ol class="steps">
                    <li><div><b>Choose a plan and set up your business</b><span>Name, logo, VAT number, bank accounts.</span></div></li>
                    <li><div><b>Import your lists and opening balances</b><span>Upload a spreadsheet; MyBooks shows you each column before it saves.</span></div></li>
                    <li><div><b>Send your first invoice</b><span>From your laptop or your phone.</span></div></li>
                </ol>
            </div>
            <div class="panel imports">
                <h3>What you can import from a spreadsheet</h3>
                <ul class="chips">
                    @foreach (['Customers', 'Suppliers', 'Items', 'Chart of accounts', 'Opening balances', 'Invoices', 'Bills', 'Expenses', 'Employees', 'Journals', 'Budgets'] as $chip)
                        <li>{{ $chip }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- 7. Safety --}}
    <section class="section" aria-labelledby="safe-title">
        <div class="wrap">
            <div class="section-head"><h2 id="safe-title">Your books stay safe and private</h2></div>
            <div class="trust">
                <div><b>Encrypted connection</b><span>Everything between your browser and MyBooks travels over HTTPS.</span></div>
                <div><b>Two-step sign-in</b><span>Add a code from your phone to every sign-in.</span></div>
                <div><b>Each business kept apart</b><span>Your records are only shown to your own team.</span></div>
                <div><b>Roles for every person</b><span>Sales staff see sales; payroll stays with the people you choose.</span></div>
                <div><b>Activity log</b><span>Every change is recorded with who made it.</span></div>
                <div><b>Full backup</b><span>Download all your books whenever you like.</span></div>
            </div>
        </div>
    </section>

    {{-- 8. Plans --}}
    <section class="section band" id="pricing" aria-labelledby="price-title">
        <div class="wrap">
            <div class="section-head price-top">
                <h2 id="price-title">Plans in naira</h2>
                <p>{{ $cycleNote }}@if ($yearlyNote) {{ $yearlyNote }}@endif</p>
            </div>
            <x-public.pricing highlight="professional" highlight-label="Best for a growing business" />
        </div>
    </section>

    {{-- 9. Questions --}}
    <section class="section" id="faq" aria-labelledby="faq-title">
        <div class="wrap">
            <div class="section-head"><h2 id="faq-title">Questions people ask</h2></div>
            <div class="faq">
                @foreach ($faq as [$question, $answer])
                    <details @if ($loop->first) open @endif><summary>{{ $question }}</summary><p>{!! $answer !!}</p></details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 10. Closing --}}
    <section class="section close" aria-labelledby="close-title">
        <div class="wrap">
            <h2 id="close-title">Ready to put your books in order?</h2>
            <div>
                <div class="hero-cta">
                    <a class="btn btn-main" href="#pricing">Choose a plan</a>
                    <a class="btn btn-quiet" href="{{ $demoUrl }}">Book a demo</a>
                </div>
                <p class="hero-note">Prices in naira. Pay through Paystack.</p>
            </div>
        </div>
    </section>
</x-public.layout>
