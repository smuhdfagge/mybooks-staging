<?php

namespace App\Console\Commands;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use Illuminate\Console\Command;

/**
 * Marks draft and sent quotations whose expiry date has passed as expired.
 * Runs daily; running it twice changes nothing more.
 */
class ExpireQuotations extends Command
{
    protected $signature = 'quotations:expire';

    protected $description = 'Mark quotations past their expiry date as expired';

    public function handle(): int
    {
        $count = 0;

        Quotation::withoutGlobalScopes()
            ->whereIn('status', [QuotationStatus::Draft->value, QuotationStatus::Sent->value])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', today())
            ->whereNull('deleted_at')
            ->chunkById(200, function ($quotations) use (&$count) {
                foreach ($quotations as $quotation) {
                    $quotation->update(['status' => QuotationStatus::Expired->value]);
                    $count++;
                }
            });

        $this->info("{$count} quotation(s) marked as expired.");

        return self::SUCCESS;
    }
}
