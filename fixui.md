# MyBooks ERP — UI/UX Evaluation & Fix Tracker

> Generated: April 7, 2026 | Overall Score: **7.1 / 10**

---

## OVERALL UX SCORE

| #   | Dimension                | Weight   | Score | Weighted  |
| --- | ------------------------ | -------- | ----- | --------- |
| 1   | Global / Design System   | 15%      | 7.0   | 1.050     |
| 2   | Navigation & IA          | 10%      | 7.5   | 0.750     |
| 3   | Accounting Module        | 8%       | 7.5   | 0.600     |
| 4   | Human Resource Module    | 7%       | 6.0   | 0.420     |
| 5   | Payroll Module           | 8%       | 7.5   | 0.600     |
| 6   | Sales Module             | 8%       | 8.0   | 0.640     |
| 7   | Items / Inventory Module | 8%       | 6.5   | 0.520     |
| 8   | Purchases Module         | 8%       | 7.0   | 0.560     |
| 9   | Fixed Assets Module      | 7%       | 7.0   | 0.490     |
| 10  | Reports Module           | 8%       | 7.5   | 0.600     |
| 11  | Forms & Data Entry       | 8%       | 7.0   | 0.560     |
| 12  | Accessibility (WCAG 2.1) | 5%       | 5.5   | 0.275     |
|     | **OVERALL**              | **100%** |       | **7.065** |

---

## TOP 10 UX ISSUES (Ranked by User Impact)

| Rank   | Issue                                                           | Severity | Module        | Impact                                                                                 |
| ------ | --------------------------------------------------------------- | -------- | ------------- | -------------------------------------------------------------------------------------- |
| **1**  | Global search bar is non-functional (decorative only)           | Critical | Navigation    | Every user expects it to work — causes frustration and distrust                        |
| **2**  | No loading indicators on any Livewire table operations          | Critical | Global        | Filters, sorts, and pagination appear frozen — users double-click and cause errors     |
| **3**  | No breadcrumb navigation on any page                            | Major    | Navigation    | Users in deep flows (Invoice → Refund → Create) lose context                           |
| **4**  | No auto-save/draft on complex forms (invoices, bills, journals) | Major    | Forms         | Data loss on accidental navigation — very costly for 10+ field forms with dynamic rows |
| **5**  | Icon-only buttons lack `aria-label` across all table views      | Critical | Accessibility | Screen reader users cannot identify Edit/Delete/View actions                           |
| **6**  | No employee photos, org chart, or onboarding workflow           | Critical | HR            | HR module feels skeletal compared to the rest                                          |
| **7**  | Chart of Accounts has no tree/hierarchy view                    | Major    | Accounting    | Core accounting tool — flat list fails for large account structures                    |
| **8**  | No toast/notification system for real-time feedback             | Major    | Global        | Livewire actions (bulk operations) silently succeed or fail                            |
| **9**  | Items have no image support and no card/grid view               | Critical | Inventory     | Inventory-heavy businesses need visual item identification                             |
| **10** | No procurement pipeline (PO → GRN → Bill)                       | Critical | Purchases     | Major ERP feature gap — reduces purchase control and auditability                      |

---

## DIMENSION DETAILS

---

### 1. GLOBAL / DESIGN SYSTEM (15%) — Score: 7.0/10

#### UX Strengths

- Consistent Tailwind utility-first approach with `@tailwindcss/forms` plugin across all modules
- Coherent color system: Indigo primary, semantic greens/reds/yellows, Gray neutral palette
- Dark mode fully implemented (`darkMode: 'class'`) with `dark:` variants on virtually every element
- PWA support with manifest, service worker, iOS touch icons, install prompt
- Professional print stylesheet hides chrome and resets spacing
- Glass-morphism auth pages with animated floating shapes — polished first impression
- Component library: `<x-modal>`, `<x-searchable-select>`, `<x-bulk-actions>`, `<x-flash-messages>`, `<x-report-export-buttons>`, `<x-primary-button>`, `<x-danger-button>`

#### UX Issues Found

- **Critical — No skeleton screens or loading indicators on data tables.** Only registration wizard uses `wire:loading`. Every Livewire table renders with no visual feedback during filter/sort/pagination.
- **Major — No toast/snackbar notification system.** Flash messages are basic session-based banners requiring page reload to appear. No real-time toast for Livewire actions.
- **Major — No consistent empty state design tokens.** Each module implements its own ad-hoc empty state (different icons, text sizes, inconsistent CTA placement).
- **Minor — Typography scale is default Tailwind.** No custom `text-*` scale defined beyond `fontFamily`. No heading presets, no `prose` plugin.
- **Minor — Spacing rhythm relies entirely on developer discipline.** No design tokens for gap/padding/margin. Inconsistencies: some cards `p-4`, others `p-6`.

#### Recommendations

**1a. Add Livewire loading states globally:**

```html
{{-- resources/views/components/table-loading.blade.php --}}
<div
    wire:loading.delay
    class="absolute inset-0 bg-white/60 dark:bg-gray-900/60 z-10 flex items-center justify-center"
>
    <svg
        class="animate-spin h-8 w-8 text-indigo-600"
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
    >
        <circle
            class="opacity-25"
            cx="12"
            cy="12"
            r="10"
            stroke="currentColor"
            stroke-width="4"
        ></circle>
        <path
            class="opacity-75"
            fill="currentColor"
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
        ></path>
    </svg>
</div>
```

Wrap every Livewire table in a `relative` container and include `<x-table-loading />`.

**1b. Implement a toast notification system:**

```html
{{-- resources/views/components/toast.blade.php --}}
<div
    x-data="{ toasts: [] }"
    @toast.window="toasts.push({...$event.detail, id: Date.now()}); setTimeout(() => toasts.shift(), 5000)"
    class="fixed bottom-4 right-4 z-50 space-y-2"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-transition
            class="flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg text-white text-sm"
            :class="toast.type === 'success' ? 'bg-green-600' : toast.type === 'error' ? 'bg-red-600' : 'bg-blue-600'"
        >
            <span x-text="toast.message"></span>
        </div>
    </template>
</div>
```

**1c. Standardize empty states:**

```html
{{-- resources/views/components/empty-state.blade.php --}} @props(['icon' =>
'document', 'title', 'description' => '', 'actionUrl' => null, 'actionLabel' =>
null])
<div class="text-center py-12">
    <x-dynamic-component
        :component="'heroicon-o-' . $icon"
        class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500"
    />
    <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
        {{ $title }}
    </h3>
    @if($description)
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        {{ $description }}
    </p>
    @endif @if($actionUrl)<a
        href="{{ $actionUrl }}"
        class="mt-4 inline-flex ..."
        >{{ $actionLabel }}</a
    >@endif
</div>
```

---

### 2. NAVIGATION & INFORMATION ARCHITECTURE (10%) — Score: 7.5/10

#### UX Strengths

- Well-organized sidebar with 10 collapsible module groups
- Permission-gated menu items via `@can`
- Active state detection using `request()->routeIs()` with indigo highlighting
- Smooth Alpine.js `x-collapse` animations on expandable sections
- User info footer showing name and company context
- Mobile sidebar with backdrop blur and translate transitions
- Plan-specific feature flagging (Budgets shows "Pro" badge)

#### UX Issues Found

- **Critical — Global search bar in header is non-functional.** Static `<input type="search">` with no handler.
- **Major — No breadcrumb trails anywhere.** Deep pages have no breadcrumbs.
- **Major — No keyboard shortcut support.** No `Ctrl+K` command palette, no hotkeys.
- **Minor — Module grouping could be improved.** "Accountant" should be "Accounting".
- **Minor — Sidebar scroll position resets on navigation.**

#### Recommendations

**2a. Implement global search:**

```html
{{-- Replace static search input in header.blade.php --}}
<div
    x-data="{ open: false }"
    @keydown.window.ctrl.k.prevent="open = true; $nextTick(() => $refs.searchInput.focus())"
    class="hidden sm:flex flex-1 max-w-md"
>
    <livewire:global-search />
</div>
```

**2b. Add breadcrumbs:**

```html
{{-- resources/views/components/breadcrumbs.blade.php --}} @props(['items' =>
[]])
<nav class="flex mb-4" aria-label="Breadcrumb">
    <ol
        class="inline-flex items-center space-x-1 text-sm text-gray-500 dark:text-gray-400"
    >
        <li>
            <a href="{{ route('dashboard') }}" class="hover:text-indigo-600"
                >Dashboard</a
            >
        </li>
        @foreach($items as $item)
        <li class="flex items-center">
            <svg class="w-4 h-4 mx-1" fill="currentColor" viewBox="0 0 20 20">
                <path
                    fill-rule="evenodd"
                    d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                />
            </svg>
            @if($loop->last)
            <span class="text-gray-900 dark:text-gray-100 font-medium"
                >{{ $item['label'] }}</span
            >
            @else
            <a href="{{ $item['url'] }}" class="hover:text-indigo-600"
                >{{ $item['label'] }}</a
            >
            @endif
        </li>
        @endforeach
    </ol>
</nav>
```

---

### 3. ACCOUNTING MODULE UX (8%) — Score: 7.5/10

#### UX Strengths

- Journal entry form has dynamic inline debit/credit rows with real-time running balance and imbalance indicator
- Chart of Accounts supports parent/child hierarchy with clickable sub-accounts and journal history
- Accounting periods have clear open/closed/locked lifecycle with progressive confirmation
- Bank reconciliation with checkbox-based match/unmatch, running totals, "All caught up!" empty state
- Budget vs Actual with utilization progress bar, over-budget alerts, variance color coding
- Bulk journal operations center with statistics cards

#### UX Issues Found

- **Major — Chart of Accounts is a flat table, not a tree.** No expand/collapse hierarchy view, no account type color coding.
- **Major — No GL drill-down from financial statements.** P&L and Balance Sheet rows don't link to the GL.
- **Minor — Journal form has no keyboard shortcut to add rows.**
- **Minor — Budget edit 12-month horizontal table is cramped on smaller screens.**
- **Minor — Bank reconciliation has no fuzzy matching suggestions.**

#### Recommendations

**3a. Add tree view to Chart of Accounts:**

```html
@foreach(['asset' => 'Assets', 'liability' => 'Liabilities', 'equity' =>
'Equity', 'income' => 'Income', 'expense' => 'Expenses'] as $type => $label)
<div x-data="{ open: true }" class="mb-4">
    <button
        @click="open = !open"
        class="flex items-center w-full px-4 py-2 text-sm font-semibold rounded-lg
        {{ $type === 'asset' ? 'bg-blue-50 text-blue-800' : '' }}
        {{ $type === 'liability' ? 'bg-red-50 text-red-800' : '' }}
        {{ $type === 'income' ? 'bg-green-50 text-green-800' : '' }}"
    >
        <span x-text="open ? '▾' : '▸'" class="mr-2"></span>{{ $label }}
    </button>
    <div x-show="open" x-collapse>
        {{-- Nested accounts with pl-4 per level --}}
    </div>
</div>
@endforeach
```

**3b. Add drill-down links from financial statements:**

```html
<a
    href="{{ route('reports.general-ledger', ['account_id' => $account->id, 'start_date' => $startDate, 'end_date' => $endDate]) }}"
    class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 hover:underline"
>
    {{ $account->name }}
</a>
```

---

### 4. HUMAN RESOURCE MODULE UX (7%) — Score: 6.0/10

#### UX Strengths

- Employee profile with stats cards, two-column grid, personal info sidebar
- Leave show page with clear status banners (yellow/green/red) and approval trail
- Department/designation pages show employee counts and relationships
- Masked sensitive data (bank account, tax ID)

#### UX Issues Found

- **Critical — No employee photo/avatar support.** Only initials-based circles.
- **Critical — No org chart visualization.** Only flat department lists.
- **Major — No onboarding checklist or progress tracker.**
- **Major — No calendar picker for leave requests.** Basic HTML date inputs only.
- **Major — Leave balance not displayed on the request form.**
- **Minor — No attendance view (monthly grid calendar).**
- **Minor — Employee show page has no tabbed layout.** All info on a single scrollable page.

#### Recommendations

**4a. Add tabbed layout to employee profile:**

```html
<div
    x-data="{ tab: 'personal' }"
    class="border-b border-gray-200 dark:border-gray-700"
>
    <nav class="flex space-x-8">
        @foreach(['personal' => 'Personal', 'employment' => 'Employment',
        'leaves' => 'Leaves', 'payroll' => 'Payroll', 'documents' =>
        'Documents'] as $key => $label)
        <button
            @click="tab = '{{ $key }}'"
            :class="tab === '{{ $key }}' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
            class="px-1 py-4 text-sm font-medium border-b-2 whitespace-nowrap"
        >
            {{ $label }}
        </button>
        @endforeach
    </nav>
</div>
<div x-show="tab === 'personal'" x-transition>...</div>
```

**4b. Show leave balance on request form:**

```html
<div class="grid grid-cols-3 gap-4 mb-6">
    @foreach($leaveBalances as $type => $balance)
    <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border">
        <p class="text-sm text-gray-500">{{ $type }}</p>
        <p
            class="text-2xl font-bold {{ $balance['remaining'] <= 2 ? 'text-red-600' : 'text-green-600' }}"
        >
            {{ $balance['remaining'] }}
            <span class="text-sm font-normal text-gray-400"
                >/ {{ $balance['total'] }} days</span
            >
        </p>
    </div>
    @endforeach
</div>
```

---

### 5. PAYROLL MODULE UX (8%) — Score: 7.5/10

#### UX Strengths

- Payroll batch system with clear pipeline: Draft → Approved → Paid
- Batch show page with summary cards and search/filter within the batch
- Clean payslip earnings/deductions breakdown with color-coded sections
- Professional PDF payslip layout
- Bulk operations: Approve All, Mark Paid, Download All Payslips
- Salary structures with live summary calculation

#### UX Issues Found

- **Major — No exception alerts before payroll run.** No pre-flight check for missing data.
- **Major — No status pipeline visualization.** Status shown as text badge only.
- **Minor — Payroll history lacks date-range search.**
- **Minor — No inline edit on employee pay summary table.**
- **Minor — Payroll generation shows employees as both card grid AND table (redundant).**

#### Recommendations

**5a. Add status step indicator:**

```html
@php $steps = ['Draft', 'Approved', 'Paid']; $currentStep =
array_search($batch->status, ['draft', 'approved', 'paid']); @endphp
<div class="flex items-center justify-center space-x-4 mb-8">
    @foreach($steps as $index => $step)
    <div class="flex items-center">
        <div
            class="flex items-center justify-center w-10 h-10 rounded-full
                {{ $index <= $currentStep ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-500 dark:bg-gray-700' }}"
        >
            {{ $index + 1 }}
        </div>
        <span
            class="ml-2 text-sm font-medium {{ $index <= $currentStep ? 'text-indigo-600' : 'text-gray-500' }}"
            >{{ $step }}</span
        >
    </div>
    @if(!$loop->last)
    <div
        class="w-16 h-0.5 {{ $index < $currentStep ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700' }}"
    ></div>
    @endif @endforeach
</div>
```

**5b. Add pre-run validation alerts:**

```html
@if($exceptions->isNotEmpty())
<div
    class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6"
>
    <h4 class="text-sm font-semibold text-yellow-800 dark:text-yellow-200">
        ⚠ {{ $exceptions->count() }} employees need attention
    </h4>
    <ul class="mt-2 text-sm text-yellow-700 dark:text-yellow-300 space-y-1">
        @foreach($exceptions as $e)
        <li>• {{ $e->full_name }} — {{ $e->issue }}</li>
        @endforeach
    </ul>
</div>
@endif
```

---

### 6. SALES MODULE UX (8%) — Score: 8.0/10

#### UX Strengths

- Fully dynamic invoice form: customer autocomplete with keyboard nav, searchable items, real-time totals
- Rich invoice show page: status banner, payment history, refund history, audit timeline, journal entries
- Professional print layout with signature lines and waybill document
- Sales orders support one-click conversion to invoices
- Customer profile with 5-column stats, deposit management, recent transactions
- Refund flow with quick-amount buttons (Full / 50% / 25%)
- Consistent status badges: Gray=Draft, Blue=Sent, Yellow=Partial, Green=Paid, Red=Overdue

#### UX Issues Found

- **Major — No pipeline/board view for sales orders.** Table-only, no Kanban.
- **Major — AR aging has no quick follow-up action** (Send Reminder, Record Payment inline).
- **Minor — Customer credit limit displayed but not enforced in the UI.**
- **Minor — Invoice PDF requires navigation to separate route (no direct download button).**
- **Minor — No multi-currency conversion indicators.**

#### Recommendations

**6a. Add quick actions to AR aging rows:**

```html
<td class="px-4 py-3 text-right whitespace-nowrap">
    <div class="flex items-center justify-end gap-2">
        <a
            href="{{ route('invoices.show', $invoice) }}"
            class="text-indigo-600 hover:text-indigo-900 text-xs"
            >View</a
        >
        <a
            href="{{ route('payments-received.create', ['invoice_id' => $invoice->id]) }}"
            class="text-green-600 hover:text-green-900 text-xs font-medium"
            >Record Payment</a
        >
        @if($invoice->isOverdue())
        <button
            wire:click="sendReminder({{ $invoice->id }})"
            class="text-yellow-600 hover:text-yellow-900 text-xs"
        >
            Send Reminder
        </button>
        @endif
    </div>
</td>
```

**6b. Add credit limit warning on invoice creation:**

```javascript
// In the invoice create Alpine component
watch: {
    customer(value) {
        if (value && value.credit_limit > 0) {
            const outstanding = value.outstanding_balance + this.total;
            if (outstanding > value.credit_limit) {
                this.creditWarning = `Warning: This invoice will exceed credit limit by ${formatCurrency(outstanding - value.credit_limit)}`;
            }
        }
    }
}
```

---

### 7. ITEMS / INVENTORY MODULE UX (8%) — Score: 6.5/10

#### UX Strengths

- Item show page has profit margin calculation and color-coded inventory badges
- Low stock warning with visual alert when below reorder level
- Inventory history with color-coded type badges
- Stock adjustment form embedded directly on inventory show page
- Item categories support hierarchy

#### UX Issues Found

- **Critical — No card/table toggle view.** Table-only, no grid with thumbnails.
- **Critical — No item image support.** No image upload field in forms.
- **Major — No warehouse/location support.** Single-location inventory only.
- **Major — No reorder alert badges on items list page.**
- **Minor — No stock movement timeline visualization.**
- **Minor — Item form has no tabbed layout (Details, Pricing, Stock).**
- **Minor — No BOM (Bill of Materials) support.**

#### Recommendations

**7a. Add low-stock indicator to items table:**

```html
<td>
    <span
        class="{{ $item->quantity_on_hand <= $item->reorder_level ? 'text-red-600 font-semibold' : 'text-gray-900 dark:text-gray-100' }}"
    >
        {{ $item->quantity_on_hand }}
    </span>
    @if($item->track_inventory && $item->quantity_on_hand <=
    $item->reorder_level)
    <span
        class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-400"
    >
        Low
    </span>
    @endif
</td>
```

**7b. Add image upload to item form:**

```html
<div>
    <x-input-label for="image" value="Item Image" />
    <input
        type="file"
        name="image"
        accept="image/*"
        class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
    />
    @error('image') <x-input-error :messages="$message" /> @enderror
</div>
```

---

### 8. PURCHASES MODULE UX (8%) — Score: 7.0/10

#### UX Strengths

- Bill form mirrors invoice form for consistency
- Vendor profile with 4-column stats and recent bills/expenses
- Expense form has clear account selection
- Recurrent bills and expenses support automated scheduling
- Consistent status badges

#### UX Issues Found

- **Critical — No procurement pipeline (PR → PO → GRN → Bill).** Purchase Orders don't exist as separate entity.
- **Critical — No 3-way matching screen.** No PO/GRN/Bill comparison view.
- **Major — AP aging has no inline payment action.**
- **Major — No vendor statement/reconciliation view.**
- **Minor — Expense reject modal lacks reason categorization.**

#### Recommendations

**8a. Add quick payment action to AP aging:**

```html
<a
    href="{{ route('payments-made.create', ['bill_id' => $bill->id]) }}"
    class="inline-flex items-center px-2 py-1 text-xs font-medium text-white bg-green-600 rounded hover:bg-green-700"
>
    Pay
</a>
```

**8b. Purchase Order module** — Significant feature addition requiring:

- New `purchase_orders` table and model
- PO create/edit/show views mirroring invoice structure
- GRN (Goods Receipt Note) with item verification
- Bill matching against PO

---

### 9. FIXED ASSETS MODULE UX (7%) — Score: 7.0/10

#### UX Strengths

- Asset register with comprehensive filterable table
- Summary cards for Total Cost, Accumulated Depreciation, NBV
- Depreciation schedule with year selector
- Create form auto-populates from category defaults
- Multiple depreciation methods supported
- GL account integration

#### UX Issues Found

- **Major — No NBV trend chart on asset detail page.** Tabular only.
- **Major — No depreciation bulk-run interface with progress indicator.**
- **Major — No disposal wizard with gain/loss preview.**
- **Minor — No asset tag/barcode generation or scan support.**
- **Minor — No "fully depreciated" visual distinction in status options.**

#### Recommendations

**9a. Add NBV trend chart using Chart.js:**

```html
<canvas id="nbvChart" class="w-full" style="height: 250px;"></canvas>
<script nonce="{{ csp_nonce() }}">
    new Chart(document.getElementById('nbvChart'), {
        type: 'line',
        data: {
            labels: @json($depreciationSchedule->pluck('period')),
            datasets: [{
                label: 'Net Book Value',
                data: @json($depreciationSchedule->pluck('book_value')),
                borderColor: '#4f46e5',
                backgroundColor: 'rgba(79,70,229,0.1)',
                fill: true, tension: 0.3
            }]
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });
</script>
```

---

### 10. REPORTS MODULE UX (8%) — Score: 7.5/10

#### UX Strengths

- 25+ reports organized by category (Financial, Comparative, Tax, Receivables, Sales, Inventory, Payroll)
- Card-based catalogue with color-coded icons and hover effects
- Export component with Print / PDF / CSV on every report
- Balance Sheet has collapsible account groups with animated chevrons
- Budget vs Actual with utilization progress bar
- Accounting equation validation on Balance Sheet
- Custom Report Builder
- Analytics dashboard with period selectors, KPI cards, Chart.js visualizations

#### UX Issues Found

- **Major — No scheduled/recurring report delivery.**
- **Major — No column toggling on report tables.**
- **Minor — P&L lacks expandable drill-down rows (Balance Sheet does this well).**
- **Minor — Filter panel is basic (date range only, no dimension filters).**
- **Minor — Report index has no search/filter for 25+ reports.**

#### Recommendations

**10a. Add search to report catalogue:**

```html
<div x-data="{ search: '' }" class="mb-6">
    <input
        type="text"
        x-model="search"
        placeholder="Search reports..."
        class="w-full max-w-md rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700"
    />
    {{-- Filter visible report cards with x-show --}}
</div>
```

---

### 11. FORMS & DATA ENTRY UX (8%) — Score: 7.0/10

#### UX Strengths

- Inline validation with `@error` blocks, red borders on invalid inputs
- `<x-searchable-select>` with keyboard navigation (arrow keys, Enter, Esc)
- Smart defaults: auto-generated doc numbers, today's date, user associations
- Confirmation dialogs on destructive actions
- Required field indicators with red asterisks
- Multi-section forms with icon-based colored section headers
- Soft deletes on financial documents

#### UX Issues Found

- **Major — No auto-save/draft on long forms.** Data loss risk on invoices, bills, journals.
- **Major — Confirmation dialogs use native browser `confirm()`.** Ugly, unbranded, unstyled.
- **Minor — No undo on bulk operations.**
- **Minor — Error messages not contextual enough (generic Laravel messages).**
- **Minor — No character counts on textareas.**
- **Minor — `<x-searchable-select>` missing full ARIA attributes.**

#### Recommendations

**11a. Replace native `confirm()` with modal:**

```html
<button @click="$dispatch('open-modal', 'confirm-delete')">Delete</button>
<x-modal name="confirm-delete" maxWidth="md">
    <div class="p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
            Confirm Delete
        </h3>
        <p class="mt-2 text-sm text-gray-500">
            Are you sure? This action cannot be undone.
        </p>
        <div class="mt-6 flex justify-end gap-3">
            <button
                @click="$dispatch('close-modal', 'confirm-delete')"
                class="px-4 py-2 text-sm border rounded-md"
            >
                Cancel
            </button>
            <form method="POST" action="...">
                @csrf @method('DELETE')
                <button
                    type="submit"
                    class="px-4 py-2 text-sm bg-red-600 text-white rounded-md"
                >
                    Delete
                </button>
            </form>
        </div>
    </div>
</x-modal>
```

**11b. Add draft auto-save on complex forms:**

```javascript
// Alpine.js auto-save to localStorage
x-data="{
    formData: JSON.parse(localStorage.getItem('invoice_draft') || '{}'),
    autoSave() { localStorage.setItem('invoice_draft', JSON.stringify(this.formData)); },
    clearDraft() { localStorage.removeItem('invoice_draft'); }
}"
x-init="$watch('formData', () => autoSave())"
@submit="clearDraft()"
```

---

### 12. ACCESSIBILITY — WCAG 2.1 AA (5%) — Score: 5.5/10

#### UX Strengths

- `sr-only` spans on icon-only buttons (sidebar close, notifications, dark mode toggle)
- `focus:ring-2` and `focus:outline-none` on all button components
- Modal focus trap implementation (Tab/Shift+Tab cycling)
- Semantic HTML: `<header>`, `<nav>`, `<main>`, `<button>`, `<form>`, `<label for>`
- `dark:focus:ring-offset-gray-800` ensures visible focus rings in dark mode
- Some modals have `aria-labelledby`, `role="dialog"`, `aria-modal="true"`

#### UX Issues Found

- **Critical — No `aria-label` on most icon-only action buttons across all table views.**
- **Critical — `<x-searchable-select>` missing ARIA roles** (`role="combobox"`, `role="listbox"`, `aria-expanded`, `aria-activedescendant`).
- **Major — Tables lack `scope` attributes on `<th>` elements.**
- **Major — Color-only status indicators.** Status badges use color as primary signal (text labels present but secondary).
- **Minor — No skip-to-content link.**
- **Minor — Date inputs use native HTML5 `<input type="date">` — inconsistent cross-browser.**
- **Minor — Flash messages don't use `role="alert"` or `aria-live="polite"`.**

#### Recommendations

**12a. Add `aria-label` to all icon-only buttons:**

```html
<a
    href="{{ route('invoices.show', $invoice) }}"
    class="text-indigo-600 hover:text-indigo-900"
    aria-label="View invoice {{ $invoice->invoice_number }}"
>
    <svg aria-hidden="true">...</svg>
</a>
```

**12b. Add skip-to-content link:**

```html
{{-- First element inside
<body>
    in app.blade.php --}}
    <a
        href="#main-content"
        class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[100] focus:px-4 focus:py-2 focus:bg-indigo-600 focus:text-white focus:rounded-md"
    >
        Skip to main content
    </a>
    {{-- Add id="main-content" to the
    <main>element --}}</main>
</body>
```

**12c. Add `aria-live` to flash messages:**

```html
<div role="alert" aria-live="polite" class="..."></div>
```

---

## QUICK WINS (< 1 Day Each)

- [ ] **QW-01** Add `wire:loading` overlay to all Livewire tables (~2 hrs)
- [ ] **QW-02** Add `aria-label` to all icon-only buttons in table views (~2 hrs)
- [ ] **QW-03** Add skip-to-content link in `app.blade.php` (~15 min)
- [ ] **QW-04** Add `role="alert"` and `aria-live="polite"` to flash messages (~15 min)
- [ ] **QW-05** Replace native `confirm()` with `<x-modal>` on destructive actions (~3 hrs)
- [ ] **QW-06** Add breadcrumb component and wire into all show/create/edit pages (~4 hrs)
- [ ] **QW-07** Add search filter to Reports index page (~1 hr)
- [ ] **QW-08** Add low-stock badge to Items table rows (~30 min)
- [ ] **QW-09** Add `scope="col"` to all `<th>` elements (~1 hr)
- [ ] **QW-10** Create Alpine.js toast notification component (~2 hrs)

---

## UX IMPROVEMENT ROADMAP

### Phase 1: Foundation Fixes (Days 1–30)

**Theme: Accessibility + Feedback + Navigation**

- [ ] **P1-01** Implement Livewire loading states on all table components
- [ ] **P1-02** Build and deploy toast notification system (Alpine.js)
- [ ] **P1-03** Add breadcrumb component to all views
- [ ] **P1-04** Fix all WCAG critical issues: `aria-label`, `role`, `scope`, skip-link
- [ ] **P1-05** Make global search functional (Livewire global search component)
- [ ] **P1-06** Standardize empty state component and deploy across all modules
- [ ] **P1-07** Replace native `confirm()` with styled modal confirmations
- [ ] **P1-08** Add `aria-live` regions, `role="alert"` to flash messages

### Phase 2: Module Enhancement (Days 31–60)

**Theme: Feature Gaps + Data Entry UX**

- [ ] **P2-01** Add auto-save/draft for invoices, bills, and journal entries
- [ ] **P2-02** Build Chart of Accounts tree view with collapse/expand
- [ ] **P2-03** Add employee photo upload and tabbed profile layout
- [ ] **P2-04** Add item image support and card/grid view toggle
- [ ] **P2-05** Add payroll status step indicator (pipeline visualization)
- [ ] **P2-06** Add payroll pre-run exception alerts
- [ ] **P2-07** Add quick actions (Record Payment, Send Reminder) to AR/AP aging reports
- [ ] **P2-08** Add GL drill-down links from P&L and Balance Sheet
- [ ] **P2-09** Add NBV trend chart to Fixed Asset detail page
- [ ] **P2-10** Add leave balance display on leave request form

### Phase 3: Strategic Enhancements (Days 61–90)

**Theme: ERP Completeness + Power Users**

- [ ] **P3-01** Build Purchase Order module (PO → GRN → Bill pipeline)
- [ ] **P3-02** Add keyboard shortcut system (`Ctrl+K` command palette, `N` for new)
- [ ] **P3-03** Build org chart visualization component
- [ ] **P3-04** Add scheduled/recurring report email delivery
- [ ] **P3-05** Add column toggling and dimension filters to reports
- [ ] **P3-06** Build onboarding checklist for new employees
- [ ] **P3-07** Add attendance calendar view with status colours
- [ ] **P3-08** Add disposal wizard for Fixed Assets with gain/loss preview
- [ ] **P3-09** Add multi-warehouse support for inventory
- [ ] **P3-10** Improve searchable-select ARIA compliance (`role="combobox"`, `aria-activedescendant`)
