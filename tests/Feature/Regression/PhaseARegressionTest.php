<?php

namespace Tests\Feature\Regression;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payroll;
use App\Models\SalaryStructure;
use App\Models\StatutoryTaxTemplate;
use App\Models\TaxBracket;
use App\Models\Tenant;
use App\Services\PayrollTaxService;
use Tests\TestCase;

/**
 * Round 3, Phase A: urgent correctness fixes.
 */
class PhaseARegressionTest extends TestCase
{
    private function employeeFor(Tenant $tenant, array $attrs = []): Employee
    {
        // Only take a number when the test doesn't give one (numbers are used up when given out).
        $attrs['employee_id'] ??= Employee::generateEmployeeId($tenant->id);

        return Employee::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $tenant->id,
            'first_name' => 'Amina',
            'last_name' => 'Bello',
            'hire_date' => '2026-01-05',
        ], $attrs));
    }

    public function test_r1_two_businesses_can_both_have_employee_emp_00001(): void
    {
        [$tenantA] = $this->createTenantWithSubscription();
        [$tenantB] = $this->createTenantWithSubscription();

        $a = $this->employeeFor($tenantA);
        $b = $this->employeeFor($tenantB);

        $this->assertSame('EMP-00001', $a->employee_id);
        $this->assertSame('EMP-00001', $b->employee_id);
    }

    public function test_r1_employee_ids_are_still_unique_within_one_business(): void
    {
        [$tenant] = $this->createTenantWithSubscription();
        $this->employeeFor($tenant, ['employee_id' => 'EMP-00007']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->employeeFor($tenant, ['employee_id' => 'EMP-00007']);
    }

    public function test_r1_generator_skips_ids_already_taken_by_imported_staff(): void
    {
        [$tenant] = $this->createTenantWithSubscription();
        $this->employeeFor($tenant, ['employee_id' => 'EMP-00002']);
        // An imported employee with a free-form ID is the latest row.
        $this->employeeFor($tenant, ['employee_id' => 'STAFF-9']);

        $next = Employee::generateEmployeeId($tenant->id);

        $this->assertSame('EMP-00003', $next);
    }

    public function test_r1_api_rejects_a_duplicate_employee_id_in_the_same_business(): void
    {
        $this->createAuthenticatedUser(['view employees', 'create employees']);
        $this->employeeFor($this->tenant, ['employee_id' => 'EMP-00010']);

        $this->postJson(route('api.employees.store'), [
            'employee_id' => 'EMP-00010',
            'first_name' => 'Musa',
            'last_name' => 'Garba',
            'email' => 'musa@example.com',
            'hire_date' => '2026-02-01',
        ])->assertStatus(422)->assertJsonValidationErrors('employee_id');
    }

    // ── A3: PAYE ────────────────────────────────────────────────

    private function applyNigeria2026(int $tenantId): void
    {
        StatutoryTaxTemplate::where('country_code', 'NGA')->where('tax_year', 2026)->sole()->applyToTenant($tenantId);
    }

    public function test_a3_the_nigeria_2026_template_has_the_nta_2025_bands(): void
    {
        $template = StatutoryTaxTemplate::where('country_code', 'NGA')->where('is_current', true)->sole();

        $this->assertSame(2026, (int) $template->tax_year);
        $this->assertSame([0, 15, 18, 21, 23, 25], array_map(fn ($b) => (int) $b['rate'], $template->brackets));
        $this->assertEquals([800000, 3000000, 12000000, 25000000, 50000000, null], array_column($template->brackets, 'max'));
    }

    public function test_a3_monthly_pay_is_taxed_on_annual_bands(): void
    {
        [$tenant] = $this->createTenantWithSubscription();
        $this->applyNigeria2026($tenant->id);

        // ₦447,500 a month = ₦5,370,000 a year:
        // 0% on 800k, 15% on 2.2m (330,000), 18% on 2.37m (426,600) = 756,600 a year.
        $result = app(PayrollTaxService::class)->calculateTax(447500, $tenant->id, 0, 'monthly');

        $this->assertSame('progressive', $result['method']);
        $this->assertEqualsWithDelta(63050.00, $result['tax'], 0.01);
    }

    public function test_a3_applying_a_template_replaces_brackets_of_the_other_period(): void
    {
        [$tenant] = $this->createTenantWithSubscription();
        TaxBracket::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Old monthly', 'min_amount' => 0, 'max_amount' => null,
            'rate' => 5, 'fixed_amount' => 0, 'period' => 'monthly', 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->applyNigeria2026($tenant->id);

        $this->assertSame(0, TaxBracket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('period', 'monthly')->where('is_active', true)->count());
    }

    public function test_a3_payroll_run_deducts_pension_and_nhf_before_tax(): void
    {
        $this->createAuthenticatedUser(['create payroll', 'view payroll']);
        $this->applyNigeria2026($this->tenant->id);

        $employee = $this->employeeFor($this->tenant);
        $structure = SalaryStructure::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Officer',
            'basic_salary' => 500000,
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);
        $structure->items()->create(['type' => 'deduction', 'name' => 'Pension (8%)', 'amount_type' => 'percentage', 'amount' => 8, 'is_taxable' => true, 'sort_order' => 0]);
        $structure->items()->create(['type' => 'deduction', 'name' => 'NHF', 'amount_type' => 'percentage', 'amount' => 2.5, 'is_taxable' => true, 'sort_order' => 1]);
        $structure->items()->create(['type' => 'deduction', 'name' => 'Cooperative', 'amount_type' => 'fixed', 'amount' => 10000, 'sort_order' => 2]);
        $employee->update(['salary_structure_id' => $structure->id]);

        $this->post(route('payroll.generate'), ['month' => '2026-03', 'employee_ids' => [$employee->id]])
            ->assertSessionHasNoErrors();

        $payroll = Payroll::where('employee_id', $employee->id)->sole();
        // 500,000 - 40,000 pension - 12,500 NHF = 447,500 taxable (the cooperative deduction is not a relief).
        $this->assertEqualsWithDelta(63050.00, (float) $payroll->tax_deduction, 0.01);
        $this->assertEqualsWithDelta(500000 - 40000 - 12500 - 10000 - 63050, (float) $payroll->net_salary, 0.01);
    }

    // ── U1, U2: broken screens ──────────────────────────────────

    /** @return array<string, string> view path => contents */
    private function bladeViews(): array
    {
        $views = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($it as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $views[str_replace(resource_path('views').'/', '', $file->getPathname())] = file_get_contents($file->getPathname());
            }
        }

        return $views;
    }

    public function test_u1_views_have_no_inline_event_handlers(): void
    {
        $offenders = [];
        foreach ($this->bladeViews() as $path => $html) {
            // Strip Blade comments, which may mention the old attributes.
            $html = preg_replace('/\{\{--.*?--\}\}/s', '', $html);
            if (preg_match_all('/<[^>]*\son(click|submit|change|input|load|error)\s*=/i', $html, $m)) {
                $offenders[] = $path.' ('.count($m[0]).')';
            }
        }

        $this->assertSame([], $offenders, 'Inline handlers are blocked by the CSP; use data-confirm, data-print, data-call etc.');
    }

    public function test_u1_every_inline_script_carries_the_csp_nonce(): void
    {
        $offenders = [];
        foreach ($this->bladeViews() as $path => $html) {
            // Livewire's @script blocks are handled by Livewire.
            $html = preg_replace('/@script.*?@endscript/s', '', $html);
            if (preg_match_all('/<script(?![^>]*\b(nonce|src|type="application\/(ld\+)?json")\b)[^>]*>/i', $html, $m)) {
                $offenders[] = $path;
            }
        }

        $this->assertSame([], $offenders);
    }

    public function test_u1_the_page_includes_the_confirm_handler(): void
    {
        $this->createAuthenticatedUser(['view items']);
        $item = Item::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->get(route('items.show', $item))
            ->assertOk()
            ->assertSee('window.__mbDomActions', false)
            ->assertDontSee('onsubmit=', false);
    }

    public function test_u2_create_bill_survives_item_text_with_line_breaks_and_quotes(): void
    {
        $this->createAuthenticatedUser(['create bills']);
        Item::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => "O'Brien's Nuts & Bolts",
            'description' => "Line one\nLine two",
            'is_active' => true,
        ]);

        $html = $this->get(route('bills.create'))->assertOk()->getContent();

        // A raw line break inside a JavaScript string is a syntax error that
        // stops the whole form working.
        $this->assertStringNotContainsString("Line one\nLine two", $html);
        // And the names must not reach the page as HTML entities inside JS.
        $this->assertStringNotContainsString('O&#039;Brien', $html);
        $this->assertStringNotContainsString('Nuts &amp;amp; Bolts', $html);
    }

    // ── S1: 2FA settings pages ──────────────────────────────────

    private function userWithTwoFactor(): \App\Models\User
    {
        $user = $this->createAuthenticatedUser();
        $user->forceFill([
            'two_factor_secret' => \Illuminate\Support\Facades\Crypt::encryptString('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => \Illuminate\Support\Facades\Crypt::encryptString(json_encode(['aaaa-bbbb'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }

    public function test_s1_signed_in_without_finishing_2fa_cannot_open_recovery_codes_or_setup(): void
    {
        $this->userWithTwoFactor(); // signed in (e.g. remember-me cookie), 2FA not completed this session

        $this->get(route('two-factor.recovery-codes'))->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();

        $this->actingAs($this->user);
        $this->get(route('two-factor.setup'))->assertRedirect(route('two-factor.challenge'));
    }

    public function test_s1_recovery_codes_need_the_password_again(): void
    {
        $this->userWithTwoFactor();

        $this->withSession(['two_factor_verified' => true])
            ->get(route('two-factor.recovery-codes'))
            ->assertRedirect(route('password.confirm'));

        $this->withSession(['two_factor_verified' => true, 'auth.password_confirmed_at' => time()])
            ->get(route('two-factor.recovery-codes'))
            ->assertOk();
    }

    public function test_s1_an_active_2fa_secret_cannot_be_replaced_through_setup(): void
    {
        $user = $this->userWithTwoFactor();
        $before = $user->fresh()->two_factor_secret;

        $newSecret = app(\App\Services\TwoFactorService::class)->generateSecret();
        $code = (new \PragmaRX\Google2FA\Google2FA)->getCurrentOtp($newSecret);

        $this->withSession([
            'two_factor_verified' => true,
            'auth.password_confirmed_at' => time(),
            'two_factor_secret' => $newSecret,
        ])->post(route('two-factor.confirm'), ['code' => $code]);

        $this->assertSame($before, $user->fresh()->two_factor_secret);
    }

    // ── I1, I3, I4: API rules ───────────────────────────────────

    public function test_i1_sync_only_returns_what_the_user_may_view(): void
    {
        $this->createAuthenticatedUser(['view customers']);
        Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Invoice::withoutEvents(fn () => Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => Customer::factory()->create(['tenant_id' => $this->tenant->id])->id,
        ]));

        $data = $this->getJson(route('api.sync.index'))->assertOk()->json('data.data');

        $this->assertArrayHasKey('customers', $data);
        $this->assertArrayNotHasKey('invoices', $data);
        $this->assertArrayNotHasKey('bills', $data);

        $this->getJson(route('api.sync.entity', 'invoices'))->assertForbidden();
        $this->getJson(route('api.sync.entity', 'customers'))->assertOk();
        $this->assertArrayNotHasKey('invoices', $this->getJson(route('api.sync.status'))->json('data.counts'));
    }

    private function apiInvoice(array $attrs = []): Invoice
    {
        return Invoice::withoutEvents(fn () => Invoice::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'customer_id' => Customer::factory()->create(['tenant_id' => $this->tenant->id])->id,
        ], $attrs)));
    }

    public function test_i3_api_cannot_mark_an_invoice_paid(): void
    {
        $this->createAuthenticatedUser(['view invoices', 'edit invoices']);
        $invoice = $this->apiInvoice(['status' => 'unpaid']);

        $this->putJson(route('api.invoices.status', $invoice), ['status' => 'paid'])->assertStatus(422);

        $this->assertSame('unpaid', $invoice->fresh()->status);
    }

    public function test_i3_api_cannot_cancel_an_invoice_with_payments(): void
    {
        $this->createAuthenticatedUser(['view invoices', 'edit invoices']);
        $invoice = $this->apiInvoice(['status' => 'partial', 'amount_paid' => 500, 'balance_due' => 575]);

        $this->putJson(route('api.invoices.status', $invoice), ['status' => 'cancelled'])->assertStatus(422);
        $this->assertSame('partial', $invoice->fresh()->status);
    }

    public function test_i3_api_can_issue_a_draft_and_cancel_an_unpaid_invoice(): void
    {
        $this->createAuthenticatedUser(['view invoices', 'edit invoices']);
        $draft = $this->apiInvoice(['status' => 'draft']);
        $unpaid = $this->apiInvoice(['status' => 'unpaid']);

        $this->putJson(route('api.invoices.status', $draft), ['status' => 'unpaid'])->assertOk();
        $this->putJson(route('api.invoices.status', $unpaid), ['status' => 'cancelled'])->assertOk();

        $this->assertSame('unpaid', $draft->fresh()->status);
        $this->assertSame('cancelled', $unpaid->fresh()->status);
        // An issued invoice can't go back to draft through the API.
        $this->putJson(route('api.invoices.status', $draft), ['status' => 'draft'])->assertStatus(422);
    }

    public function test_i4_api_expense_approval_needs_an_admin_who_did_not_raise_it(): void
    {
        $clerk = $this->createAuthenticatedUser(['view expenses', 'edit expenses', 'create expenses']);
        $expense = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Expense::STATUS_PENDING_APPROVAL,
            'created_by' => $clerk->id,
        ]);

        // Not an admin
        $this->postJson(route('api.expenses.approve', $expense))->assertForbidden();

        // An admin approving their own expense
        \App\Models\Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => null]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $clerk->assignRole('admin');
        $this->postJson(route('api.expenses.approve', $expense))->assertForbidden();

        $this->assertSame(Expense::STATUS_PENDING_APPROVAL, $expense->fresh()->status);

        // Another admin can
        $manager = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id]);
        $manager->givePermissionTo(['view expenses', 'edit expenses']);
        $manager->assignRole('admin');
        $this->actingAs($manager)->postJson(route('api.expenses.approve', $expense))->assertOk();
        $this->assertSame(Expense::STATUS_APPROVED, $expense->fresh()->status);
    }

    public function test_i4_api_cannot_create_an_expense_already_approved_or_paid(): void
    {
        $this->createAuthenticatedUser(['view expenses', 'create expenses']);
        $account = \App\Models\ChartOfAccount::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'expense']);

        $this->postJson(route('api.expenses.store'), [
            'expense_account_id' => $account->id,
            'name' => 'Fuel',
            'expense_date' => '2026-09-01',
            'amount' => 5000,
            'status' => 'paid',
        ])->assertStatus(422)->assertJsonValidationErrors('status');
    }

    // ── R5: queue on shared hosting ─────────────────────────────

    public function test_r5_a_long_job_is_not_handed_to_a_second_worker_while_it_runs(): void
    {
        $retryAfter = config('queue.connections.database.retry_after');

        foreach (glob(app_path('Jobs/*.php')) as $file) {
            $class = 'App\\Jobs\\'.basename($file, '.php');
            $timeout = (new \ReflectionClass($class))->getDefaultProperties()['timeout'] ?? 60;
            $this->assertGreaterThan($timeout, $retryAfter, "{$class} can run {$timeout}s but the queue retries after {$retryAfter}s, so it would run twice");
        }
    }

    public function test_r5_the_scheduler_runs_the_queue_by_default(): void
    {
        $this->assertTrue(config('mybooks.queue_work_from_scheduler'));

        $commands = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->map(fn ($e) => $e->command ?? '')
            ->filter(fn ($c) => str_contains($c, 'queue:work'));

        $this->assertCount(1, $commands);
    }

    // ── O1: backups ─────────────────────────────────────────────

    private function fakeDump(): void
    {
        $this->partialMock(\App\Services\BackupService::class, function ($mock) {
            $mock->shouldReceive('dumpDatabase')->andReturnUsing(function (string $path) {
                file_put_contents($path, "-- MariaDB dump\nCREATE TABLE invoices (id int);\n");
            });
        });
    }

    public function test_o1_backup_writes_database_and_files_to_every_disk(): void
    {
        \Illuminate\Support\Facades\Storage::fake('backups');
        \Illuminate\Support\Facades\Storage::fake('offsite');
        config(['mybooks.backup.disks' => ['backups', 'offsite']]);
        $this->fakeDump();

        $upload = storage_path('app/private/o1-test/receipt.txt');
        \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($upload));
        file_put_contents($upload, 'receipt');

        try {
            $this->artisan('mybooks:backup')->assertSuccessful();
        } finally {
            \Illuminate\Support\Facades\File::deleteDirectory(dirname($upload));
        }

        foreach (['backups', 'offsite'] as $disk) {
            $files = \Illuminate\Support\Facades\Storage::disk($disk)->files('mybooks-backups');
            $this->assertCount(1, $files, "one backup on {$disk}");
        }

        $zipPath = \Illuminate\Support\Facades\Storage::disk('offsite')->path($files[0]);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertStringContainsString('CREATE TABLE invoices', $zip->getFromName('database.sql'));
        $this->assertSame('receipt', $zip->getFromName('files/private/o1-test/receipt.txt'));
        $zip->close();

        $this->assertNotNull(app(\App\Services\BackupService::class)->lastSuccess());
    }

    public function test_o1_old_backups_are_removed_but_the_newest_is_always_kept(): void
    {
        $disk = \Illuminate\Support\Facades\Storage::fake('backups');
        config(['mybooks.backup.disks' => ['backups'], 'mybooks.backup.keep_days' => 30]);

        foreach (['old-1.zip' => 60, 'old-2.zip' => 45, 'recent.zip' => 3] as $name => $daysAgo) {
            $disk->put("mybooks-backups/{$name}", 'x');
            touch($disk->path("mybooks-backups/{$name}"), now()->subDays($daysAgo)->getTimestamp());
        }

        $this->assertSame(2, app(\App\Services\BackupService::class)->cleanup());
        $this->assertSame(['mybooks-backups/recent.zip'], $disk->files('mybooks-backups'));

        // If every backup is old (e.g. backups stopped), the newest stays.
        touch($disk->path('mybooks-backups/recent.zip'), now()->subDays(90)->getTimestamp());
        $this->assertSame(0, app(\App\Services\BackupService::class)->cleanup());
    }

    public function test_o1_a_failed_backup_fails_the_command(): void
    {
        \Illuminate\Support\Facades\Storage::fake('backups');
        config(['mybooks.backup.disks' => ['backups']]);
        $this->partialMock(\App\Services\BackupService::class, function ($mock) {
            $mock->shouldReceive('dumpDatabase')->andThrow(new \RuntimeException('mysqldump: Access denied'));
        });

        $this->artisan('mybooks:backup')->assertFailed();
        $this->assertSame([], \Illuminate\Support\Facades\Storage::disk('backups')->allFiles());
    }

    public function test_o1_backup_is_scheduled_daily(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($e) => str_contains($e->command ?? '', 'mybooks:backup'));

        $this->assertCount(1, $events);
        $this->assertSame('30 1 * * *', $events->first()->expression);
    }

    // ── O2: error alerts ────────────────────────────────────────

    /** @return \Illuminate\Support\Collection<int, \Symfony\Component\Mailer\SentMessage> */
    private function sentMail()
    {
        return app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
    }

    public function test_o2_server_errors_are_emailed_once_an_hour_without_personal_data(): void
    {
        config([
            'mail.default' => 'array',
            'mybooks.error_alerts.enabled' => true,
            'mybooks.error_alerts.email' => 'alerts@example.com',
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        $boom = fn () => new \RuntimeException('SQLSTATE[HY000]: General error');
        report($boom());
        report($boom()); // same error again: not emailed twice

        $this->assertCount(1, $this->sentMail());
        $email = $this->sentMail()->first()->getOriginalMessage();
        $this->assertSame('alerts@example.com', $email->getTo()[0]->getAddress());
        $this->assertStringContainsString('RuntimeException', $email->getSubject());
        $this->assertStringContainsString('PhaseARegressionTest.php', $email->getTextBody());
    }

    public function test_o2_alerts_are_capped_per_hour(): void
    {
        config([
            'mail.default' => 'array',
            'mybooks.error_alerts.enabled' => true,
            'mybooks.error_alerts.email' => 'alerts@example.com',
            'mybooks.error_alerts.max_per_hour' => 3,
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        // Six distinct errors (different lines).
        report(new \RuntimeException('error 1'));
        report(new \RuntimeException('error 2'));
        report(new \RuntimeException('error 3'));
        report(new \RuntimeException('error 4'));
        report(new \RuntimeException('error 5'));
        report(new \RuntimeException('error 6'));

        $this->assertCount(3, $this->sentMail());
    }

    public function test_o2_logs_rotate_daily_by_default(): void
    {
        // A server without LOG_STACK in .env gets daily, rotated files.
        $this->assertStringContainsString("env('LOG_STACK', 'daily')", file_get_contents(config_path('logging.php')));
        $this->assertMatchesRegularExpression('/^LOG_STACK=daily$/m', file_get_contents(base_path('.env.example')));
    }
}
