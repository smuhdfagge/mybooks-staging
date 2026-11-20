<?php

namespace App\Services\Journals;

use App\Services\JournalService;

/**
 * The journal one kind of document posts (audit MB-51): its lines, taken
 * out of JournalService a document at a time, with no change to what is
 * posted. JournalService still owns the steps every document shares
 * (account codes, adding a line, posting in place of the document's old
 * journal, stock costing), and its create*Journal methods, the ones
 * JournalServiceInterface names, hand over to these.
 */
abstract class DocumentPosting
{
    public function __construct(protected JournalService $journals) {}
}
