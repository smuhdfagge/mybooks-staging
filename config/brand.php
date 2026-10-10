<?php

/*
| MyBooks brand colours (rebrand R1).
|
| The same values are in tailwind.config.js. This file is for the places
| Tailwind can't reach: PDFs, emails, chart colours, the app manifest and
| the offline page. Change both together.
*/

return [

    'name' => 'MyBooks',

    // Navy: the brand colour is brand.600.
    'brand' => [
        50 => '#EEF3F8',
        100 => '#D9E4EF',
        200 => '#B4C8DD',
        300 => '#8AA9CB',
        400 => '#5F84B0',
        500 => '#3A6798',
        600 => '#1F4E79',
        700 => '#183E61',
        800 => '#132F4A',
        900 => '#102A43',
        950 => '#0A1B2D',
    ],

    // Ochre accent: small highlights only, never with white text on it.
    'accent' => [
        100 => '#FBEFD5',
        300 => '#E9BD67',
        400 => '#D79E36',
        500 => '#C0841A',
        700 => '#8A5A12',
    ],

    // Colours that carry a meaning everywhere in the app.
    'status' => [
        'success' => '#2E7D32', // paid, money in
        'danger' => '#C62828',  // overdue, loss, delete
        'warning' => '#B26A00', // waiting
        'info' => '#1F4E79',    // open, sent
        'muted' => '#6B7280',   // draft, inactive
    ],

    // Charts: income is always green and expenses always red. Other series
    // take these colours in order.
    // Chart colours (dashboard upgrade). Checked with a colour-blindness and
    // clarity test in both modes: navy itself is too dark and grey for bars,
    // so charts use a brighter "chart blue". Green and red are kept for
    // status only (done, problem), never for a data series.
    'chart' => [
        'income' => '#2F6AAE',
        'expense' => '#C0841A',
        'profit' => '#374151',
        'dark' => ['income' => '#5B8FD3', 'expense' => '#BF8020', 'profit' => '#D1D5DB'],
        // Customers owe you, by how late: current, then 1-30 / 31-60 / 61-90 / over 90 days (deeper = later).
        'ageing' => ['#2F6AAE', '#D79E36', '#B5781A', '#8A5A12', '#5C3D0D'],
        'ageing_dark' => ['#5B8FD3', '#E0AC50', '#C9902E', '#A8701A', '#8A5A12'],
        'series' => ['#1F4E79', '#C0841A', '#4E8A5B', '#8E3B46', '#5B6B7A', '#3E8E9E'],
        // Invoice status chart: same meanings as the status badges.
        'invoice_status' => [
            'draft' => '#9CA3AF',
            'sent' => '#3A6798',
            'unpaid' => '#B26A00',
            'partial' => '#D79E36',
            'paid' => '#2E7D32',
            'overdue' => '#C62828',
            'cancelled' => '#5B6B7A',
        ],
    ],

    // Browser bar colour on phones (meta theme-color and the app manifest).
    'theme_color' => '#1F4E79',

    // Plain colours for printed and PDF documents.
    'print' => [
        'heading' => '#102A43',
        'text' => '#1F2937',
        'muted' => '#6B7280',
        'rule' => '#D1D5DB',
        'panel' => '#F3F4F6',
    ],

];
