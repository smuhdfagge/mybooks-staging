<?php

namespace Tests\Feature\Banks;

use App\Models\Bank;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 'mybooks:check-encrypted' says how much encrypted data this server can
 * no longer read, and for which tenants — the question to answer first
 * when a key has changed, before deciding whether to hunt for the old one
 * or ask people to type the values in again.
 */
class CheckEncryptedCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_nothing_when_everything_reads(): void
    {
        Bank::factory()->create(['account_number' => '0123456789']);

        $this->artisan('mybooks:check-encrypted')
            ->expectsOutputToContain('Every encrypted field on this server can be read.')
            ->assertSuccessful();
    }

    public function test_it_finds_and_counts_what_it_cannot_read(): void
    {
        $good = Bank::factory()->create(['account_number' => '0123456789']);
        $bad = Bank::factory()->create(['account_number' => '9876543210']);
        DB::table('banks')->where('id', $bad->id)->update(['account_number' => 'not-our-ciphertext']);

        $this->artisan('mybooks:check-encrypted')
            ->expectsOutputToContain('1 value(s) cannot be read')
            ->expectsOutputToContain('APP_PREVIOUS_KEYS')
            ->assertSuccessful();

        // The readable row is not counted, and nothing was written.
        $this->assertSame('0123456789', Bank::find($good->id)->account_number);
        $this->assertSame('not-our-ciphertext', DB::table('banks')->where('id', $bad->id)->value('account_number'));
    }
}
