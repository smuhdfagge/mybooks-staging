<?php

namespace App\Console\Commands;

use App\Actions\EInvoicing\SubmitInvoiceToNrs;
use App\Enums\EInvoiceStatus;
use App\Jobs\SubmitEInvoice;
use App\Models\EInvoiceSetting;
use App\Models\EInvoiceSubmission;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\EInvoiceNotReportedNotification;
use App\Services\EInvoicing\EInvoiceDrivers;
use App\Services\EInvoicing\PayloadBuilder;
use Illuminate\Console\Command;
use Throwable;

/**
 * Hourly e-invoicing upkeep (session 18):
 *  1. asks NRS about documents left "pending" too long (there are no
 *     webhooks, so this is how a late answer is picked up);
 *  2. sends failed documents again, once their wait is over, up to the
 *     attempt limit (config mybooks.einvoicing.max_attempts);
 *  3. warns the business about B2C invoices above the NRS threshold that are
 *     close to the 24-hour reporting limit and still not accepted.
 * Only businesses with e-invoicing switched on are touched. One document
 * failing does not stop the rest.
 */
class RetryEInvoices extends Command
{
    protected $signature = 'einvoice:retry';

    protected $description = 'Retry failed NRS e-invoice submissions and warn about unreported B2C invoices';

    public function handle(SubmitInvoiceToNrs $submit, EInvoiceDrivers $drivers, PayloadBuilder $payloads): int
    {
        if (! config('mybooks.features.e_invoicing')) {
            return self::SUCCESS;
        }

        $checked = $retried = 0;

        // 1. Pending too long: ask NRS.
        $stale = now()->subMinutes((int) config('mybooks.einvoicing.pending_stale_minutes', 15));
        EInvoiceSubmission::withoutGlobalScopes()->where('status', EInvoiceStatus::Pending->value)
            ->where('last_attempt_at', '<=', $stale)->orderBy('id')->limit(200)->get()
            ->each(function (EInvoiceSubmission $row) use ($submit, $drivers, &$checked) {
                if (! $this->active($row->tenant_id, $drivers)) {
                    return;
                }
                try {
                    $submit->check($row);
                    $checked++;
                } catch (Throwable $e) {
                    report($e);
                }
            });

        // 2. Failed and due.
        $max = (int) config('mybooks.einvoicing.max_attempts', 5);
        EInvoiceSubmission::withoutGlobalScopes()->where('status', EInvoiceStatus::Failed->value)
            ->where('attempts', '<', $max)->where('next_retry_at', '<=', now())->orderBy('id')->limit(200)->get()
            ->each(function (EInvoiceSubmission $row) use ($drivers, &$retried) {
                if (! $this->active($row->tenant_id, $drivers)) {
                    return;
                }
                SubmitEInvoice::dispatch($row->invoice_id ? 'invoice' : 'credit_note', (int) ($row->invoice_id ?: $row->credit_note_id));
                $retried++;
            });

        // 3. B2C invoices close to the 24-hour limit.
        $warned = $this->warnAboutLateB2c($payloads);

        $this->info("{$checked} pending checked, {$retried} failed sent again, {$warned} businesses warned");

        return self::SUCCESS;
    }

    private function active(int $tenantId, EInvoiceDrivers $drivers): bool
    {
        $settings = EInvoiceSetting::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->first();

        return $settings && $settings->enabled && $drivers->isLive($settings);
    }

    private function warnAboutLateB2c(PayloadBuilder $payloads): int
    {
        $hours = (int) config('mybooks.einvoicing.b2c_report_hours', 24);
        $threshold = (float) config('mybooks.einvoicing.b2c_threshold', 50000);
        $warned = 0;

        $settings = EInvoiceSetting::query()->withoutGlobalScopes()->where('enabled', true)->get();
        foreach ($settings as $setting) {
            try {
                $late = Invoice::query()->withoutGlobalScopes()->with('customer')
                    ->where('tenant_id', $setting->tenant_id)
                    ->whereNotIn('status', ['draft', 'cancelled'])
                    ->where('total', '>', $threshold)
                    ->where('created_at', '<=', now()->subHours($hours - 4))   // 4 hours or less to go
                    ->where('created_at', '>=', now()->subDays(7))
                    ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('e_invoice_submissions as s')
                        ->whereColumn('s.invoice_id', 'invoices.id')
                        ->where(fn ($w) => $w->where('s.status', EInvoiceStatus::Accepted->value)->orWhereNotNull('s.late_warned_at')))
                    ->orderBy('id')->limit(500)->get()
                    ->filter(fn (Invoice $i) => $payloads->kind($i) === 'b2c');

                if ($late->isEmpty()) {
                    continue;
                }

                foreach ($late as $invoice) {
                    $row = EInvoiceSubmission::forDocument($invoice) ?? tap(new EInvoiceSubmission(['invoice_id' => $invoice->id, 'status' => EInvoiceStatus::NotSubmitted->value, 'kind' => 'b2c']), function ($new) use ($invoice) {
                        $new->skipTenantGuard = true;
                        $new->tenant_id = $invoice->tenant_id;
                    });
                    $row->forceFill(['late_warned_at' => now()])->save();
                }

                $users = User::query()->where('tenant_id', $setting->tenant_id)->where('is_active', true)->permission('submit e-invoices')->get();
                foreach ($users as $user) {
                    $user->notify(new EInvoiceNotReportedNotification($late->count(), $hours));
                }
                $warned++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $warned;
    }
}
