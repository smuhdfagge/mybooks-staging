<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\JournalResource;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'journal_date' => 'required|date',
            'reference' => 'nullable|string|max:100',
            'description' => 'required|string|max:500',
            'entries' => 'required|array|min:2',
            'entries.*.account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'entries.*.description' => 'nullable|string|max:255',
            'entries.*.debit' => 'required|numeric|min:0',
            'entries.*.credit' => 'required|numeric|min:0',
        ]);

        // Validate that debits equal credits
        $totalDebit = collect($validated['entries'])->sum('debit');
        $totalCredit = collect($validated['entries'])->sum('credit');

        if (abs($totalDebit - $totalCredit) > 0.01) {
            return $this->validationError([
                'entries' => ['Total debits must equal total credits. Debit: '.$totalDebit.', Credit: '.$totalCredit],
            ]);
        }

        // Validate each entry has either debit or credit (not both, not neither)
        foreach ($validated['entries'] as $index => $entry) {
            if ($entry['debit'] > 0 && $entry['credit'] > 0) {
                return $this->validationError([
                    "entries.{$index}" => ['An entry cannot have both debit and credit amounts.'],
                ]);
            }
            if ($entry['debit'] == 0 && $entry['credit'] == 0) {
                return $this->validationError([
                    "entries.{$index}" => ['An entry must have either a debit or credit amount.'],
                ]);
            }
        }

        // Validate accounts belong to tenant
        $accountIds = collect($validated['entries'])->pluck('account_id');
        $validAccounts = ChartOfAccount::where('tenant_id', $tenantId)
            ->whereIn('id', $accountIds)
            ->count();

        if ($validAccounts !== $accountIds->count()) {
            return $this->error('One or more accounts do not belong to your organization.', 422);
        }

        DB::beginTransaction();
        try {
            $journal = Journal::create([
                'tenant_id' => $tenantId,
                'journal_number' => Journal::generateNumber($tenantId),
                'journal_date' => $validated['journal_date'],
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
                    'debit' => $entry['debit'],
                    'credit' => $entry['credit'],
                ]);
            }

            DB::commit();

            $journal->load(['entries.account', 'createdBy']);

            return $this->created(new JournalResource($journal), 'Journal entry created successfully');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Failed to create journal entry: '.$e->getMessage(), 500);
        }
    }

    /**
     * Update a journal entry (only if not posted)
     */
    public function update(Request $request, Journal $journal): JsonResponse
    {
        if ($journal->is_posted) {
            return $this->error('Cannot modify a posted journal entry.', 422);
        }

        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'journal_date' => 'sometimes|date',
            'reference' => 'nullable|string|max:100',
            'description' => 'sometimes|string|max:500',
            'entries' => 'sometimes|array|min:2',
            'entries.*.account_id' => ['required_with:entries', Rule::exists('chart_of_accounts', 'id')->where('tenant_id', $tenantId)],
            'entries.*.description' => 'nullable|string|max:255',
            'entries.*.debit' => 'required_with:entries|numeric|min:0',
            'entries.*.credit' => 'required_with:entries|numeric|min:0',
        ]);

        if (isset($validated['entries'])) {
            $totalDebit = collect($validated['entries'])->sum('debit');
            $totalCredit = collect($validated['entries'])->sum('credit');

            if (abs($totalDebit - $totalCredit) > 0.01) {
                return $this->validationError([
                    'entries' => ['Total debits must equal total credits.'],
                ]);
            }

            DB::beginTransaction();
            try {
                $journal->entries()->delete();

                foreach ($validated['entries'] as $entry) {
                    JournalEntry::create([
                        'journal_id' => $journal->id,
                        'account_id' => $entry['account_id'],
                        'description' => $entry['description'] ?? $journal->description,
                        'debit' => $entry['debit'],
                        'credit' => $entry['credit'],
                    ]);
                }

                $validated['total_debit'] = $totalDebit;
                $validated['total_credit'] = $totalCredit;
                unset($validated['entries']);

                $journal->update($validated);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();

                return $this->error('Failed to update journal entry: '.$e->getMessage(), 500);
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

        DB::beginTransaction();
        try {
            // Same posting code as the web app (checks balance, updates
            // account balances, marks posted).
            $journal->post();

            DB::commit();

            $journal->load(['entries.account', 'createdBy']);

            return $this->success(new JournalResource($journal), 'Journal entry posted successfully');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Failed to post journal entry: '.$e->getMessage(), 500);
        }
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

        DB::beginTransaction();
        try {
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

            DB::commit();

            $reversingJournal->load(['entries.account', 'createdBy']);

            return $this->success(new JournalResource($reversingJournal), 'Reversing journal entry created successfully');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Failed to create reversing entry: '.$e->getMessage(), 500);
        }
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
