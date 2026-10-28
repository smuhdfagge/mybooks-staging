<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Services\BankLines;
use App\Services\BankStatements\StatementCsv;
use App\Services\BankStatements\StatementImport;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Bank statement import for reconciling without a bank feed (audit MB-08):
 * upload a CSV, check what was read, import it; its lines are then matched
 * to the lines from the books and ticked on the reconcile screen.
 */
class BankStatementController extends Controller
{
    private const SESSION_KEY = 'bank_statement_import';

    /** Read the uploaded CSV and show what will be imported. Nothing is saved yet. */
    public function preview(Request $request, Bank $bank, StatementCsv $csv)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $parsed = $csv->parse($file->getRealPath());
        $token = Str::random(32);
        $request->session()->put(self::SESSION_KEY, ['token' => $token, 'bank_id' => $bank->id, 'lines' => $parsed['lines']]);

        $lines = collect($parsed['lines']);

        return view('banks.statement-preview', [
            'bank' => $bank,
            'token' => $token,
            'lines' => $lines,
            'problems' => $parsed['problems'],
            'moneyIn' => round($lines->where('amount', '>', 0)->sum('amount'), 2),
            'moneyOut' => round(-$lines->where('amount', '<', 0)->sum('amount'), 2),
        ]);
    }

    public function store(Request $request, Bank $bank, StatementImport $import, BankLines $bankLines)
    {
        $request->validate(['token' => ['required', 'string']]);
        $pending = $request->session()->get(self::SESSION_KEY);
        if (! is_array($pending) || ! hash_equals((string) ($pending['token'] ?? ''), (string) $request->input('token')) || (int) ($pending['bank_id'] ?? 0) !== $bank->id) {
            return redirect()->route('banks.reconcile', $bank)->with('error', 'That import has expired. Upload the statement again.');
        }
        $request->session()->forget(self::SESSION_KEY);

        $result = $import->save($bank, $pending['lines'], auth()->id());
        $bankLines->sync($bank);
        $matched = $import->match($bank);

        $message = "Imported {$result['added']} statement line(s)."
            .($result['skipped'] ? " {$result['skipped']} were already imported." : '')
            ." {$matched} matched to lines in your books and ticked below; check them, enter the closing balance and reconcile.";

        return redirect()->route('banks.reconcile', $bank)->with('success', $message);
    }

    /** Remove the imported lines not reconciled yet (a wrong file). */
    public function destroy(Bank $bank, StatementImport $import)
    {
        $count = $import->discard($bank);

        return redirect()->route('banks.reconcile', $bank)->with('success', "Removed {$count} imported statement line(s).");
    }
}
