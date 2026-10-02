<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJournalRequest;
use App\Http\Requests\UpdateJournalRequest;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

class JournalController extends Controller
{
    public function index()
    {
        return view('journals.index');
    }

    public function create()
    {
        $accounts = ChartOfAccount::where('is_active', true)->orderBy('account_code')->get();
        $journalNumber = Journal::previewNumber(auth()->user()->tenant_id);

        return view('journals.create', compact('accounts', 'journalNumber'));
    }

    public function store(StoreJournalRequest $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validated();

        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($validated['entries'] as $entry) {
            $totalDebit += $entry['debit'] ?? 0;
            $totalCredit += $entry['credit'] ?? 0;
        }

        if (abs($totalDebit - $totalCredit) > 0.01) {
            return redirect()->back()->withInput()->with('error', 'Journal entries must be balanced. Debit and credit totals must match.');
        }

        DB::transaction(function () use ($validated, $tenantId, $totalDebit, $totalCredit) {
            $journal = Journal::create([
                'tenant_id' => $tenantId,
                'journal_number' => Journal::generateNumber($tenantId),
                'journal_date' => $validated['journal_date'],
                'reverse_on' => $validated['reverse_on'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'description' => $validated['description'] ?? null,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['entries'] as $entryData) {
                if (($entryData['debit'] ?? 0) > 0 || ($entryData['credit'] ?? 0) > 0) {
                    JournalEntry::create([
                        'journal_id' => $journal->id,
                        'account_id' => $entryData['account_id'],
                        'description' => $entryData['description'] ?? null,
                        'debit' => $entryData['debit'] ?? 0,
                        'credit' => $entryData['credit'] ?? 0,
                    ]);
                }
            }
        });

        return redirect()->route('journals.index')->with('success', 'Journal created successfully.');
    }

    public function show(Journal $journal)
    {
        $journal->load(['entries.account', 'createdBy', 'approvedBy', 'autoReversal']);

        return view('journals.show', compact('journal'));
    }

    public function edit(Journal $journal)
    {
        if ($journal->is_posted) {
            return redirect()->back()->with('error', 'Posted journals cannot be edited.');
        }

        $accounts = ChartOfAccount::where('is_active', true)->orderBy('account_code')->get();
        $journal->load('entries');

        return view('journals.edit', compact('journal', 'accounts'));
    }

    public function update(UpdateJournalRequest $request, Journal $journal)
    {
        if ($journal->is_posted) {
            return redirect()->back()->with('error', 'Posted journals cannot be modified.');
        }

        $validated = $request->validated();
        // The form sends every field; anything left out stays as it was.
        $validated += [
            'journal_date' => $journal->journal_date,
            'description' => $journal->description,
            'entries' => $journal->entries->map->only(['account_id', 'description', 'debit', 'credit'])->all(),
        ];

        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($validated['entries'] as $entry) {
            $totalDebit += $entry['debit'] ?? 0;
            $totalCredit += $entry['credit'] ?? 0;
        }

        if (abs($totalDebit - $totalCredit) > 0.01) {
            return redirect()->back()->withInput()->with('error', 'Journal entries must be balanced.');
        }

        DB::transaction(function () use ($validated, $journal, $totalDebit, $totalCredit) {
            $journal->update([
                'journal_date' => $validated['journal_date'],
                'reverse_on' => $validated['reverse_on'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'description' => $validated['description'],
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
            ]);

            $journal->entries()->delete();

            foreach ($validated['entries'] as $entryData) {
                if (($entryData['debit'] ?? 0) > 0 || ($entryData['credit'] ?? 0) > 0) {
                    JournalEntry::create([
                        'journal_id' => $journal->id,
                        'account_id' => $entryData['account_id'],
                        'description' => $entryData['description'] ?? null,
                        'debit' => $entryData['debit'] ?? 0,
                        'credit' => $entryData['credit'] ?? 0,
                    ]);
                }
            }
        });

        return redirect()->route('journals.show', $journal)->with('success', 'Journal updated successfully.');
    }

    public function destroy(Journal $journal)
    {
        if ($journal->is_posted) {
            return redirect()->back()->with('error', 'Posted journals cannot be deleted.');
        }

        DB::transaction(function () use ($journal) {
            $journal->entries()->delete();
            $journal->delete();
        });

        return redirect()->route('journals.index')->with('success', 'Journal deleted successfully.');
    }

    public function post(Journal $journal)
    {
        if ($journal->is_posted) {
            return redirect()->back()->with('error', 'Journal is already posted.');
        }

        try {
            $journal->post();

            return redirect()->back()->with('success', 'Journal posted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function bulkUpdate()
    {
        return view('journals.bulk-update');
    }
}
