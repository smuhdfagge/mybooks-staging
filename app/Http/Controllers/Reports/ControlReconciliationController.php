<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Statements\ControlReconciliation;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reports > Receivables & payables check (session 10): the control
 * accounts in the ledger against the customer and supplier balances.
 */
class ControlReconciliationController extends Controller
{
    public function show(Request $request, ControlReconciliation $reconciliation): View
    {
        $data = $request->validate(['as_of' => 'nullable|date']);
        $asOf = $data['as_of'] ?? now()->toDateString();

        return view('reports.control-reconciliation', [
            'asOf' => $asOf,
            'sections' => $reconciliation->run(auth()->user()->tenant_id, $asOf),
        ]);
    }
}
