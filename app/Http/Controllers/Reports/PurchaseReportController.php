<?php

namespace App\Http\Controllers\Reports;

use App\Models\Bill;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\Vendor;
use Illuminate\Http\Request;

/**
 * Payables, purchases by vendor and inventory summary, with their exports.
 *
 * Split out of the old 3,400-line ReportController (finding L4). Route
 * names are unchanged.
 */
class PurchaseReportController extends ReportController
{
    public function accountsPayable(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $asOf = $request->get('as_of', now()->format('Y-m-d'));

        $bills = Bill::where('tenant_id', $tenantId)
            ->where('bill_date', '<=', $asOf)
            ->whereIn('status', ['unpaid', 'partial', 'overdue'])
            ->where('balance_due', '>', 0)
            ->with('vendor')
            ->orderBy('due_date')
            ->get();

        // Group by aging based on as_of date for proper historical accuracy
        $asOfDate = \Carbon\Carbon::parse($asOf);
        $current = $bills->filter(fn ($bill) => $bill->due_date >= $asOfDate)->sum('balance_due');
        $days30 = $bills->filter(fn ($bill) => $bill->due_date < $asOfDate && $bill->due_date >= $asOfDate->copy()->subDays(30))->sum('balance_due');
        $days60 = $bills->filter(fn ($bill) => $bill->due_date < $asOfDate->copy()->subDays(30) && $bill->due_date >= $asOfDate->copy()->subDays(60))->sum('balance_due');
        $days90 = $bills->filter(fn ($bill) => $bill->due_date < $asOfDate->copy()->subDays(60) && $bill->due_date >= $asOfDate->copy()->subDays(90))->sum('balance_due');
        $days120 = $bills->filter(fn ($bill) => $bill->due_date < $asOfDate->copy()->subDays(90) && $bill->due_date >= $asOfDate->copy()->subDays(120))->sum('balance_due');
        $over120 = $bills->filter(fn ($bill) => $bill->due_date < $asOfDate->copy()->subDays(120))->sum('balance_due');

        $totalPayable = $bills->sum('balance_due');

        return view('reports.accounts-payable', compact(
            'bills', 'current', 'days30', 'days60', 'days90', 'days120', 'over120', 'totalPayable', 'asOf'
        ));
    }

    public function purchaseByVendor(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $vendors = Vendor::where('tenant_id', $tenantId)
            ->whereHas('bills', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('bill_date', [$startDate, $endDate]);
            })
            ->withCount(['bills' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('bill_date', [$startDate, $endDate]);
            }])
            ->withSum(['bills' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('bill_date', [$startDate, $endDate]);
            }], 'total')
            ->withSum(['bills' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('bill_date', [$startDate, $endDate]);
            }], 'amount_paid')
            ->orderByDesc('bills_sum_total')
            ->get();

        $totalPurchases = $vendors->sum('bills_sum_total');
        $totalPaid = $vendors->sum('bills_sum_amount_paid');

        return view('reports.purchase-by-vendor', compact(
            'vendors', 'totalPurchases', 'totalPaid', 'startDate', 'endDate'
        ));
    }

    public function inventorySummary(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $items = Item::where('tenant_id', $tenantId)
            ->where('track_inventory', true)
            ->with('inventory')
            ->get()
            ->map(function ($item) {
                $item->stock_quantity = $item->inventory->quantity ?? 0;
                $item->stock_value = $item->stock_quantity * $item->cost_price;
                $item->is_low_stock = $item->stock_quantity <= $item->reorder_level;

                return $item;
            });

        $totalItems = $items->count();
        $totalValue = $items->sum('stock_value');
        $lowStockItems = $items->where('is_low_stock', true)->count();

        return view('reports.inventory-summary', compact(
            'items', 'totalItems', 'totalValue', 'lowStockItems'
        ));
    }

    /**
     * Export Accounts Payable Report
     */
    public function exportAccountsPayable(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $asOf = $request->get('as_of', now()->format('Y-m-d'));
        $format = $request->get('format', 'pdf');

        $bills = Bill::where('tenant_id', $tenantId)
            ->where('bill_date', '<=', $asOf)
            ->whereIn('status', ['unpaid', 'partial', 'overdue'])
            ->where('balance_due', '>', 0)
            ->with('vendor')
            ->orderBy('due_date')
            ->get();

        $asOfDate = \Carbon\Carbon::parse($asOf);
        $current = $bills->filter(fn ($bill) => $bill->due_date >= $asOfDate)->sum('balance_due');
        $days30 = $bills->filter(fn ($bill) => $bill->due_date < $asOfDate && $bill->due_date >= $asOfDate->copy()->subDays(30))->sum('balance_due');
        $days60 = $bills->filter(fn ($bill) => $bill->due_date < $asOfDate->copy()->subDays(30) && $bill->due_date >= $asOfDate->copy()->subDays(60))->sum('balance_due');
        $days90 = $bills->filter(fn ($bill) => $bill->due_date < $asOfDate->copy()->subDays(60) && $bill->due_date >= $asOfDate->copy()->subDays(90))->sum('balance_due');
        $days120 = $bills->filter(fn ($bill) => $bill->due_date < $asOfDate->copy()->subDays(90) && $bill->due_date >= $asOfDate->copy()->subDays(120))->sum('balance_due');
        $over120 = $bills->filter(fn ($bill) => $bill->due_date < $asOfDate->copy()->subDays(120))->sum('balance_due');
        $totalPayable = $bills->sum('balance_due');

        $data = compact('bills', 'current', 'days30', 'days60', 'days90', 'days120', 'over120', 'totalPayable', 'asOf');

        if ($format === 'csv') {
            $exportData = $this->exportService->accountsPayableData($bills, [
                'current' => $current,
                'days30' => $days30,
                'days60' => $days60,
                'days90' => $days90,
                'days120' => $days120,
                'over120' => $over120,
                'total' => $totalPayable,
            ]);

            return $this->exportService
                ->setTitle('Accounts Payable Aging')
                ->setFilters(['As of' => $asOf])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Accounts Payable Aging')
            ->setFilters(['As of' => $asOf])
            ->exportToPdf('reports.pdf.accounts-payable', $data);
    }

    /**
     * Export Purchase by Vendor Report
     */
    public function exportPurchaseByVendor(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $format = $request->get('format', 'pdf');

        $vendors = Vendor::where('tenant_id', $tenantId)
            ->whereHas('bills', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('bill_date', [$startDate, $endDate]);
            })
            ->withCount(['bills' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('bill_date', [$startDate, $endDate]);
            }])
            ->withSum(['bills' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('bill_date', [$startDate, $endDate]);
            }], 'total')
            ->withSum(['bills' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('bill_date', [$startDate, $endDate]);
            }], 'amount_paid')
            ->orderByDesc('bills_sum_total')
            ->get();

        $totalPurchases = $vendors->sum('bills_sum_total');
        $totalPaid = $vendors->sum('bills_sum_amount_paid');

        $data = compact('vendors', 'totalPurchases', 'totalPaid', 'startDate', 'endDate');

        if ($format === 'csv') {
            $exportData = $this->exportService->purchaseByVendorData($vendors);

            return $this->exportService
                ->setTitle('Purchase by Vendor')
                ->setFilters(['Period' => "$startDate to $endDate"])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Purchase by Vendor')
            ->setFilters(['Period' => "$startDate to $endDate"])
            ->exportToPdf('reports.pdf.purchase-by-vendor', $data);
    }

    /**
     * Export Inventory Summary Report
     */
    public function exportInventorySummary(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $format = $request->get('format', 'pdf');

        $items = Item::where('tenant_id', $tenantId)
            ->where('track_inventory', true)
            ->with('inventory')
            ->get()
            ->map(function ($item) {
                $item->stock_quantity = $item->inventory->quantity ?? 0;
                $item->stock_value = $item->stock_quantity * $item->cost_price;
                $item->is_low_stock = $item->stock_quantity <= $item->reorder_level;

                return $item;
            });

        $totalItems = $items->count();
        $totalValue = $items->sum('stock_value');
        $lowStockItems = $items->where('is_low_stock', true)->count();

        $data = compact('items', 'totalItems', 'totalValue', 'lowStockItems');

        if ($format === 'csv') {
            $exportData = $this->exportService->inventorySummaryData($items);

            return $this->exportService
                ->setTitle('Inventory Summary')
                ->setFilters(['Generated' => now()->format('Y-m-d')])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Inventory Summary')
            ->setFilters(['Generated' => now()->format('Y-m-d')])
            ->exportToPdf('reports.pdf.inventory-summary', $data);
    }
}
