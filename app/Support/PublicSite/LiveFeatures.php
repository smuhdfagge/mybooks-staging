<?php

namespace App\Support\PublicSite;

use App\Services\BankFeeds\MonoProvider;
use App\Services\Messaging\MessagingDrivers;

/**
 * Which optional features the public pages may talk about (home page plan,
 * decision 12). A feature counts as live only when its switch is on AND the
 * keys it needs are set, so the pages never promise something that would
 * send nothing.
 */
class LiveFeatures
{
    /** SMS reminders and receipts go out for real. */
    public function sms(): bool
    {
        return (bool) config('mybooks.features.sms_whatsapp')
            && app(MessagingDrivers::class)->isLive('sms');
    }

    /** WhatsApp reminders and receipts go out for real. */
    public function whatsapp(): bool
    {
        return (bool) config('mybooks.features.sms_whatsapp')
            && app(MessagingDrivers::class)->isLive('whatsapp');
    }

    /** Bank accounts can be linked and their transactions pulled in. */
    public function bankFeeds(): bool
    {
        return (bool) config('mybooks.features.bank_feeds')
            && app(MonoProvider::class)->isLive();
    }

    /**
     * Invoices can be sent to the Nigeria Revenue Service. Each business adds
     * its own NRS keys; what MyBooks needs is the switch and the live address.
     */
    public function eInvoicing(): bool
    {
        return (bool) config('mybooks.features.e_invoicing')
            && filled(config('mybooks.einvoicing.base_urls.live'));
    }
}
