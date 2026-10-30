<?php

namespace App\Console\Commands;

use App\Models\Bank;
use App\Models\BillingCard;
use App\Models\Customer;
use App\Models\EInvoiceSetting;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Reads every encrypted field in the database and reports which ones this
 * server can no longer decrypt.
 *
 * Encrypted values are tied to APP_KEY. Change that key without keeping
 * the old one in APP_PREVIOUS_KEYS and every value written under it
 * becomes unreadable — and because the fields are bank account numbers,
 * tax numbers and payroll details, the pages showing them are the ones a
 * business uses daily.
 *
 * Run this after a key change, or when someone reports a field that has
 * gone blank. Put a recovered key into APP_PREVIOUS_KEYS and run it again
 * to confirm the figures drop to zero before telling anyone it is fixed.
 *
 * Reads only. It never writes, and never prints a decrypted value.
 */
class CheckEncryptedFields extends Command
{
    protected $signature = 'mybooks:check-encrypted {--tenant= : Only this tenant}';

    protected $description = 'Report encrypted fields this server can no longer read';

    /** @var array<class-string, array<int, string>> */
    private const FIELDS = [
        Bank::class => ['account_number', 'routing_number', 'swift_code', 'iban'],
        BillingCard::class => ['authorization_code', 'customer_code'],
        Customer::class => ['tax_number'],
        EInvoiceSetting::class => ['api_key', 'api_secret', 'service_id', 'business_id', 'public_key', 'certificate'],
        Employee::class => ['salary', 'bank_account_number', 'bank_routing_number', 'tax_id', 'rsa_pin', 'nhf_number'],
        Vendor::class => ['tax_number'],
    ];

    public function handle(): int
    {
        // Every business, on purpose (audit MB-26: tenant-scoped queries need
        // an explicit context; see Tenant::acrossAll()).
        return Tenant::acrossAll(fn () => $this->runForEveryBusiness());
    }

    private function runForEveryBusiness(): int
    {
        $tenantId = $this->option('tenant');
        $rows = [];
        $unreadable = 0;

        foreach (self::FIELDS as $model => $fields) {
            $table = (new $model)->getTable();
            $hasTenant = DB::getSchemaBuilder()->hasColumn($table, 'tenant_id');

            foreach ($fields as $field) {
                $query = DB::table($table)->whereNotNull($field)->where($field, '!=', '');

                if ($tenantId && $hasTenant) {
                    $query->where('tenant_id', $tenantId);
                }

                $bad = [];
                $total = 0;

                // Straight off the table, so the model's own cast cannot
                // quietly turn a failure into null before it is counted.
                $query->select('id', $field, ...($hasTenant ? ['tenant_id'] : []))
                    ->orderBy('id')
                    ->chunk(500, function ($chunk) use ($field, $hasTenant, &$bad, &$total) {
                        foreach ($chunk as $row) {
                            $total++;
                            try {
                                Crypt::decryptString($row->{$field});
                            } catch (DecryptException) {
                                $bad[] = $hasTenant ? (int) $row->tenant_id : 0;
                            }
                        }
                    });

                if ($bad !== []) {
                    $unreadable += count($bad);
                    $tenants = collect($bad)->unique()->sort()->filter()->implode(', ');
                    $rows[] = [class_basename($model), $field, count($bad).' of '.$total, $tenants ?: 'n/a'];
                }
            }
        }

        if ($rows === []) {
            $this->info('Every encrypted field on this server can be read.');

            return self::SUCCESS;
        }

        $this->table(['Model', 'Field', 'Unreadable', 'Tenants'], $rows);
        $this->newLine();
        $this->warn("{$unreadable} value(s) cannot be read with the keys this server has.");
        $this->line('They were encrypted under a different APP_KEY. Add the old key to');
        $this->line('APP_PREVIOUS_KEYS in .env (comma separated), run "php artisan config:cache",');
        $this->line('then run this again. If the old key is gone, the values have to be typed in');
        $this->line('again: nothing can recover them.');

        // Not a failure of the command: it did its job and found something.
        return self::SUCCESS;
    }
}
