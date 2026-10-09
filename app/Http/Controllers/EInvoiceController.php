<?php

namespace App\Http\Controllers;

use App\Actions\EInvoicing\SubmitInvoiceToNrs;
use App\Models\CreditNote;
use App\Models\EInvoiceSubmission;
use App\Models\Invoice;
use App\Services\EInvoicing\EInvoiceException;
use Illuminate\Http\RedirectResponse;

/**
 * E-invoices list, and the Submit / Retry / Check buttons (session 18).
 * Sending only records status; it never changes the books.
 */
class EInvoiceController extends Controller
{
    public function index()
    {
        return view('e-invoices.index');
    }

    public function submitInvoice(Invoice $invoice, SubmitInvoiceToNrs $submit): RedirectResponse
    {
        return $this->send($invoice, $submit);
    }

    public function submitCreditNote(CreditNote $creditNote, SubmitInvoiceToNrs $submit): RedirectResponse
    {
        return $this->send($creditNote, $submit);
    }

    /** Asks NRS where a pending document stands. */
    public function check(EInvoiceSubmission $submission, SubmitInvoiceToNrs $submit): RedirectResponse
    {
        try {
            $row = $submit->check($submission);
        } catch (EInvoiceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $this->answer($row);
    }

    private function send(Invoice|CreditNote $document, SubmitInvoiceToNrs $submit): RedirectResponse
    {
        try {
            $row = $submit->handle($document, auth()->id());
        } catch (EInvoiceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $this->answer($row);
    }

    private function answer(EInvoiceSubmission $row): RedirectResponse
    {
        return match ($row->status) {
            'accepted' => back()->with('success', 'NRS accepted it. IRN: '.$row->irn),
            'pending' => back()->with('success', 'Sent to NRS. NRS has not finished with it yet; the answer is picked up automatically.'),
            'rejected' => back()->with('error', (string) $row->last_error),
            default => back()->with('error', (string) $row->last_error ?: 'It could not be sent to NRS.'),
        };
    }
}
