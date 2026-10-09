<?php

namespace Tests\Feature\Regression;

use App\Http\Middleware\EnsureAdminTwoFactor;
use App\Livewire\Auth\RegisterWizard;
use App\Models\ActivityLog;
use App\Models\AdminUser;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Export;
use App\Models\Journal;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\EmailAddressChangedNotification;
use App\Services\BankFileExporters\CsvBankExporter;
use App\Services\ExportService;
use App\Services\ReportExportService;
use App\Services\TwoFactorService;
use App\Support\Csv;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
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

    // ── S2: admin panel second factor and audit trail ───────────

    private function makeAdmin(bool $withTwoFactor = true): AdminUser
    {
        $admin = AdminUser::create([
            'name' => 'Platform Admin',
            'email' => 'root@mybooks.test',
            'password' => 'correct-horse-battery',
            'role' => AdminUser::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);

        if ($withTwoFactor) {
            $admin->forceFill([
                'two_factor_secret' => Crypt::encryptString(self::SECRET),
                'two_factor_recovery_codes' => app(TwoFactorService::class)->hashRecoveryCodes(['AAAA1111-BBBB2222']),
                'two_factor_confirmed_at' => now(),
            ])->save();
        }

        return $admin;
    }

    /** Signed in to the admin panel with 2FA completed. */
    private function actingAsAdmin(): AdminUser
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'admin')->withSession([
            'admin_two_factor_verified' => true,
            'admin_password_hash' => EnsureAdminTwoFactor::passwordFingerprint($admin),
        ]);

        return $admin;
    }

    public function test_s2_admin_sign_in_needs_the_second_factor_and_has_no_remember_me(): void
    {
        $admin = $this->makeAdmin();

        $this->get(route('admin.login'))->assertOk()->assertDontSee('name="remember"', false);

        $this->post(route('admin.login'), ['email' => $admin->email, 'password' => 'correct-horse-battery', 'remember' => 'on'])
            ->assertRedirect(route('admin.two-factor.challenge'));
        $this->assertGuest('admin');
        $this->get(route('admin.tenants.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.two-factor.challenge'))->assertOk()->assertSee('Two-factor authentication');

        $this->post(route('admin.two-factor.verify'), ['code' => '000000'])->assertSessionHas('error');
        $this->assertGuest('admin');

        $this->post(route('admin.two-factor.verify'), ['code' => (new Google2FA)->getCurrentOtp(self::SECRET)])
            ->assertRedirect(route('admin.tenants.index'));
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertNull($admin->fresh()->remember_token);
        $this->get(route('admin.tenants.index'))->assertOk();
    }

    public function test_s2_an_admin_without_2fa_must_set_it_up_before_anything_else(): void
    {
        $admin = $this->makeAdmin(withTwoFactor: false);

        $this->post(route('admin.login'), ['email' => $admin->email, 'password' => 'correct-horse-battery'])
            ->assertRedirect(route('admin.two-factor.setup'));
        $this->get(route('admin.tenants.index'))->assertRedirect(route('admin.two-factor.setup'));
        $this->get(route('admin.users.index'))->assertRedirect(route('admin.two-factor.setup'));

        $this->get(route('admin.two-factor.setup'))->assertOk();
        $secret = session('admin_two_factor_secret');
        $this->post(route('admin.two-factor.confirm'), ['code' => (new Google2FA)->getCurrentOtp($secret)])
            ->assertRedirect(route('admin.two-factor.recovery-codes'));
        $this->get(route('admin.two-factor.recovery-codes'))->assertOk()->assertSee('only time they');

        $this->assertNotNull($admin->fresh()->two_factor_confirmed_at);
        $this->get(route('admin.tenants.index'))->assertOk();
    }

    public function test_s2_an_admin_session_without_the_second_factor_is_signed_out(): void
    {
        // e.g. an old remember-me cookie: signed in, but 2FA never done.
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'admin');

        $this->get(route('admin.tenants.index'))->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_s2_admin_account_locks_after_repeated_wrong_passwords_from_any_address(): void
    {
        $admin = $this->makeAdmin();

        foreach (range(1, 5) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.1.0.{$i}"])
                ->post(route('admin.login'), ['email' => $admin->email, 'password' => 'wrong']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.2.0.1'])
            ->post(route('admin.login'), ['email' => $admin->email, 'password' => 'correct-horse-battery'])
            ->assertSessionHasErrors('email');
        $this->assertFalse(session()->has('admin_two_factor:id'));

        $this->assertSame(4, ActivityLog::where('admin_user_id', $admin->id)->where('action', ActivityLog::ACTION_LOGIN_FAILED)->count());
        $this->assertTrue(ActivityLog::where('admin_user_id', $admin->id)->where('action', ActivityLog::ACTION_ACCOUNT_LOCKED)->exists());
    }

    public function test_s2_admin_changes_are_logged_with_who_and_before_and_after(): void
    {
        [$tenant, , $subscription] = $this->createTenantWithSubscription(['is_active' => true]);
        $admin = $this->actingAsAdmin();
        $oldEnd = $subscription->ends_at->toDateTimeString();

        $this->patch(route('admin.tenants.toggle-status', $tenant))->assertRedirect();
        $this->patch(route('admin.tenants.extend-subscription', $tenant), ['extension_days' => 30])->assertRedirect();
        $this->post(route('admin.users.store'), [
            'name' => 'Second Admin', 'email' => 'second@mybooks.test', 'role' => AdminUser::ROLE_VIEWER,
            'password' => 'Another-long-Passw0rd!', 'password_confirmation' => 'Another-long-Passw0rd!',
        ])->assertRedirect(route('admin.users.index'));

        $logs = ActivityLog::where('admin_user_id', $admin->id)->orderBy('id')->get();

        $suspend = $logs->firstWhere('model_type', Tenant::class);
        $this->assertNotNull($suspend);
        $this->assertNull($suspend->tenant_id); // not shown in the business's own log
        $this->assertStringContainsString('root@mybooks.test', $suspend->description);
        $this->assertEquals(['is_active' => 1], array_map('intval', $suspend->old_values));
        $this->assertEquals(['is_active' => 0], array_map('intval', $suspend->new_values));
        $this->assertNotEmpty($suspend->integrity_hash);

        $extend = $logs->firstWhere('model_type', Subscription::class);
        $this->assertNotNull($extend);
        $this->assertStringStartsWith(substr($oldEnd, 0, 10), (string) $extend->old_values['ends_at']);
        $this->assertNotEquals($extend->old_values['ends_at'], $extend->new_values['ends_at']);

        $created = $logs->firstWhere('action', ActivityLog::ACTION_CREATED);
        $this->assertSame('second@mybooks.test', $created->new_values['email']);
        $this->assertArrayNotHasKey('password', $created->new_values);
    }

    // ── S5: password change ends other sign-ins ─────────────────

    private const NEW_PASSWORD = 'N3w-Long-Passw0rd!';

    /** The session value another device holds for this user. */
    private function sessionFingerprint(User $user): string
    {
        return Auth::guard('web')->hashPasswordForCookie($user->fresh()->getAuthPassword());
    }

    /** Pretend to be the user's other browser, signed in before the change. */
    private function assertOtherDeviceIsSignedOut(User $user, string $oldFingerprint): void
    {
        auth()->forgetGuards();
        $this->flushSession();
        $this->actingAs($user->fresh())
            ->withSession(['password_hash_web' => $oldFingerprint, 'two_factor_verified' => true])
            ->get(route('profile.edit'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    private function addDatabaseSession(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $user->id, 'ip_address' => '10.9.9.9', 'user_agent' => 'Other',
            'payload' => '', 'last_activity' => time(),
        ]);
    }

    public function test_s5_changing_your_password_signs_out_other_devices_and_api_tokens(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->createAuthenticatedUser();
        $old = $this->sessionFingerprint($user);
        $user->createToken('phone');
        $this->addDatabaseSession($user, 'other-browser-session');

        $this->put(route('password.update'), [
            'current_password' => 'password',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertSessionHasNoErrors();

        // This browser stays signed in.
        $this->get(route('profile.edit'))->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->assertFalse(DB::table('sessions')->where('id', 'other-browser-session')->exists());
        $this->assertOtherDeviceIsSignedOut($user, $old);
    }

    public function test_s5_a_password_reset_ends_every_sign_in(): void
    {
        [$tenant] = $this->createTenantWithSubscription();
        $user = $this->createUserForTenant($tenant);
        $old = $this->sessionFingerprint($user);
        $user->createToken('phone');
        $token = Password::createToken($user);

        $this->post(route('password.store'), [
            'token' => $token, 'email' => $user->email,
            'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ])->assertRedirect(route('login'));

        $this->assertSame(0, $user->tokens()->count());
        $this->assertOtherDeviceIsSignedOut($user, $old);
    }

    public function test_s5_a_password_set_by_an_admin_signs_the_user_out(): void
    {
        $manager = $this->createAuthenticatedUser(['edit users']);
        $staff = $this->createUserForTenant($this->tenant);
        $old = $this->sessionFingerprint($staff);
        $staff->createToken('phone');

        $this->put(route('settings.users.update', $staff), [
            'name' => $staff->name, 'email' => $staff->email, 'is_active' => 1,
            'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ])->assertRedirect(route('settings.users'));

        $this->assertSame(0, $staff->tokens()->count());
        // The manager who made the change is not affected.
        $this->get(route('profile.edit'))->assertOk();
        $this->assertAuthenticatedAs($manager);

        $this->assertOtherDeviceIsSignedOut($staff, $old);
    }

    public function test_s5_changing_an_admin_password_ends_that_admins_other_sessions(): void
    {
        $admin = $this->makeAdmin();
        $other = AdminUser::create([
            'name' => 'Other', 'email' => 'other@mybooks.test', 'password' => 'old-Passw0rd!',
            'role' => AdminUser::ROLE_ADMIN, 'is_active' => true, 'two_factor_confirmed_at' => now(),
        ]);
        $other->forceFill(['two_factor_confirmed_at' => now()])->save();
        $otherFingerprint = EnsureAdminTwoFactor::passwordFingerprint($other);

        $this->actingAs($admin, 'admin')->withSession([
            'admin_two_factor_verified' => true,
            'admin_password_hash' => EnsureAdminTwoFactor::passwordFingerprint($admin),
        ])->put(route('admin.users.update', $other), [
            'name' => 'Other', 'email' => 'other@mybooks.test', 'role' => AdminUser::ROLE_ADMIN,
            'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ])->assertRedirect(route('admin.users.index'));

        // The other admin's existing session.
        auth('admin')->logout();
        $this->flushSession();
        $this->actingAs($other->fresh(), 'admin')
            ->withSession(['admin_two_factor_verified' => true, 'admin_password_hash' => $otherFingerprint])
            ->get(route('admin.tenants.index'))
            ->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    // ── S6: changing the email address ──────────────────────────

    public function test_s6_changing_your_email_needs_the_password(): void
    {
        Notification::fake();
        $user = $this->createAuthenticatedUser();
        $old = $user->email;

        $this->patch(route('profile.update'), ['name' => $user->name, 'email' => 'thief@evil.test'])
            ->assertSessionHasErrors('current_password');
        $this->patch(route('profile.update'), ['name' => $user->name, 'email' => 'thief@evil.test', 'current_password' => 'wrong'])
            ->assertSessionHasErrors('current_password');
        $this->assertSame($old, $user->fresh()->email);
        Notification::assertNothingSent();

        // Other profile changes still don't need it.
        $this->patch(route('profile.update'), ['name' => 'New Name', 'email' => $old])->assertSessionHasNoErrors();
        $this->assertSame('New Name', $user->fresh()->name);
    }

    public function test_s6_the_old_address_is_told_when_the_email_changes(): void
    {
        Notification::fake();
        $user = $this->createAuthenticatedUser(['edit users']);
        $old = $user->email;

        // A business admin changing someone else's email.
        $staff = $this->createUserForTenant($this->tenant);
        $staffOld = $staff->email;
        $this->put(route('settings.users.update', $staff), ['name' => $staff->name, 'email' => 'staff@new.test', 'is_active' => 1])
            ->assertRedirect(route('settings.users'));

        Notification::assertSentOnDemand(EmailAddressChangedNotification::class, function ($n, $channels, $notifiable) use ($staffOld) {
            return $notifiable->routes['mail'] === $staffOld && $n->changedByAdmin;
        });

        // Changing your own.
        $this->patch(route('profile.update'), ['name' => $user->name, 'email' => 'me@new.test', 'current_password' => 'password'])
            ->assertSessionHasNoErrors();
        $this->assertSame('me@new.test', $user->fresh()->email);

        Notification::assertSentOnDemand(EmailAddressChangedNotification::class, function ($n, $channels, $notifiable) use ($old) {
            return $notifiable->routes['mail'] === $old && $n->newEmail === 'me@new.test' && ! $n->changedByAdmin;
        });
    }

    // ── S8: sign-up limits ──────────────────────────────────────

    private function signUpThroughWizard(Plan $plan, int $n)
    {
        return Livewire::test(RegisterWizard::class)
            ->set('plan_id', $plan->id)
            ->set('company_name', "Bot Co {$n}")
            ->set('separate_company_email', true)
            ->set('company_email', "co{$n}@bots.test")
            ->set('name', "Bot {$n}")
            ->set('email', "bot{$n}@bots.test")
            ->set('password', self::NEW_PASSWORD)
            ->call('register');
    }

    public function test_s8_the_registration_wizard_limits_sign_ups_per_address(): void
    {
        Notification::fake();
        $plan = Plan::factory()->create(['is_active' => true]);

        foreach (range(1, 3) as $n) {
            $this->signUpThroughWizard($plan, $n)->assertHasNoErrors();
            auth()->logout();
        }

        $this->signUpThroughWizard($plan, 4)->assertHasErrors('email');

        $this->assertSame(3, User::where('email', 'like', '%@bots.test')->count());
        $this->assertFalse(Tenant::where('email', 'co4@bots.test')->exists());
        Notification::assertSentTimes(VerifyEmail::class, 3);
    }

    public function test_s8_resending_the_verification_email_is_capped(): void
    {
        Notification::fake();
        $this->createAuthenticatedUser([], ['email_verified_at' => null]);

        $this->post(route('verification.send'))->assertRedirect();
        $this->post(route('verification.send'))->assertRedirect();
        $this->post(route('verification.send'))->assertStatus(429);

        Notification::assertSentTimes(VerifyEmail::class, 2);
    }
}
