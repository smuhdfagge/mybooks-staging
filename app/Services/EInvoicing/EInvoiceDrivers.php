<?php

namespace App\Services\EInvoicing;

use App\Models\EInvoiceSetting;

/**
 * Picks the driver for a business (session 18). Real calls need the
 * business's keys, the feature on, e-invoicing switched on for it and an
 * NRS address in config. Without those nothing is sent: on production
 * always, elsewhere too unless EINVOICING_DRIVER=log (the simulator).
 */
class EInvoiceDrivers
{
    /** ready, simulated, needs_keys, needs_address, off */
    public function state(EInvoiceSetting $settings): string
    {
        if (config('mybooks.einvoicing.driver') === 'log' && ! app()->isProduction()) {
            return 'simulated';
        }
        if (! $settings->hasKeys()) {
            return 'needs_keys';
        }
        if (! filled(config('mybooks.einvoicing.base_urls.'.($settings->isLiveEnvironment() ? 'live' : 'sandbox')))) {
            return 'needs_address';
        }
        if (config('mybooks.einvoicing.driver') === 'nrs' || config('mybooks.einvoicing.driver') === 'auto') {
            return 'ready';
        }

        return 'needs_keys';
    }

    /** Documents can really be sent (or simulated). */
    public function isLive(EInvoiceSetting $settings): bool
    {
        return in_array($this->state($settings), ['ready', 'simulated'], true);
    }

    /** @throws NotSetUp */
    public function for(EInvoiceSetting $settings): EInvoiceDriver
    {
        return match ($this->state($settings)) {
            'ready' => app(NrsDriver::class),
            'simulated' => app(LogDriver::class),
            'needs_address' => throw new NotSetUp('The NRS address has not been set by the MyBooks team yet, so nothing was sent.'),
            default => throw new NotSetUp,
        };
    }
}
