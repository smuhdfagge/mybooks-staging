<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\UnbalancedJournalException;
use App\Http\Requests\StoreJournalRequest;
use App\Http\Requests\UpdateJournalRequest;
use App\Http\Resources\JournalResource;
use App\Models\Journal;
use App\Models\JournalEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JournalController extends BaseApiController
{
    /**
     * Get all journals
     */
    public function index(Request $request): JsonResponse
    {
        $query = Journal::with(['entries.account', 'createdBy:id,name']);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('journal_number', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by posted status
        if ($request->has('is_posted')) {
            $query->where('is_posted', $request->boolean('is_posted'));
        }

        // Filter by date range
        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('journal_date', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $query->whereDate('journal_date', '<=', $toDate);
        }

        // Filter by reference type (invoices, bills, expenses, etc.)
        if ($refType = $request->input('reference_type')) {
            $query->where('reference_type', 'like', "%{$refType}%");
        }

        // Sorting
        [$sortBy, $sortOrder] = $this->validateSortParameters(
            $request,
            ['journal_number', 'journal_date', 'status', 'total_amount', 'is_posted', 'created_at', 'updated_at'],
            'journal_date'
        );
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $journals = $query->paginate($this->validatedPerPage($request));

        return $this->paginated($journals->through(fn ($journal) => new JournalResource($journal)));
    }

    /**
     * Get a specific journal
     */
    public function show(Journal $journal): JsonResponse
    {
        $journal->load(['entries.account', 'createdBy', 'approvedBy']);

        return $this->success(new JournalResource($journal));
    }

    /**
     * Create a manual journal entry
     */
    public function store(StoreJournalRequest $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        // Each line holding a debit or a credit (not both) is checked by
        // StoreJournalRequest, the same as on the web.
        $validated = $request->validated();

        // Validate that debits equal credits
        $totalDebit = collect($validated['entries'])->sum('debit');
        $totalCredit = collect($validated['entries'])->sum('credit');

        if (abs($totalDebit - $totalCredit) > 0.01) {
            return $this->validationError([
                'entries' => ['Total debits must equal total credits. Debit: '.$totalDebit.', Credit: '.$totalCredit],
            ]);
        }

        try {
            $journal = DB::transaction(function () use ($validated, $tenantId, $totalDebit, $totalCredit) {
                $journal = Journal::create([
                    'tenant_id' => $tenantId,
                    'journal_number' => Journal::generateNumber($tenantId),
                    'journal_date' => $validated['journal_date'],
                    'reverse_on' => $validated['reverse_on'] ?? null,
                    'reference' => $validated['reference'] ?? null,
                    'description' => $validated['description'],
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                    'status' => 'draft',
                    'is_posted' => false,
                    'created_by' => auth()->id(),
                ]);

                foreach ($validated['entries'] as $entry) {
                    JournalEntry::create([
                        'journal_id' => $journal->id,
                        'account_id' => $entry['account_id'],
                        'description' => $entry['description'] ?? $validated['description'],
                        'debit' => $entry['debit'] ?? 0,
                        'credit' => $entry['credit'] ?? 0,
                    ]);
                }

                return $journal;
            });
        } catch (BusinessRuleException|UnbalancedJournalException $e) {
            // A broken rule is the client's to fix (422); anything else goes to the
            // API error handler, which reports it without showing internals (I6).
            return $this->error($e->getMessage(), 422);
        }

        $journal->load(['entries.account', 'createdBy']);

        return $this->created(new JournalResource($journal), 'Journal entry created successfully');
    }

    /**
     * Update a journal entry (only if not posted)
     */
    public function update(UpdateJournalRequest $request, Journal $journal): JsonResponse
    {
        if ($journal->is_posted) {
            return $this->error('Cannot modify a posted journal entry.', 422);
        }

        $validated = $request->validated();

        if (isset($validated['entries'])) {
            $totalDebit = collect($validated['entries'])->sum('debit');
            $totalCredit = collect($validated['entries'])->sum('credit');

            if (abs($totalDebit - $totalCredit) > 0.01) {
                return $this->validationError([
                    'entries' => ['Total debits must equal total credits.'],
                ]);
            }

            try {
                DB::transaction(function () use ($validated, $journal, $totalDebit, $totalCredit) {
                    $journal->entries()->delete();

                    foreach ($validated['entries'] as $entry) {
                        JournalEntry::create([
                            'journal_id' => $journal->id,
                            'account_id' => $entry['account_id'],
                            'description' => $entry['description'] ?? $journal->description,
                            'debit' => $entry['debit'] ?? 0,
                            'credit' => $entry['credit'] ?? 0,
                        ]);
                    }

                    $validated['total_debit'] = $totalDebit;
                    $validated['total_credit'] = $totalCredit;
                    unset($validated['entries']);

                    $journal->update($validated);
                });
            } catch (BusinessRuleException|UnbalancedJournalException $e) {
                // A broken rule is the client's to fix (422); anything else goes to the
                // API error handler, which reports it without showing internals (I6).
                return $this->error($e->getMessage(), 422);
            }
        } else {
            $journal->update($validated);
        }

        $journal->load(['entries.account', 'createdBy']);

        return $this->success(new JournalResource($journal), 'Journal entry updated successfully');
    }

    /**
     * Delete a journal entry (only if not posted)
     */
    public function destroy(Journal $journal): JsonResponse
    {
        if ($journal->is_posted) {
            return $this->error('Cannot delete a posted journal entry. Create a reversing entry instead.', 422);
        }

        if ($journal->reference_type) {
            return $this->error('Cannot delete system-generated journal entries.', 422);
        }

        $journal->entries()->delete();
        $journal->delete();

        return $this->success(null, 'Journal entry deleted successfully');
    }

    /**
     * Post a journal entry
     */
    public function post(Journal $journal): JsonResponse
    {
        if ($journal->is_posted) {
            return $this->error('Journal entry is already posted.', 422);
        }

        if (! $journal->entries()->exists()) {
            return $this->error('Journal entry has no lines to post.', 422);
        }

        if (! $journal->isBalanced()) {
            return $this->error('Journal entry must be balanced before posting.', 422);
        }

        try {
            // Same posting code as the web app (checks balance, updates
            // account balances, marks posted).
            DB::transaction(fn () => $journal->post());
        } catch (BusinessRuleException|UnbalancedJournalException $e) {
            // A broken rule is the client's to fix (422); anything else goes to the
            // API error handler, which reports it without showing internals (I6).
            return $this->error($e->getMessage(), 422);
        }

        $journal->load(['entries.account', 'createdBy']);

        return $this->success(new JournalResource($journal), 'Journal entry posted successfully');
    }

    /**
     * Create a reversing entry for a posted journal
     */
    public function reverse(Journal $journal): JsonResponse
    {
        if (! $journal->is_posted) {
            return $this->error('Only posted journal entries can be reversed.', 422);
        }

        if ($journal->status === 'reversed') {
            return $this->error('This journal entry has already been reversed.', 422);
        }

        $tenantId = $this->getTenantId();

        try {
            $reversingJournal = DB::transaction(function () use ($journal, $tenantId) {
                $reversingJournal = Journal::create([
                    'tenant_id' => $tenantId,
                    'journal_number' => Journal::generateNumber($tenantId),
                    'journal_date' => now()->format('Y-m-d'),
                    'reference' => "Reversal of {$journal->journal_number}",
                    'description' => "Reversal: {$journal->description}",
                    'total_debit' => $journal->total_credit,
                    'total_credit' => $journal->total_debit,
                    'status' => 'posted',
                    'is_posted' => true,
                    'posted_at' => now(),
                    'created_by' => auth()->id(),
                ]);

                foreach ($journal->entries as $entry) {
                    JournalEntry::create([
                        'journal_id' => $reversingJournal->id,
                        'account_id' => $entry->account_id,
                        'description' => "Reversal: {$entry->description}",
                        'debit' => $entry->credit,
                        'credit' => $entry->debit,
                    ]);

                    // Update account balances
                    $account = $entry->account;
                    if ($account->isDebitBalance()) {
                        $account->current_balance += ($entry->credit - $entry->debit);
                    } else {
                        $account->current_balance += ($entry->debit - $entry->credit);
                    }
                    $account->save();
                }

                $journal->update(['status' => 'reversed']);

                return $reversingJournal;
            });
        } catch (BusinessRuleException|UnbalancedJournalException $e) {
            // A broken rule is the client's to fix (422); anything else goes to the
            // API error handler, which reports it without showing internals (I6).
            return $this->error($e->getMessage(), 422);
        }

        $reversingJournal->load(['entries.account', 'createdBy']);

        return $this->success(new JournalResource($reversingJournal), 'Reversing journal entry created successfully');
    }

    /**
     * Get journal summary
     */
    public function summary(): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $totalJournals = Journal::where('tenant_id', $tenantId)->count();
        $postedJournals = Journal::where('tenant_id', $tenantId)->where('is_posted', true)->count();
        $draftJournals = Journal::where('tenant_id', $tenantId)->where('is_posted', false)->count();

        $totalDebits = Journal::where('tenant_id', $tenantId)->where('is_posted', true)->sum('total_debit');
        $totalCredits = Journal::where('tenant_id', $tenantId)->where('is_posted', true)->sum('total_credit');

        return $this->success([
            'total_journals' => $totalJournals,
            'posted_journals' => $postedJournals,
            'draft_journals' => $draftJournals,
            'total_debits' => (float) $totalDebits,
            'total_credits' => (float) $totalCredits,
            'is_balanced' => abs($totalDebits - $totalCredits) < 0.01,
        ]);
    }
}
