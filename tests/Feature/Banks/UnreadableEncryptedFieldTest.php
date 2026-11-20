<?php

namespace Tests\Feature\Banks;

use App\Casts\SafeEncrypted;
use App\Models\Bank;
use App\Models\Customer;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * A stored value that cannot be decrypted must cost that field, not the
 * page.
 *
 * On mybooks.cloud one tenant could not open Banks and could not record a
 * payment: both screens show bank account numbers, which are encrypted
 * with APP_KEY, and theirs had been written under a key the application
 * no longer had. The stock 'encrypted' cast throws while Blade is
 * rendering, so one unreadable field became a 500 for the whole screen.
 *
 * Nothing here recovers the data — only the original key does, put back
 * in APP_PREVIOUS_KEYS. This is about the business still being able to
 * work, and type the number in again.
 */
class UnreadableEncryptedFieldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        SafeEncrypted::forgetReported();
    }

    /** Write ciphertext no key of ours can read, as a foreign key would leave. */
    private function corrupt(string $table, int $id, string $column): void
    {
        DB::table($table)->where('id', $id)->update([
            $column => 'eyJpdiI6ImZvcmVpZ24ta2V5IiwidmFsdWUiOiJub3Qtb3Vycy==',
        ]);
    }

    public function test_an_unreadable_field_reads_as_empty_rather_than_throwing(): void
    {
        $bank = Bank::factory()->create(['account_number' => '0123456789']);
        $this->corrupt('banks', $bank->id, 'account_number');

        $fresh = Bank::withoutGlobalScope('tenant')->find($bank->id);

        $this->assertNull($fresh->account_number);
        $this->assertSame($bank->name, $fresh->name, 'The rest of the row still reads normally.');
    }

    public function test_a_readable_field_is_unaffected(): void
    {
        $bank = Bank::factory()->create(['account_number' => '0123456789']);

        // The cast still encrypts and decrypts ordinary values, in the same
        // format as before, so existing rows keep working.
        $this->assertSame('0123456789', Bank::withoutGlobalScope('tenant')->find($bank->id)->account_number);
        $this->assertNotSame('0123456789', DB::table('banks')->where('id', $bank->id)->value('account_number'));
    }

    public function test_it_says_so_in_the_log(): void
    {
        Log::spy();
        $bank = Bank::factory()->create(['account_number' => '0123456789']);
        $this->corrupt('banks', $bank->id, 'account_number');

        Bank::withoutGlobalScope('tenant')->find($bank->id)->account_number;

        // Otherwise a blank field looks like data nobody ever entered.
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_the_payment_form_still_opens(): void
    {
        $user = $this->createAuthenticatedUser(['create payments-received']);
        $bank = Bank::factory()->create(['tenant_id' => $user->tenant_id, 'account_number' => '0123456789']);
        $this->corrupt('banks', $bank->id, 'account_number');

        // The screen the tenant was locked out of.
        $this->get(route('payments-received.create'))->assertOk()
            ->assertSee($bank->name);
    }

    public function test_the_same_holds_for_the_other_encrypted_models(): void
    {
        // Customers, vendors, employees and billing cards carry encrypted
        // fields too, so the same key problem would have taken those pages
        // down as well.
        $customer = Customer::factory()->create(['tax_number' => '12345678-0001']);
        $this->corrupt('customers', $customer->id, 'tax_number');
        $this->assertNull(Customer::withoutGlobalScope('tenant')->find($customer->id)->tax_number);

        $vendor = Vendor::factory()->create(['tax_number' => '12345678-0002']);
        $this->corrupt('vendors', $vendor->id, 'tax_number');
        $this->assertNull(Vendor::withoutGlobalScope('tenant')->find($vendor->id)->tax_number);
    }
}
