<?php

namespace Tests\Feature\Regression;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Export;
use App\Models\Journal;
use App\Models\User;
use App\Services\BankFileExporters\CsvBankExporter;
use App\Services\ExportService;
use App\Services\ReportExportService;
use App\Support\Csv;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Round 3, Phase D: security fixes S2 to S8.
 */
class PhaseDSecurityTest extends TestCase
{
    // ── S3: journal account options ─────────────────────────────

    public function test_s3_journal_pages_add_account_options_as_text_not_html(): void
    {
        $this->createAuthenticatedUser(['create journals', 'edit journals']);
        ChartOfAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => '<img src=x onerror=alert(1)>',
            'is_active' => true,
        ]);
        $journal = Journal::withoutEvents(fn () => Journal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'draft',
            'is_posted' => false,
        ]));

        foreach ([route('journals.create'), route('journals.edit', $journal)] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            // The "add row" script used to paste account names into an HTML
            // string, so a name with markup ran as code.
            $this->assertStringNotContainsString('${a.name}</option>', $html);
            $this->assertStringNotContainsString('${accountOptions}', $html);
            $this->assertStringContainsString('select.add(new Option(', $html);
        }
    }

    // ── S4: CSV formula cells ───────────────────────────────────

    public function test_s4_data_export_csv_turns_formula_names_into_text(): void
    {
        $this->createAuthenticatedUser();
        Storage::fake('exports');
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => '=HYPERLINK("http://evil.test","Click")',
        ]);

        $export = Export::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => Export::TYPE_CUSTOMERS,
            'format' => Export::FORMAT_CSV,
            'status' => Export::STATUS_PENDING,
        ]);
        app(ExportService::class)->processExport($export);

        $csv = Storage::disk('exports')->get($export->fresh()->file_path);
        $this->assertStringContainsString('"\'=HYPERLINK(', $csv);
        $this->assertStringNotContainsString(',"=HYPERLINK(', $csv);
    }

    public function test_s4_report_and_bank_csv_escape_formulas_but_keep_numbers(): void
    {
        $response = (new ReportExportService)->exportToCsv(
            [['@SUM(A1:A9)', '-1,500.00', '+2348012345678x', 250]],
            ['Name', 'Amount', 'Phone', 'Qty'],
        );
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString("'@SUM(A1:A9)", $csv);
        $this->assertStringContainsString(',"-1,500.00",', $csv);
        $this->assertStringContainsString("'+2348012345678x", $csv);

        $bank = (new CsvBankExporter)->generate(collect([[
            'employee_name' => '-2+3+cmd|/C calc!A0',
            'account_number' => '0123456789',
            'amount' => 1000,
        ]]));
        $this->assertStringContainsString("'-2+3+cmd", $bank);
    }

    public function test_s4_a_re_imported_export_loses_the_escape_mark(): void
    {
        $this->assertSame('=A1', Csv::unescapeCell(Csv::escapeCell('=A1')));
        $this->assertSame('-12.5', Csv::escapeCell('-12.5'));
        $this->assertSame("'plain", Csv::unescapeCell("'plain"));
    }

    // ── S7: 2FA codes ───────────────────────────────────────────

    private const SECRET = 'JBSWY3DPEHPK3PXP';

    /** A signed-out user with 2FA on and old-style (encrypted) recovery codes. */
    private function twoFactorUser(array $recoveryCodes = ['AAAA1111-BBBB2222']): User
    {
        [$tenant] = $this->createTenantWithSubscription();
        $user = $this->createUserForTenant($tenant);
        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString(self::SECRET),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($recoveryCodes)),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }

    private function runTwoFactorMigration(): void
    {
        (require database_path('migrations/2026_10_04_200001_harden_two_factor_codes.php'))->up();
    }

    private function challenge(User $user, array $input, string $ip = '10.0.0.1')
    {
        return $this->withSession(['two_factor:user_id' => $user->id])
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('two-factor.verify'), $input);
    }

    public function test_s7_2fa_attempts_are_limited_per_account_not_just_per_ip(): void
    {
        $user = $this->twoFactorUser();

        // Five wrong codes, each from a different address.
        foreach (range(1, 5) as $i) {
            $this->challenge($user, ['code' => '000000'], "10.0.1.{$i}");
        }
        $this->assertGuest();

        // The right code from yet another address is still refused for now.
        $code = (new Google2FA)->getCurrentOtp(self::SECRET);
        $this->challenge($user, ['code' => $code], '10.0.2.1')->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_s7_a_2fa_code_cannot_be_used_twice(): void
    {
        $user = $this->twoFactorUser();
        $code = (new Google2FA)->getCurrentOtp(self::SECRET);

        $this->challenge($user, ['code' => $code])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        auth()->logout();
        $this->flushSession();

        // Someone who saw the code tries it again within its 30-90 seconds.
        $this->challenge($user, ['code' => $code])->assertSessionHas('error', 'Invalid authentication code.');
        $this->assertGuest();

        // Same through the API login.
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'password', 'device_name' => 'phone', 'two_factor_code' => $code,
        ])->assertStatus(422)->assertSee('Invalid two-factor authentication code.');
    }

    public function test_s7_new_recovery_codes_are_stored_hashed_and_shown_once(): void
    {
        $this->createAuthenticatedUser();
        $step = (new Google2FA)->getCurrentOtp(self::SECRET);

        $response = $this->withSession([
            'auth.password_confirmed_at' => time(),
            'two_factor_secret' => self::SECRET,
        ])->post(route('two-factor.confirm'), ['code' => $step]);

        $codes = session('two_factor_new_recovery_codes');
        $this->assertCount(8, $codes);
        $response->assertRedirect(route('two-factor.recovery-codes'));

        $stored = $this->user->fresh()->two_factor_recovery_codes;
        foreach ($codes as $code) {
            $this->assertStringNotContainsString($code, $stored);
        }
        // Not an encrypted payload either: APP_KEY alone can't read them.
        $this->assertIsArray(json_decode($stored, true));

        // Shown on the page straight after; later visits show how many are
        // left, not the codes.
        $this->withSession(['two_factor_verified' => true])
            ->get(route('two-factor.recovery-codes'))->assertOk()->assertSee($codes[0]);
        $page = $this->get(route('two-factor.recovery-codes'))->assertOk()->getContent();
        $this->assertStringNotContainsString($codes[0], $page);
        $this->assertStringContainsString('<strong>8</strong> unused recovery codes', $page);
    }

    public function test_s7_existing_recovery_codes_still_work_after_the_migration_and_only_once(): void
    {
        $user = $this->twoFactorUser(['AAAA1111-BBBB2222', 'CCCC3333-DDDD4444']);

        $this->runTwoFactorMigration();
        $hashed = $user->fresh()->two_factor_recovery_codes;
        $this->assertStringNotContainsString('AAAA1111', $hashed);

        // Running it again leaves hashed rows alone.
        $this->runTwoFactorMigration();
        $this->assertSame($hashed, $user->fresh()->two_factor_recovery_codes);

        // Codes are not case sensitive.
        $this->challenge($user, ['recovery_code' => 'aaaa1111-bbbb2222'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        auth()->logout();
        $this->flushSession();
        $this->challenge($user, ['recovery_code' => 'AAAA1111-BBBB2222'])->assertSessionHas('error', 'Invalid recovery code.');
        $this->assertGuest();
    }
}
