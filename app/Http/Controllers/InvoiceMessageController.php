<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\MessageSetting;
use App\Services\Messaging\CustomerMessenger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "Send reminder" by SMS or WhatsApp from an unpaid invoice (session 16).
 */
class InvoiceMessageController extends Controller
{
    public function store(Request $request, Invoice $invoice, CustomerMessenger $messenger)
    {
        $validated = $request->validate(['channel' => ['required', Rule::in(MessageSetting::CHANNELS)]]);

        $result = $messenger->remind($invoice, $validated['channel'], $request->user()->id);

        if (is_string($result)) {
            return redirect()->route('invoices.show', $invoice)->with('error', CustomerMessenger::REASONS[$result] ?? 'The reminder could not be sent.');
        }

        $result->refresh();
        $when = $result->send_after ? ' It will go at '.$result->send_after->setTimezone(config('mybooks.messaging.timezone'))->format('g:ia').' (no reminders at night).' : '';

        return redirect()->route('invoices.show', $invoice)->with($result->status === 'failed' ? 'error' : 'success', $result->status === 'failed'
            ? "The {$result->channelLabel()} reminder failed: {$result->error}"
            : "{$result->channelLabel()} reminder to {$result->maskedTo()} is on its way.{$when}");
    }
}
