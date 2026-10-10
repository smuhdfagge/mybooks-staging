<?php

/*
 * Dashboard cards and the permission that shows each one (dashboard upgrade).
 *
 * The key is the permission name before " dashboard-widgets". The roles
 * screen lists the cards from here, with these names and descriptions, so
 * what an owner ticks there matches what people see on the dashboard.
 */
return [
    'widgets' => [
        'attention-list' => ['label' => 'Needs your attention', 'help' => 'Overdue invoices, bills due, VAT, bank lines to match. Each line only shows if the person can open that page.'],
        'cash-position' => ['label' => 'Cash in bank and cash flow', 'help' => 'Bank and cash balances, money in and out, next 30 days.'],
        'total-revenue' => ['label' => 'Income', 'help' => 'Income for the period, compared with the period before.'],
        'monthly-expenses' => ['label' => 'Expenses', 'help' => 'Everything spent in the period.'],
        'profit' => ['label' => 'Net profit', 'help' => 'Profit and margin for the period.'],
        'revenue-chart' => ['label' => 'Income and expenses chart', 'help' => 'Last 12 months with a profit line.'],
        'outstanding-receivables' => ['label' => 'Customers owe you', 'help' => 'Amount owed by how late, and the most overdue customers.'],
        'pending-bills' => ['label' => 'You owe', 'help' => 'Unpaid bills, soonest first.'],
        'top-customers' => ['label' => 'Top customers', 'help' => 'Biggest customers in the last 12 months.'],
        'expense-breakdown' => ['label' => 'Where the money goes', 'help' => 'Biggest expenses in the last 12 months.'],
        'recent-invoices' => ['label' => 'Recent activity', 'help' => 'The last things recorded in the books.'],
        'low-stock' => ['label' => 'Low stock alerts', 'help' => 'Items at or below their reorder level, in the attention list.'],
        'quick-actions' => ['label' => '"+ New" menu', 'help' => 'Shortcuts to create invoices, bills, expenses and more.'],
    ],

    // Cached figures are kept at most this long; they are also cleared as
    // soon as anything is posted to the books.
    'cache_seconds' => 600,
];
