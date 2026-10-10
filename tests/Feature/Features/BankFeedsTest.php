<?php

namespace Tests\Feature\Features;

use App\Actions\LockDates\UpdateLockDates;
use App\Actions\Payments\DeletePaymentReceived;
use App\Actions\Payments\RecordPaymentReceived;
use App\Livewire\Banks\BankFeedLinesTable;
use App\Models\Bank;
use App\Models\BankFeedConnection;
use App\Models\BankFeedLine;
use App\Models\BankTransaction;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\PaymentReceived;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BankFeeds\BankFeedLinker;
use App\Services\BankFeeds\BankFeedProviders;
use App\Services\BankFeeds\BankFeedSync;
use App\Services\BankFeeds\LineActions;
use App\Services\BankFeeds\Matcher;
use App\Services\BankReconciliationService;
use Database\Seeders\PlanSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;
use Livewire\Livewire;
use Tests\Support\AssertsLedger;
use Tests\TestCase;

/**
 * Session 17: bank feeds through Mono. Every Mono call is faked; the tests
 * check that nothing real is called and that no key reaches the log.
 */
class BankFeedsTest extends TestCase
{
    use AssertsLedger;

    private const SECRET = 'sk_test_do-not-log-9f8e7d';

    private const HOOK = 'whsec_do-not-log-1a2b3c';

    private const FULL_NUMBER = '0123456789';

    private const PERMS = [
        'view banks', 'edit banks', 'reconcile banks', 'create payments-received', 'view payments-received',
        'create expenses', 'view expenses', 'create journals', 'view journals', 'view invoices',
    ];

    /** The bank's side, as Mono would return it (amounts in kobo). */
    private array $monoTx = [];

    /** @var array<string, array> accounts by id */
    private array $monoAccounts = [];

    /** Account ids whose transactions call answers with this HTTP status. */
    private array $failing = [];

    /** @var array<string, array{0: int, 1: int}> status and how many calls still fail */
    private array $flaky = [];

    private int $flakyCalls = 0;

    /** @var array<string, array> accounts whose transactions call asks for a new login */
    private array $failBody = [];

    private int $pageSize = 5;

    private int $txNo = 0;

    private Bank $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-09 10:00:00'));
        Sleep::fake();
        config([
            'mybooks.features.bank_feeds' => true,
            'mybooks.bank_feeds.driver' => 'auto',
            'services.mono.secret_key' => self::SECRET,
            'services.mono.public_key' => 'test_pk_123',
            'services.mono.webhook_secret' => self::HOOK,
            'services.mono.base_url' => 'https://mono.test',
        ]);

        $this->createAuthenticatedUser(self::PERMS);
        $this->tenant->update(['name' => 'Kano Traders Ltd', 'currency' => 'NGN']);
        $this->subscription->update(['ends_at' => now()->addYear()]);
        $this->plan->update(['bank_feed_accounts_limit' => null]);
        $this->bank = $this->makeBank($this->tenant);

        $this->monoAccounts['acc_1'] = $this->account('acc_1');

        Http::preventStrayRequests();
        Http::fake([
            'mono.test/v2/accounts/initiate' => fn (Request $r) => Http::response(['status' => 'successful', 'message' => 'Created', 'data' => ['mono_url' => 'https://link.mono.test/ABC123', 'meta' => $r['meta'] ?? []]]),
            'mono.test/v2/accounts/auth' => fn (Request $r) => Http::response(['id' => $r['code'] === 'code-2' ? 'acc_2' : ($r['code'] === 'code-usd' ? 'acc_usd' : 'acc_1')]),
            'mono.test/v2/accounts/*/transactions*' => fn (Request $r) => $this->transactions($r),
            'mono.test/v2/accounts/*/balance' => fn (Request $r) => Http::response(['status' => 'successful', 'data' => ['balance' => 1]]),
            'mono.test/v2/accounts/*/unlink' => Http::response(['status' => 'successful', 'message' => 'Account unlinked']),
            'mono.test/v2/accounts/*' => fn (Request $r) => $this->details($r),
        ]);
    }

    // ---- helpers ----------------------------------------------------------

    /** Another business; made while nobody is logged in so its default accounts land on it. */
    private function otherTenant(): Tenant
    {
        return $this->asNobody(fn () => Tenant::factory()->create());
    }

    /** Runs with nobody logged in, so rows made for another business keep their own tenant_id. */
    private function asNobody(callable $make): mixed
    {
        $me = auth()->user();
        auth()->logout();
        try {
            return $make();
        } finally {
            $this->actingAs($me);
        }
    }

    private function account(string $id, array $overrides = []): array
    {
        return $overrides + [
            'name' => 'KANO TRADERS LTD', 'currency' => 'NGN', 'type' => 'CURRENT', 'account_number' => self::FULL_NUMBER,
            'balance' => 123456700, 'institution' => ['name' => 'GTBank', 'bank_code' => '058', 'type' => 'PERSONAL_BANKING'],
        ];
    }

    private function accountIdFromUrl(string $url): string
    {
        preg_match('#/v2/accounts/([^/?]+)#', $url, $m);

        return $m[1];
    }

    private function details(Request $r)
    {
        $id = $this->accountIdFromUrl($r->url());
        $acc = $this->monoAccounts[$id] ?? null;

        return $acc
            ? Http::response(['status' => 'successful', 'message' => 'ok', 'data' => ['meta' => ['data_status' => 'AVAILABLE', 'auth_method' => 'internet_banking'], 'account' => $acc + ['id' => $id]]])
            : Http::response(['status' => 'failed', 'message' => 'Account not found'], 404);
    }

    private function transactions(Request $r)
    {
        $id = $this->accountIdFromUrl($r->url());
        if (isset($this->flaky[$id]) && $this->flaky[$id][1] > 0) {
            $this->flaky[$id][1]--;
            $this->flakyCalls++;

            return Http::response(['message' => 'slow down'], $this->flaky[$id][0]);
        }
        if (isset($this->failBody[$id])) {
            return Http::response($this->failBody[$id], 403);
        }
        if (isset($this->failing[$id])) {
            return Http::response(['status' => 'failed', 'message' => 'Upstream trouble'], $this->failing[$id]);
        }
        $this->flakyCalls += isset($this->flaky[$id]) ? 1 : 0;
        parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);
        $page = max(1, (int) ($q['page'] ?? 1));
        $all = array_values(array_filter($this->monoTx[$id] ?? [], fn ($t) => true));
        $slice = array_slice($all, ($page - 1) * $this->pageSize, $this->pageSize);
        $hasMore = $page * $this->pageSize < count($all);

        return Http::response(['status' => 'successful', 'message' => 'ok', 'data' => $slice,
            'meta' => ['total' => count($all), 'page' => $page, 'previous' => $page > 1 ? 'prev' : null, 'next' => $hasMore ? 'next' : null]]);
    }

    /** A bank transaction on Mono's side. $naira is positive. */
    private function bankTx(string $account, string $type, float $naira, string $date, string $narration, ?string $id = null): string
    {
        $id ??= 'tx'.(++$this->txNo);
        $this->monoTx[$account][] = [
            'id' => $id, 'narration' => $narration, 'amount' => (int) round($naira * 100), 'type' => $type,
            'balance' => 100000000 + $this->txNo, 'date' => $date.'T09:30:00.000Z', 'category' => null,
        ];

        return $id;
    }

    private function makeBank(Tenant $tenant, array $attributes = []): Bank
    {
        return $this->asNobody(fn () => Bank::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $tenant->id, 'name' => 'GTBank current', 'bank_name' => 'GTBank', 'account_type' => 'checking',
            'currency' => 'NGN', 'opening_balance' => 0, 'current_balance' => 0, 'is_active' => true,
        ], $attributes)));
    }

    /** Runs the whole link: start, bank page, back with the code. */
    private function link(?Bank $bank = null, string $code = 'code-1', ?string $ref = null): BankFeedConnection
    {
        $bank ??= $this->bank;
        $this->post(route('bank-feeds.start'), ['bank_id' => $bank->id])->assertRedirect(route('bank-feeds.go'))->assertSessionHas('bank_link_url', 'https://link.mono.test/ABC123');
        $connection = BankFeedConnection::where('bank_id', $bank->id)->where('status', 'pending')->latest('id')->firstOrFail();
        $this->get(route('bank-feeds.callback', ['ref' => $connection->link_ref, 'code' => $code]))->assertRedirect(route('bank-feeds.index'));

        return $connection->fresh();
    }

    private function customer(string $name = 'Musa Ibrahim Stores'): Customer
    {
        return Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => $name]);
    }

    private function invoice(Customer $customer, float $total = 50000, string $number = 'INV-000101'): Invoice
    {
        return Invoice::factory()->create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_number' => $number, 'status' => 'unpaid',
            'total' => $total, 'subtotal' => $total, 'tax_amount' => 0, 'balance_due' => $total, 'amount_paid' => 0, 'due_date' => now()->addDays(3),
        ]);
    }

    private function received(Customer $customer, float $amount, string $date, ?Invoice $invoice = null, ?string $reference = null): PaymentReceived
    {
        return app(RecordPaymentReceived::class)->handle($this->tenant->id, [
            'customer_id' => $customer->id, 'invoice_id' => $invoice?->id, 'payment_date' => $date, 'amount' => $amount,
            'payment_method' => 'bank_transfer', 'bank_id' => $this->bank->id, 'reference' => $reference, 'is_deposit' => ! $invoice,
        ], $this->user->id);
    }

    private function line(array $attributes = [], ?BankFeedConnection $connection = null): BankFeedLine
    {
        $connection ??= BankFeedConnection::withoutGlobalScopes()->where('bank_id', $this->bank->id)->first() ?? $this->connectionRow();

        return $this->asNobody(fn () => BankFeedLine::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $connection->tenant_id, 'connection_id' => $connection->id, 'bank_id' => $connection->bank_id,
            'provider_transaction_id' => 'L'.(++$this->txNo), 'date' => '2026-10-07', 'amount' => 25000, 'direction' => 'credit',
            'narration' => 'NIP TRANSFER FROM MUSA IBRAHIM', 'balance_after' => 1000000, 'status' => 'new',
        ], $attributes)));
    }

    private function connectionRow(?Bank $bank = null, ?Tenant $tenant = null, string $accountId = 'acc_1', string $status = 'linked'): BankFeedConnection
    {
        $tenant ??= $this->tenant;
        $bank ??= $tenant->is($this->tenant) ? $this->bank : $this->makeBank($tenant);

        return $this->asNobody(fn () => BankFeedConnection::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'bank_id' => $bank->id, 'provider' => 'mono', 'provider_account_id' => $accountId,
            'institution_name' => 'GTBank', 'account_name' => 'KANO TRADERS LTD', 'account_mask' => '6789', 'currency' => 'NGN',
            'status' => $status, 'linked_by' => $this->user->id, 'linked_at' => now(),
        ]));
    }

    private function monoCalls(): array
    {
        return Http::recorded(fn (Request $r) => str_contains($r->url(), 'mono.test'))->all();
    }

    private function hook(array $payload, ?string $secret = self::HOOK)
    {
        return $this->withoutMiddleware(PreventRequestForgery::class)
            ->postJson(route('bank-feeds.webhooks.mono'), $payload, $secret === null ? [] : ['mono-webhook-secret' => $secret]);
    }

    // ---- linking ----------------------------------------------------------

    public function test_linking_stores_the_connection_and_does_the_first_pull(): void
    {
        for ($i = 1; $i <= 7; $i++) { // more than one page of 5
            $this->bankTx('acc_1', $i % 2 ? 'credit' : 'debit', 1000 * $i, '2026-10-0'.min($i, 8), "TRANSACTION {$i}");
        }

        $connection = $this->link();

        $this->assertSame('linked', $connection->status);
        $this->assertSame('acc_1', $connection->provider_account_id);
        $this->assertSame('GTBank', $connection->institution_name);
        $this->assertSame('6789', $connection->account_mask);
        $this->assertSame($this->bank->id, $connection->bank_id);
        $this->assertSame($this->user->id, $connection->linked_by);
        $this->assertSame(123456700, $connection->provider_balance);
        $this->assertNotNull($connection->last_synced_at);
        $this->assertSame(7, $connection->lines()->count());
        $this->assertSame(7, BankFeedLine::where('status', 'new')->count());

        // Mono was asked the documented way.
        $initiate = collect($this->monoCalls())->first(fn ($c) => str_ends_with($c[0]->url(), '/v2/accounts/initiate'))[0];
        $this->assertSame(self::SECRET, $initiate->header('mono-sec-key')[0]);
        $this->assertSame('auth', $initiate['scope']);
        $this->assertSame($connection->link_ref, $initiate['meta']['ref']);
        $this->assertSame(route('bank-feeds.callback', ['ref' => $connection->link_ref]), $initiate['redirect_url']);
        $this->assertSame('Kano Traders Ltd', $initiate['customer']['name']);

        $first = collect($this->monoCalls())->first(fn ($c) => str_contains($c[0]->url(), '/transactions'))[0];
        parse_str((string) parse_url($first->url(), PHP_URL_QUERY), $q);
        $this->assertSame('11-07-2026', $q['start']); // 90 days back
        $this->assertSame('09-10-2026', $q['end']);
        $this->assertCount(2, collect($this->monoCalls())->filter(fn ($c) => str_contains($c[0]->url(), '/transactions'))); // two pages
    }

    public function test_the_full_account_number_is_never_stored(): void
    {
        $this->bankTx('acc_1', 'credit', 500, '2026-10-05', 'NIP');
        $this->link();

        foreach (Schema::getTableListing() as $table) {
            $table = preg_replace('/^.*\./', '', $table);
            foreach (DB::table($table)->get() as $row) {
                $this->assertStringNotContainsString(self::FULL_NUMBER, json_encode($row), "full account number found in {$table}");
            }
        }
        $this->assertSame('6789', BankFeedConnection::first()->account_mask);
    }

    public function test_kobo_become_naira_and_direction_is_kept(): void
    {
        $this->bankTx('acc_1', 'credit', 1500.50, '2026-10-06', 'NIP FROM A');
        $this->bankTx('acc_1', 'debit', 20.00, '2026-10-06', 'SMS ALERT CHARGE');
        $this->link();

        $credit = BankFeedLine::where('direction', 'credit')->firstOrFail();
        $debit = BankFeedLine::where('direction', 'debit')->firstOrFail();
        $this->assertSame('1500.50', $credit->amount);
        $this->assertSame('20.00', $debit->amount);
        $this->assertSame('2026-10-06', $credit->date->toDateString());
        $this->assertSame('NIP FROM A', $credit->narration);
        $this->assertNotNull($credit->balance_after);
    }

    public function test_linking_an_existing_or_new_account_and_one_feed_per_bank(): void
    {
        $this->bankTx('acc_1', 'credit', 500, '2026-10-05', 'NIP');
        $this->post(route('bank-feeds.start'), ['new_bank_name' => 'Main current'])->assertRedirect(route('bank-feeds.go'))->assertSessionHas('bank_link_url', 'https://link.mono.test/ABC123');
        $pending = BankFeedConnection::where('status', 'pending')->firstOrFail();
        $this->get(route('bank-feeds.callback', ['ref' => $pending->link_ref, 'code' => 'code-1']));

        $new = Bank::where('name', 'Main current')->firstOrFail();
        $this->assertSame('NGN', $new->currency);
        $this->assertSame($new->id, $pending->fresh()->bank_id);
        $this->assertSame('linked', $pending->fresh()->status);

        // That account already has a feed.
        $this->post(route('bank-feeds.start'), ['bank_id' => $new->id])->assertSessionHas('error', 'This bank account already has a bank feed.');
        // Another account of the business can have its own.
        $this->monoAccounts['acc_2'] = $this->account('acc_2', ['account_number' => '0000011111']);
        $second = $this->link($this->bank, 'code-2');
        $this->assertSame('acc_2', $second->provider_account_id);
        $this->assertSame(2, BankFeedConnection::counted()->count());
    }

    public function test_a_non_naira_account_is_refused(): void
    {
        $this->monoAccounts['acc_usd'] = $this->account('acc_usd', ['currency' => 'USD']);
        $this->post(route('bank-feeds.start'), ['bank_id' => $this->bank->id]);
        $ref = BankFeedConnection::firstOrFail()->link_ref;

        $this->get(route('bank-feeds.callback', ['ref' => $ref, 'code' => 'code-usd']))
            ->assertRedirect(route('bank-feeds.index'))
            ->assertSessionHas('error', 'Bank feeds work for naira accounts only. That account is in USD.');
        $this->assertSame(0, BankFeedConnection::count());
        $this->assertSame(0, BankFeedLine::count());
    }

    public function test_a_naira_only_bank_account_can_be_chosen(): void
    {
        $usd = $this->makeBank($this->tenant, ['name' => 'Dollar account', 'currency' => 'USD']);
        $this->post(route('bank-feeds.start'), ['bank_id' => $usd->id])->assertSessionHas('error', 'Bank feeds work for naira accounts only.');
    }

    public function test_an_unfinished_link_is_reported_and_cleaned_up(): void
    {
        $this->post(route('bank-feeds.start'), ['bank_id' => $this->bank->id]);
        $connection = BankFeedConnection::firstOrFail();

        $this->get(route('bank-feeds.callback', ['ref' => $connection->link_ref]))
            ->assertRedirect(route('bank-feeds.index'))->assertSessionHas('error');
        $this->assertSame('pending', $connection->fresh()->status);

        $this->travel(2)->days();
        $this->artisan('bankfeeds:sync')->assertSuccessful();
        $this->assertSame(0, BankFeedConnection::count());
    }

    // ---- sync -------------------------------------------------------------

    public function test_pulling_again_adds_only_new_lines(): void
    {
        $this->bankTx('acc_1', 'credit', 1000, '2026-10-05', 'FIRST', 'a1');
        $this->bankTx('acc_1', 'debit', 200, '2026-10-06', 'SECOND', 'a2');
        $connection = $this->link();
        $this->assertSame(2, BankFeedLine::count());

        $result = app(BankFeedSync::class)->pull($connection);
        $this->assertSame(0, $result['new']);
        $this->assertSame(2, BankFeedLine::count());

        $this->bankTx('acc_1', 'credit', 300, '2026-10-08', 'THIRD', 'a3');
        $result = app(BankFeedSync::class)->pull($connection->fresh());
        $this->assertSame(1, $result['new']);
        $this->assertSame(3, BankFeedLine::count());

        // The re-read overlaps by 5 days, not the full 90.
        $last = collect($this->monoCalls())->filter(fn ($c) => str_contains($c[0]->url(), '/transactions'))->last()[0];
        parse_str((string) parse_url($last->url(), PHP_URL_QUERY), $q);
        $this->assertSame('04-10-2026', $q['start']);
    }

    public function test_sync_now_asks_mono_for_a_refresh_and_reads_what_is_there(): void
    {
        $connection = $this->link();
        $this->bankTx('acc_1', 'credit', 700, '2026-10-08', 'NEW ONE');

        $this->post(route('bank-feeds.sync', $connection))->assertSessionHas('success', '1 new transaction found.');
        $this->assertNotNull($connection->fresh()->last_sync_requested_at);
        $balanceCall = collect($this->monoCalls())->first(fn ($c) => str_ends_with($c[0]->url(), '/balance'))[0];
        $this->assertSame('true', $balanceCall->header('x-realtime')[0]);

        // Clicking again within 5 minutes does not ask Mono for another live refresh.
        $before = count(collect($this->monoCalls())->filter(fn ($c) => str_ends_with($c[0]->url(), '/balance')));
        $this->post(route('bank-feeds.sync', $connection))->assertSessionHas('success');
        $this->assertSame($before, count(collect($this->monoCalls())->filter(fn ($c) => str_ends_with($c[0]->url(), '/balance'))));
    }

    public function test_the_scheduled_sync_carries_on_when_one_account_fails(): void
    {
        $one = $this->link();
        $this->monoAccounts['acc_2'] = $this->account('acc_2');
        $bank2 = $this->makeBank($this->tenant, ['name' => 'Second']);
        $two = $this->link($bank2, 'code-2');
        $other = $this->otherTenant();
        $this->monoAccounts['acc_3'] = $this->account('acc_3');
        $three = $this->connectionRow(null, $other, 'acc_3');

        $this->bankTx('acc_1', 'credit', 100, '2026-10-08', 'ONE');
        $this->bankTx('acc_2', 'credit', 200, '2026-10-08', 'TWO');
        $this->bankTx('acc_3', 'credit', 300, '2026-10-08', 'THREE');
        $this->failing['acc_1'] = 503;

        $this->artisan('bankfeeds:sync')->assertSuccessful();

        $this->assertSame(0, $one->lines()->count());
        $this->assertStringContainsString('busy', (string) $one->fresh()->last_error);
        $this->assertSame('linked', $one->fresh()->status); // temporary: stays connected
        $this->assertSame(1, $two->lines()->count());
        $this->assertSame(1, $three->lines()->withoutGlobalScopes()->count());
        $this->assertNull($two->fresh()->last_error);

        // A later run picks the failed one up.
        unset($this->failing['acc_1']);
        $this->artisan('bankfeeds:sync')->assertSuccessful();
        $this->assertSame(1, $one->lines()->count());
        $this->assertNull($one->fresh()->last_error);
    }

    public function test_busy_mono_is_retried_before_giving_up(): void
    {
        $connection = $this->link();
        $this->flaky['acc_1'] = [429, 2];
        $this->flakyCalls = 0;

        $result = app(BankFeedSync::class)->pull($connection);

        $this->assertNull($result['error']);
        $this->assertSame(3, $this->flakyCalls); // two refusals, then the answer
    }

    public function test_a_hard_error_marks_the_account_with_a_problem(): void
    {
        $connection = $this->link();
        $this->failing['acc_1'] = 400;

        $result = app(BankFeedSync::class)->pull($connection);

        $this->assertSame('error', $connection->fresh()->status);
        $this->assertStringContainsString('Upstream trouble', (string) $result['error']);
        // and a later good pull brings it back
        unset($this->failing['acc_1']);
        app(BankFeedSync::class)->pull($connection->fresh());
        $this->assertSame('linked', $connection->fresh()->status);
    }

    public function test_the_bank_asking_for_a_new_login_is_noticed_and_reconnect_starts_it(): void
    {
        $connection = $this->link();
        $this->failBody['acc_1'] = ['status' => 'failed', 'message' => 'Reauthorisation required', 'code' => 'REAUTHORISATION_REQUIRED'];

        app(BankFeedSync::class)->pull($connection);
        $this->assertSame('needs_reauthorisation', $connection->fresh()->status);

        $this->get(route('bank-feeds.index'))->assertSee('Reconnect')->assertSee('Needs you to log in again');
        $this->post(route('bank-feeds.reconnect', $connection))->assertRedirect(route('bank-feeds.go'))->assertSessionHas('bank_link_url', 'https://link.mono.test/ABC123');
        $call = collect($this->monoCalls())->last()[0];
        $this->assertSame('reauth', $call['scope']);
        $this->assertSame('acc_1', $call['account']);

        // Back from the bank: working again.
        unset($this->failBody['acc_1']);
        $ref = $connection->fresh()->link_ref;
        $this->get(route('bank-feeds.callback', ['ref' => $ref]))->assertRedirect(route('bank-feeds.index'));
        $this->assertSame('linked', $connection->fresh()->status);
    }

    public function test_the_go_page_opens_the_bank_link_with_a_plain_link(): void
    {
        // The security policy only lets forms post to this site, so the hop to Mono is a link.
        $this->post(route('bank-feeds.start'), ['bank_id' => $this->bank->id])->assertRedirect(route('bank-feeds.go'));
        $this->get(route('bank-feeds.go'))->assertOk()->assertSee('href="https://link.mono.test/ABC123"', false);

        // Without a link in the session it goes back to the list with a message.
        $this->flushSession();
        $this->get(route('bank-feeds.go'))->assertRedirect(route('bank-feeds.index'))->assertSessionHas('error');
    }

    // ---- webhook ----------------------------------------------------------

    public function test_the_webhook_refuses_a_bad_or_missing_secret_and_does_nothing(): void
    {
        $connection = $this->link();
        $this->bankTx('acc_1', 'credit', 100, '2026-10-08', 'NEW');
        $payload = ['event' => 'mono.events.account_updated', 'data' => ['account' => ['_id' => 'acc_1'], 'meta' => ['data_status' => 'AVAILABLE', 'has_new_data' => true]]];
        $calls = count($this->monoCalls());

        $this->hook($payload, 'wrong')->assertStatus(401);
        $this->hook($payload, null)->assertStatus(401);
        $this->assertSame(0, $connection->lines()->count());
        $this->assertSame($calls, count($this->monoCalls()));

        // No secret configured at all: nothing is accepted.
        config(['services.mono.webhook_secret' => null]);
        $this->hook($payload, '')->assertStatus(401);
        $this->hook($payload, self::HOOK)->assertStatus(401);
    }

    public function test_the_webhook_with_the_right_secret_pulls_new_data(): void
    {
        $connection = $this->link();
        $this->bankTx('acc_1', 'credit', 100, '2026-10-08', 'NEW');
        $payload = ['event' => 'mono.events.account_updated', 'event_id' => 'e1', 'data' => ['account' => ['_id' => 'acc_1'], 'meta' => ['data_status' => 'AVAILABLE', 'has_new_data' => true]]];

        $this->hook($payload)->assertOk()->assertJson(['received' => true]);

        $this->assertSame(1, $connection->lines()->count());

        // "No new data" is acknowledged without a pull.
        $calls = count($this->monoCalls());
        $payload['data']['meta']['has_new_data'] = false;
        $this->hook($payload)->assertOk();
        $this->assertSame($calls, count($this->monoCalls()));

        // An account MyBooks doesn't know is acknowledged and ignored.
        $payload['data']['account']['_id'] = 'acc_unknown';
        $payload['data']['meta']['has_new_data'] = true;
        $this->hook($payload)->assertOk();
        $this->assertSame($calls, count($this->monoCalls()));
    }

    public function test_the_webhook_can_mark_reauthorisation_needed_and_restore_it(): void
    {
        $connection = $this->link();

        $this->hook(['event' => 'mono.events.account_updated', 'data' => ['account' => ['_id' => 'acc_1'], 'meta' => ['sync_status' => 'REAUTHORISATION_REQUIRED']]])->assertOk();
        $this->assertSame('needs_reauthorisation', $connection->fresh()->status);

        $this->hook(['event' => 'mono.events.account_reauthorized', 'data' => ['id' => 'acc_1']])->assertOk();
        $this->assertSame('linked', $connection->fresh()->status);
    }

    public function test_the_webhook_can_finish_a_link_the_customer_never_came_back_from(): void
    {
        $this->bankTx('acc_1', 'credit', 100, '2026-10-08', 'NEW');
        $this->post(route('bank-feeds.start'), ['bank_id' => $this->bank->id]);
        $pending = BankFeedConnection::firstOrFail();

        $this->hook(['event' => 'mono.events.account_connected', 'data' => ['id' => 'acc_1', 'meta' => ['ref' => $pending->link_ref, 'data_status' => 'PROCESSING']]])->assertOk();

        $this->assertSame('linked', $pending->fresh()->status);
        $this->assertSame(1, $pending->lines()->count());
        // The customer arriving afterwards finds it done.
        $this->get(route('bank-feeds.callback', ['ref' => $pending->link_ref, 'code' => 'code-1']))->assertRedirect(route('bank-feeds.index'));
        $this->assertSame(1, BankFeedConnection::count());
    }

    public function test_the_webhook_route_is_outside_csrf_and_the_flag_hides_it(): void
    {
        $this->link();
        // Plain POST with CSRF middleware on (not withoutMiddleware): still reaches the controller.
        $this->postJson(route('bank-feeds.webhooks.mono'), ['event' => 'mono.events.account_updated'], ['mono-webhook-secret' => 'nope'])->assertStatus(401);

        config(['mybooks.features.bank_feeds' => false]);
        $this->postJson(route('bank-feeds.webhooks.mono'), [], ['mono-webhook-secret' => self::HOOK])->assertNotFound();
    }

    // ---- matching ---------------------------------------------------------

    public function test_a_line_matches_a_payment_with_the_same_amount_inside_the_date_window(): void
    {
        $this->link();
        $customer = $this->customer();
        $payment = $this->received($customer, 25000, '2026-10-06', null, 'INV-77');
        $line = $this->line(['amount' => 25000, 'date' => '2026-10-07']);

        $s = app(Matcher::class)->suggest([$line])[$line->id] ?? null;

        $this->assertNotNull($s);
        $this->assertTrue($s->candidate->record->is($payment));
        $this->assertSame('High', $s->label()); // 50 + 20 (a day apart) + 20 (customer name in narration)
        $this->assertGreaterThanOrEqual(80, $s->score);
    }

    public function test_amount_is_required_and_the_window_is_five_days(): void
    {
        $this->link();
        $customer = $this->customer();
        $this->received($customer, 25000, '2026-10-06');
        $differentAmount = $this->line(['amount' => 25000.50]);
        $tooEarly = $this->line(['amount' => 25000, 'date' => '2026-10-12']); // 6 days after
        $wrongWay = $this->line(['amount' => 25000, 'date' => '2026-10-07', 'direction' => 'debit']);
        $edge = $this->line(['amount' => 25000, 'date' => '2026-10-11']); // 5 days after

        $result = app(Matcher::class)->suggest([$differentAmount, $tooEarly, $wrongWay, $edge]);

        $this->assertArrayNotHasKey($differentAmount->id, $result);
        $this->assertArrayNotHasKey($tooEarly->id, $result);
        $this->assertArrayNotHasKey($wrongWay->id, $result);
        $this->assertArrayHasKey($edge->id, $result);
        $this->assertLessThan(80, $result[$edge->id]->score);
        $this->assertSame('Medium', $result[$edge->id]->label()); // 50 + 5, no names: still shown, but not High
    }

    public function test_one_record_is_never_matched_to_two_lines_and_the_better_line_wins(): void
    {
        $this->link();
        $payment = $this->received($this->customer(), 25000, '2026-10-07');
        $near = $this->line(['amount' => 25000, 'date' => '2026-10-07', 'narration' => 'MUSA IBRAHIM STORES']);
        $far = $this->line(['amount' => 25000, 'date' => '2026-10-10', 'narration' => 'SOMETHING ELSE']);

        $result = app(Matcher::class)->suggest([$far, $near]);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey($near->id, $result);
        $this->assertTrue($result[$near->id]->candidate->record->is($payment));

        // Once accepted for one line, the other gets nothing.
        app(LineActions::class)->accept($near, PaymentReceived::class, $payment->id, $this->user);
        $this->assertSame([], app(Matcher::class)->suggest([$far->fresh()]));
        $this->expectExceptionMessage('That record no longer fits');
        app(LineActions::class)->accept($far->fresh(), PaymentReceived::class, $payment->id, $this->user);
    }

    public function test_a_line_cannot_take_two_records_and_not_this_one_hides_a_suggestion(): void
    {
        $this->link();
        $customer = $this->customer();
        $p1 = $this->received($customer, 25000, '2026-10-06');
        $p2 = $this->received($customer, 25000, '2026-10-08');
        $line = $this->line(['amount' => 25000, 'date' => '2026-10-07']);
        $actions = app(LineActions::class);

        $first = app(Matcher::class)->suggest([$line])[$line->id]->candidate->record;
        $actions->reject($line, $first::class, $first->id, $this->user);
        $second = app(Matcher::class)->suggest([$line->fresh()])[$line->id]->candidate->record;
        $this->assertFalse($second->is($first));

        $actions->reject($line->fresh(), $second::class, $second->id, $this->user);
        $this->assertSame([], app(Matcher::class)->suggest([$line->fresh()]));
        $this->assertSame('new', $line->fresh()->status);
        $this->assertNotNull($p1->id.$p2->id);
    }

    public function test_accepting_marks_the_record_cleared_and_the_reconciliation_screen_counts_it(): void
    {
        $this->link();
        $payment = $this->received($this->customer(), 25000, '2026-10-06');
        $line = $this->line(['amount' => 25000, 'date' => '2026-10-07', 'balance_after' => 25000]);
        $this->bank->update(['opening_balance' => 0]);

        $this->assertSame(0.0, app(BankReconciliationService::class)->getReconciledBalance($this->bank));
        app(LineActions::class)->accept($line, PaymentReceived::class, $payment->id, $this->user);

        $line->refresh();
        $this->assertSame('matched', $line->status);
        $this->assertSame(PaymentReceived::class, $line->matched_type);
        $this->assertSame($this->user->id, $line->matched_by);
        $row = BankTransaction::findOrFail($line->bank_transaction_id);
        $this->assertTrue($row->is_reconciled);
        $this->assertSame('deposit', $row->type);
        $this->assertSame('2026-10-07', $row->reconciled_date->toDateString());
        $this->assertSame(25000.0, app(BankReconciliationService::class)->getReconciledBalance($this->bank));

        $this->get(route('banks.reconcile', $this->bank))->assertOk()->assertSee('25,000.00')->assertSee('Bank feed: GTBank');

        // Taking it back un-clears the record.
        app(LineActions::class)->undo($line, $this->user);
        $this->assertSame('new', $line->fresh()->status);
        $this->assertSame(0.0, app(BankReconciliationService::class)->getReconciledBalance($this->bank));
        $this->assertSame(0, BankTransaction::withTrashed()->count());
    }

    public function test_accepting_in_a_locked_period_is_allowed_because_nothing_is_posted(): void
    {
        $this->link();
        $payment = $this->received($this->customer(), 25000, '2026-10-02');
        app(UpdateLockDates::class)->handle($this->tenant, ['staff_lock_date' => '2026-10-05', 'all_users_lock_date' => null, 'reason' => null]);
        $line = $this->line(['amount' => 25000, 'date' => '2026-10-03']);

        app(LineActions::class)->accept($line, PaymentReceived::class, $payment->id, $this->user);

        $this->assertSame('matched', $line->fresh()->status);
    }

    public function test_a_transfer_is_posted_once_and_matched_on_the_other_account(): void
    {
        $this->link();
        $other = $this->makeBank($this->tenant, ['name' => 'Savings', 'account_type' => 'savings', 'chart_of_account_id' => ChartOfAccount::where('account_code', '1110')->value('id'), 'current_balance' => 1000]);
        $this->bank->update(['chart_of_account_id' => ChartOfAccount::where('account_code', '1100')->value('id'), 'current_balance' => 100000]);
        $connection = BankFeedConnection::firstOrFail();
        $this->monoAccounts['acc_2'] = $this->account('acc_2');
        $otherConnection = $this->connectionRow($other, null, 'acc_2');
        $out = $this->line(['direction' => 'debit', 'amount' => 40000, 'date' => '2026-10-06', 'narration' => 'TRF TO SAVINGS'], $connection);
        $in = $this->line(['direction' => 'credit', 'amount' => 40000, 'date' => '2026-10-07', 'narration' => 'TRF FROM CURRENT'], $otherConnection);

        $this->post(route('bank-feeds.lines.transfer', $out), ['other_bank_id' => $other->id])->assertSessionHasNoErrors()->assertRedirect(route('bank-feeds.lines'));

        $journal = Journal::where('journal_type', 'bank_transfer')->firstOrFail();
        $this->assertSame('created', $out->fresh()->status);
        $this->assertEqualsWithDelta(60000, (float) $this->bank->fresh()->current_balance, 0.001);
        $this->assertEqualsWithDelta(41000, (float) $other->fresh()->current_balance, 0.001);
        $this->assertSame(40000.0, $this->accountBalance($this->tenant->id, '1110'));
        $this->assertSame(-40000.0, $this->accountBalance($this->tenant->id, '1100'));

        // The savings account's own line offers that journal, and accepting it posts nothing more.
        $s = app(Matcher::class)->suggest([$in])[$in->id];
        $this->assertTrue($s->candidate->record->is($journal));
        $this->actingAs($this->user);
        app(LineActions::class)->accept($in, Journal::class, $journal->id, $this->user);
        $this->assertSame('matched', $in->fresh()->status);
        $this->assertSame(1, Journal::where('journal_type', 'bank_transfer')->count());
        $this->assertEqualsWithDelta(41000, (float) $other->fresh()->current_balance, 0.001);
        // and the debit side is not offered for the account it came from
        $this->assertArrayNotHasKey($out->id, app(Matcher::class)->suggest([$this->line(['direction' => 'debit', 'amount' => 40000, 'date' => '2026-10-06'], $connection)]) + [$out->id => null] === [] ? [] : []);
        $this->assertAllJournalsBalance($this->tenant->id);
    }

    // ---- creating from a line ----------------------------------------------

    public function test_a_payment_can_be_recorded_from_a_line_and_applies_to_the_invoice(): void
    {
        $this->link();
        $customer = $this->customer();
        $invoice = $this->invoice($customer, 50000);
        $line = $this->line(['amount' => 30000, 'date' => '2026-10-07', 'narration' => 'NIP MUSA IBRAHIM']);
        $before = (float) $this->bank->fresh()->current_balance;

        $this->get(route('bank-feeds.lines.show', ['line' => $line, 'customer_id' => $customer->id]))
            ->assertOk()->assertSee('INV-000101')->assertSee('Record payment received');
        $this->post(route('bank-feeds.lines.payment-received', $line), ['customer_id' => $customer->id, 'invoice_id' => $invoice->id])
            ->assertSessionHasNoErrors()->assertRedirect(route('bank-feeds.lines'));

        $line->refresh();
        $payment = PaymentReceived::firstOrFail();
        $this->assertSame('created', $line->status);
        $this->assertSame(PaymentReceived::class, $line->matched_type);
        $this->assertSame($payment->id, $line->matched_id);
        $this->assertSame('2026-10-07', $payment->payment_date->toDateString());
        $this->assertSame('30000.00', $payment->amount);
        $this->assertSame($this->bank->id, $payment->bank_id);
        $this->assertSame('partial', $invoice->fresh()->status);
        $this->assertEqualsWithDelta(20000, (float) $invoice->fresh()->balance_due, 0.001);
        $this->assertEqualsWithDelta($before + 30000, (float) $this->bank->fresh()->current_balance, 0.001);
        $this->assertTrue(BankTransaction::findOrFail($line->bank_transaction_id)->is_reconciled);
        $this->assertAllJournalsBalance($this->tenant->id);
        $this->assertStoredBalancesMatchLedger($this->tenant->id);
        $this->assertSame(1, Journal::where('reference_type', PaymentReceived::class)->count());
    }

    public function test_a_payment_from_a_line_can_be_kept_as_a_deposit_and_cannot_overpay_an_invoice(): void
    {
        $this->link();
        $customer = $this->customer();
        $invoice = $this->invoice($customer, 10000);
        $big = $this->line(['amount' => 30000]);

        $this->post(route('bank-feeds.lines.payment-received', $big), ['customer_id' => $customer->id, 'invoice_id' => $invoice->id])
            ->assertSessionHasErrors('amount');
        $this->assertSame('new', $big->fresh()->status);
        $this->assertSame(0, PaymentReceived::count());

        $this->post(route('bank-feeds.lines.payment-received', $big), ['customer_id' => $customer->id, 'invoice_id' => 'deposit'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(PaymentReceived::firstOrFail()->is_deposit);
        $this->assertSame('created', $big->fresh()->status);
        $this->assertAllJournalsBalance($this->tenant->id);
    }

    public function test_an_expense_can_be_made_from_a_line_and_counts_as_cleared_once_paid(): void
    {
        $this->link();
        $account = ChartOfAccount::where('type', 'expense')->firstOrFail();
        $line = $this->line(['direction' => 'debit', 'amount' => 8000, 'date' => '2026-10-06', 'narration' => 'POS FUEL STATION']);

        $this->post(route('bank-feeds.lines.expense', $line), ['name' => 'Fuel', 'expense_account_id' => $account->id])
            ->assertSessionHasNoErrors()->assertRedirect(route('bank-feeds.lines'));

        $expense = Expense::firstOrFail();
        $line->refresh();
        $this->assertSame('created', $line->status);
        $this->assertSame($expense->id, $line->matched_id);
        $this->assertSame('draft', $expense->status);
        $this->assertSame('8000.00', $expense->total);
        $this->assertSame($this->bank->id, $expense->bank_id);
        $this->assertSame('2026-10-06', $expense->expense_date->toDateString());
        $this->assertNull($line->bank_transaction_id); // not in the books yet
        $this->assertSame(0, Journal::where('reference_type', Expense::class)->count());

        // Approved by someone else and paid, as any expense is.
        $expense->forceFill(['status' => Expense::STATUS_APPROVED])->save();
        $this->assertTrue($expense->fresh()->markAsPaid());

        $line->refresh();
        $this->assertNotNull($line->bank_transaction_id);
        $this->assertSame('withdrawal', BankTransaction::findOrFail($line->bank_transaction_id)->type);
        $this->assertSame(1, Journal::where('reference_type', Expense::class)->count());
        $this->assertAllJournalsBalance($this->tenant->id);
    }

    public function test_a_paid_expense_is_offered_as_a_match_but_a_draft_is_not(): void
    {
        $this->link();
        $account = ChartOfAccount::where('type', 'expense')->firstOrFail();
        $make = fn (string $status) => Expense::create([
            'tenant_id' => $this->tenant->id, 'expense_number' => 'EXP-'.$status, 'name' => 'Diesel', 'expense_date' => '2026-10-06', 'expense_account_id' => $account->id,
            'amount' => 9000, 'tax_amount' => 0, 'total' => 9000, 'bank_id' => $this->bank->id, 'status' => $status, 'created_by' => $this->user->id,
        ]);
        $make('draft');
        $line = $this->line(['direction' => 'debit', 'amount' => 9000, 'date' => '2026-10-06']);
        $this->assertSame([], app(Matcher::class)->suggest([$line]));

        $paid = $make('paid');
        $s = app(Matcher::class)->suggest([$line])[$line->id];
        $this->assertTrue($s->candidate->record->is($paid));
    }

    public function test_other_income_a_bank_charge_and_a_transfer_post_balanced_journals(): void
    {
        $this->link();
        $this->bank->update(['chart_of_account_id' => ChartOfAccount::where('account_code', '1100')->value('id'), 'current_balance' => 50000]);
        $income = ChartOfAccount::where('type', 'income')->firstOrFail();
        $charges = ChartOfAccount::where('account_code', '6900')->firstOrFail();
        $in = $this->line(['amount' => 1200, 'narration' => 'INTEREST']);
        $fee = $this->line(['direction' => 'debit', 'amount' => 53.75, 'narration' => 'STAMP DUTY']);

        $this->post(route('bank-feeds.lines.other-income', $in), ['income_account_id' => $income->id, 'description' => 'Interest'])->assertSessionHasNoErrors();
        $this->post(route('bank-feeds.lines.bank-charge', $fee), ['expense_account_id' => $charges->id])->assertSessionHasNoErrors();

        $this->assertSame('created', $in->fresh()->status);
        $this->assertSame('created', $fee->fresh()->status);
        $this->assertEqualsWithDelta(50000 + 1200 - 53.75, (float) $this->bank->fresh()->current_balance, 0.001);
        $this->assertSame(1200.0, $this->accountBalance($this->tenant->id, $income->account_code));
        $this->assertSame(53.75, $this->accountBalance($this->tenant->id, '6900'));
        $this->assertSame(1200 - 53.75, $this->accountBalance($this->tenant->id, '1100'));
        $this->assertAllJournalsBalance($this->tenant->id);
        $this->assertStoredBalancesMatchLedger($this->tenant->id);
        $this->assertNotNull($in->fresh()->bank_transaction_id);

        // Wrong kind of account, or the wrong direction, is refused.
        $other = $this->line(['amount' => 10]);
        $this->post(route('bank-feeds.lines.bank-charge', $other), ['expense_account_id' => $charges->id])->assertSessionHas('error');
        $this->post(route('bank-feeds.lines.other-income', $other), ['income_account_id' => $charges->id])->assertSessionHas('error');
    }

    public function test_a_locked_period_refuses_posting_from_a_line_with_the_usual_message(): void
    {
        $this->link();
        $customer = $this->customer();
        $invoice = $this->invoice($customer, 50000);
        $account = ChartOfAccount::where('type', 'expense')->firstOrFail();
        app(UpdateLockDates::class)->handle($this->tenant, ['staff_lock_date' => '2026-10-05', 'all_users_lock_date' => null, 'reason' => null]);
        $locked = $this->line(['amount' => 30000, 'date' => '2026-10-03']);
        $lockedOut = $this->line(['direction' => 'debit', 'amount' => 900, 'date' => '2026-10-03']);

        $this->post(route('bank-feeds.lines.payment-received', $locked), ['customer_id' => $customer->id, 'invoice_id' => $invoice->id])
            ->assertSessionHasErrors();
        $this->post(route('bank-feeds.lines.expense', $lockedOut), ['name' => 'Fuel', 'expense_account_id' => $account->id])
            ->assertSessionHasErrors();
        $this->post(route('bank-feeds.lines.bank-charge', $lockedOut), ['expense_account_id' => $account->id])
            ->assertSessionHasErrors();

        $message = collect(session('errors')->all())->first();
        $this->assertStringContainsString('locked up to 5 Oct 2026', (string) $message);
        $this->assertSame('new', $locked->fresh()->status);
        $this->assertSame('new', $lockedOut->fresh()->status);
        $this->assertSame(0, PaymentReceived::count() + Expense::count() + Journal::where('reference_type', '!=', Invoice::class)->count());
        $this->assertEqualsWithDelta(0, (float) $this->bank->fresh()->current_balance, 0.001);
    }

    public function test_deleting_the_record_frees_the_line(): void
    {
        $this->link();
        $payment = $this->received($this->customer(), 25000, '2026-10-06');
        $line = $this->line(['amount' => 25000, 'date' => '2026-10-07']);
        app(LineActions::class)->accept($line, PaymentReceived::class, $payment->id, $this->user);
        $this->assertSame('matched', $line->fresh()->status);

        app(DeletePaymentReceived::class)->handle($payment);

        $line->refresh();
        $this->assertSame('new', $line->status);
        $this->assertNull($line->matched_id);
        $this->assertNull($line->bank_transaction_id);
        $this->assertSame(0, BankTransaction::count());
    }

    // ---- the lines screen --------------------------------------------------

    public function test_ignore_and_bring_back_and_bulk_ignore(): void
    {
        $this->link();
        $a = $this->line(['narration' => 'ALPHA']);
        $b = $this->line(['narration' => 'BETA']);
        $c = $this->line(['narration' => 'GAMMA']);

        Livewire::test(BankFeedLinesTable::class)
            ->assertSee('ALPHA')->assertSee('BETA')
            ->call('ignore', $a->id)
            ->assertDontSee('ALPHA')
            ->set('selectedItems', [(string) $b->id, (string) $c->id])
            ->call('ignoreSelected')
            ->assertSee('2 lines ignored')
            ->set('tab', 'ignored')
            ->assertSee('ALPHA')->assertSee('BETA')->assertSee('GAMMA')
            ->call('unignore', $a->id)
            ->assertDontSee('ALPHA');

        $this->assertSame('new', $a->fresh()->status);
        $this->assertSame('ignored', $b->fresh()->status);
        // no ledger effect
        $this->assertSame(0, Journal::count());
    }

    public function test_filters_and_search(): void
    {
        $this->link();
        $this->line(['narration' => 'RENT OCTOBER', 'direction' => 'debit', 'date' => '2026-09-01', 'amount' => 70000]);
        $this->line(['narration' => 'SALES DEPOSIT', 'direction' => 'credit', 'date' => '2026-10-05', 'amount' => 123456.78]);

        $this->travelTo('2026-10-10 09:00');
        Livewire::test(BankFeedLinesTable::class)
            ->set('search', 'rent')->assertSee('RENT OCTOBER')->assertDontSee('SALES DEPOSIT')
            ->set('search', '123456.78')->assertSee('SALES DEPOSIT')->assertDontSee('RENT OCTOBER')
            ->set('search', '')->set('direction', 'credit')->assertSee('SALES DEPOSIT')->assertDontSee('RENT OCTOBER')
            ->set('direction', '')->set('period', 'this_month')->assertSee('SALES DEPOSIT')->assertDontSee('RENT OCTOBER')
            ->set('period', 'last_month')->assertSee('RENT OCTOBER')->assertDontSee('SALES DEPOSIT')
            ->set('period', '')->set('connection', '999')->assertDontSee('RENT OCTOBER');
    }

    public function test_accept_all_only_takes_high_suggestions_after_the_click(): void
    {
        $this->link();
        $customer = $this->customer('Musa Ibrahim Stores');
        $good = $this->received($customer, 25000, '2026-10-07');
        $weak = $this->received($customer, 9000, '2026-10-01');
        $high = $this->line(['amount' => 25000, 'date' => '2026-10-07', 'narration' => 'NIP MUSA IBRAHIM STORES']);
        $medium = $this->line(['amount' => 9000, 'date' => '2026-10-05', 'narration' => 'MUSA SENDER']);

        Livewire::test(BankFeedLinesTable::class)
            ->assertSee('High confidence')->assertSee('Medium confidence')
            ->call('acceptAllSuggested')
            ->assertSee('1 line matched');

        $this->assertSame('matched', $high->fresh()->status);
        $this->assertSame($good->id, $high->fresh()->matched_id);
        $this->assertSame('new', $medium->fresh()->status);
        $this->assertNotNull($weak->id);
    }

    public function test_the_table_buttons_accept_and_refuse_a_suggestion(): void
    {
        $this->link();
        $p = $this->received($this->customer(), 25000, '2026-10-07');
        $wrong = $this->received($this->customer('Other Buyer'), 7000, '2026-10-07');
        $line = $this->line(['amount' => 25000, 'date' => '2026-10-07']);

        Livewire::test(BankFeedLinesTable::class)
            ->call('reject', $line->id, PaymentReceived::class, $p->id)
            ->assertSee('Nothing matches yet')
            ->call('accept', $line->id, PaymentReceived::class, $wrong->id)
            ->assertSee('That record no longer fits');
        $this->assertSame('new', $line->fresh()->status);
    }

    // ---- disconnect ---------------------------------------------------------

    public function test_disconnecting_unlinks_at_mono_and_keeps_the_lines(): void
    {
        $this->bankTx('acc_1', 'credit', 500, '2026-10-05', 'KEEP ME');
        $connection = $this->link();

        $this->delete(route('bank-feeds.disconnect', $connection))->assertRedirect(route('bank-feeds.index'));

        $connection->refresh();
        $this->assertSame('unlinked', $connection->status);
        $this->assertNotNull($connection->unlinked_at);
        $this->assertSame(1, $connection->lines()->count());
        $unlink = collect($this->monoCalls())->first(fn ($c) => str_ends_with($c[0]->url(), '/acc_1/unlink'));
        $this->assertNotNull($unlink);
        $this->assertSame('POST', $unlink[0]->method());

        // No more pulls, and the account can be linked again.
        $calls = count($this->monoCalls());
        $this->artisan('bankfeeds:sync')->assertSuccessful();
        $this->assertSame($calls, count($this->monoCalls()));
        $this->get(route('bank-feeds.lines'))->assertSee('KEEP ME');
        $this->assertSame(0, BankFeedConnection::counted()->count());
        $this->post(route('bank-feeds.start'), ['bank_id' => $this->bank->id])->assertRedirect(route('bank-feeds.go'))->assertSessionHas('bank_link_url', 'https://link.mono.test/ABC123');
    }

    // ---- tenants, permissions, plan, flag ---------------------------------------

    public function test_one_business_cannot_see_or_touch_anothers_feeds_and_lines(): void
    {
        $this->bankTx('acc_1', 'credit', 100, '2026-10-08', 'MINE');
        $this->link();
        $mine = BankFeedLine::firstOrFail();
        $other = $this->otherTenant();
        $otherUser = $this->createUserForTenant($other, self::PERMS);
        $otherBank = $this->makeBank($other, ['name' => 'Other bank']);
        $this->monoAccounts['acc_x'] = $this->account('acc_x');
        $theirs = $this->connectionRow($otherBank, $other, 'acc_x');
        $theirLine = $this->asNobody(fn () => BankFeedLine::withoutGlobalScopes()->create([
            'tenant_id' => $other->id, 'connection_id' => $theirs->id, 'bank_id' => $otherBank->id, 'provider_transaction_id' => 'T-OTHER',
            'date' => '2026-10-07', 'amount' => 777, 'direction' => 'credit', 'narration' => 'OTHER BUSINESS SECRET', 'status' => 'new',
        ]));

        $this->get(route('bank-feeds.index'))->assertOk()->assertDontSee('acc_x');
        $this->get(route('bank-feeds.lines'))->assertOk()->assertDontSee('OTHER BUSINESS SECRET');
        $this->get(route('bank-feeds.lines.show', $theirLine))->assertNotFound();
        $this->post(route('bank-feeds.sync', $theirs))->assertNotFound();
        $this->post(route('bank-feeds.reconnect', $theirs))->assertNotFound();
        $this->delete(route('bank-feeds.disconnect', $theirs))->assertNotFound();
        $this->post(route('bank-feeds.lines.transfer', $theirLine), ['other_bank_id' => $this->bank->id])->assertNotFound();
        $this->assertSame('linked', $theirs->fresh()->status);

        // Can't link a feed to another business's bank account.
        $this->post(route('bank-feeds.start'), ['bank_id' => $otherBank->id])->assertSessionHasErrors('bank_id');
        $this->assertSame(0, BankFeedConnection::where('bank_id', $otherBank->id)->where('status', 'pending')->count());

        // A link reference from another business is not honoured.
        $pending = $this->asNobody(fn () => BankFeedConnection::withoutGlobalScopes()->create(['tenant_id' => $other->id, 'bank_id' => $otherBank->id, 'provider' => 'mono', 'link_ref' => 'other-ref', 'status' => 'pending']));
        $this->get(route('bank-feeds.callback', ['ref' => 'other-ref', 'code' => 'code-1']))->assertNotFound();
        $this->assertSame('pending', $pending->fresh()->status);

        // Matching: a line of mine is never offered the other business's payment.
        $this->actingAs($otherUser);
        $customer = Customer::factory()->create(['tenant_id' => $other->id]);
        app(RecordPaymentReceived::class)->handle($other->id, ['customer_id' => $customer->id, 'payment_date' => '2026-10-07', 'amount' => 25000, 'payment_method' => 'bank_transfer', 'bank_id' => $otherBank->id, 'is_deposit' => true], $otherUser->id);
        $this->actingAs($this->user);
        $line = $this->line(['amount' => 25000]);
        $this->assertSame([], app(Matcher::class)->suggest([$line]));
        $this->assertNotNull($mine);

        // Accepting someone else's line through the actions is refused too.
        $this->expectExceptionMessage('That bank line was not found.');
        app(LineActions::class)->ignore($theirLine, $this->user);
    }

    public function test_the_webhook_only_touches_the_business_that_owns_the_account(): void
    {
        $mine = $this->link();
        $other = $this->otherTenant();
        $this->monoAccounts['acc_x'] = $this->account('acc_x');
        $theirs = $this->connectionRow(null, $other, 'acc_x');
        $this->bankTx('acc_x', 'credit', 50, '2026-10-08', 'THEIRS');
        $this->bankTx('acc_1', 'credit', 60, '2026-10-08', 'MINE');

        $this->hook(['event' => 'mono.events.account_updated', 'data' => ['account' => ['_id' => 'acc_x'], 'meta' => ['has_new_data' => true]]])->assertOk();

        $this->assertSame(1, $theirs->lines()->withoutGlobalScopes()->count());
        $this->assertSame(0, $mine->lines()->count());
        $this->assertSame($other->id, BankFeedLine::withoutGlobalScopes()->where('connection_id', $theirs->id)->value('tenant_id'));
    }

    public function test_the_same_bank_link_cannot_feed_two_accounts(): void
    {
        $this->link();
        $bank2 = $this->makeBank($this->tenant, ['name' => 'Another']);
        $this->post(route('bank-feeds.start'), ['bank_id' => $bank2->id]);
        $ref = BankFeedConnection::where('bank_id', $bank2->id)->firstOrFail()->link_ref;

        $this->get(route('bank-feeds.callback', ['ref' => $ref, 'code' => 'code-1']))
            ->assertSessionHas('error', 'That bank account is already linked.');
        $this->assertSame(1, BankFeedConnection::count());
    }

    public function test_permissions(): void
    {
        $this->link();
        $line = $this->line(['amount' => 100]);
        $viewer = $this->createUserForTenant($this->tenant, ['view banks']);
        $this->actingAs($viewer);

        $this->get(route('bank-feeds.index'))->assertOk()->assertDontSee('Sync now');
        $this->get(route('bank-feeds.lines'))->assertOk();
        $this->get(route('bank-feeds.connect'))->assertForbidden();
        $this->post(route('bank-feeds.start'), ['bank_id' => $this->bank->id])->assertForbidden();
        $this->post(route('bank-feeds.sync', BankFeedConnection::firstOrFail()))->assertForbidden();
        $this->delete(route('bank-feeds.disconnect', BankFeedConnection::firstOrFail()))->assertForbidden();
        $this->post(route('bank-feeds.lines.transfer', $line), ['other_bank_id' => 1])->assertForbidden();
        Livewire::test(BankFeedLinesTable::class)->call('ignore', $line->id)->assertForbidden();
        $this->assertSame('new', $line->fresh()->status);

        // Reconciling alone is not enough to create payments or expenses from a line.
        $clerk = $this->createUserForTenant($this->tenant, ['view banks', 'reconcile banks']);
        $this->actingAs($clerk);
        $this->post(route('bank-feeds.lines.payment-received', $line), ['customer_id' => 1])->assertForbidden();
        $this->post(route('bank-feeds.lines.expense', $line), ['name' => 'x', 'expense_account_id' => 1])->assertForbidden();
        $this->post(route('bank-feeds.lines.bank-charge', $line), ['expense_account_id' => 1])->assertForbidden();
        Livewire::test(BankFeedLinesTable::class)->call('ignore', $line->id)->assertOk();
        $this->assertSame('ignored', $line->fresh()->status);

        // Nothing at all without view banks.
        $nobody = $this->createUserForTenant($this->tenant, []);
        $this->actingAs($nobody);
        $this->get(route('bank-feeds.index'))->assertForbidden();
        $this->get(route('bank-feeds.lines'))->assertForbidden();
    }

    public function test_the_plan_limits_linked_accounts(): void
    {
        $this->plan->update(['bank_feed_accounts_limit' => 1]);
        $this->link();
        $bank2 = $this->makeBank($this->tenant, ['name' => 'Second']);

        $this->get(route('bank-feeds.index'))->assertSee('1 of 1 bank account linked on your plan');
        $this->post(route('bank-feeds.start'), ['bank_id' => $bank2->id])
            ->assertSessionHas('error', 'Your plan includes 1 linked bank account. Disconnect one or upgrade your plan to link another.');
        $this->assertSame(0, BankFeedConnection::where('status', 'pending')->count());

        // Disconnecting frees the place; no limit when the column is empty.
        $this->delete(route('bank-feeds.disconnect', BankFeedConnection::firstOrFail()));
        $this->post(route('bank-feeds.start'), ['bank_id' => $bank2->id])->assertRedirect(route('bank-feeds.go'))->assertSessionHas('bank_link_url', 'https://link.mono.test/ABC123');
        $this->plan->update(['bank_feed_accounts_limit' => null]);
        $this->actingAs($this->user->fresh());
        $this->get(route('bank-feeds.index'))->assertSee('0 bank accounts linked');
    }

    public function test_the_seeded_plans_have_sensible_limits(): void
    {
        $this->seed(PlanSeeder::class);

        $this->assertSame([1, 3, 10], Plan::whereIn('slug', ['starter', 'professional', 'enterprise'])->orderBy('sort_order')->pluck('bank_feed_accounts_limit')->all());
    }

    public function test_the_flag_hides_everything(): void
    {
        $this->link();
        config(['mybooks.features.bank_feeds' => false]);

        $this->get(route('bank-feeds.index'))->assertNotFound();
        $this->get(route('bank-feeds.lines'))->assertNotFound();
        $this->get(route('bank-feeds.connect'))->assertNotFound();
        $this->post(route('bank-feeds.start'), ['bank_id' => $this->bank->id])->assertNotFound();
        $this->get(route('banks.index'))->assertOk()->assertDontSee('Bank feeds');
        $this->get(route('banks.show', $this->bank))->assertOk()->assertDontSee('Connect bank feed');
        $calls = count($this->monoCalls());
        $this->artisan('bankfeeds:sync')->assertSuccessful();
        $this->assertSame($calls, count($this->monoCalls()));
    }

    public function test_without_keys_the_screens_say_not_set_up_and_nothing_is_called(): void
    {
        config(['services.mono.secret_key' => null]);
        $this->assertFalse(app(BankFeedProviders::class)->isLive());
        $this->assertSame('log', app(BankFeedProviders::class)->driverName());

        $this->get(route('bank-feeds.index'))->assertOk()->assertSee('Not set up yet')->assertSee('add Mono keys')->assertDontSee('Connect a bank account');
        $this->get(route('bank-feeds.connect'))->assertOk()->assertSee('Not set up yet');
        $this->get(route('bank-feeds.lines'))->assertOk()->assertSee('Not set up yet');
        $this->post(route('bank-feeds.start'), ['bank_id' => $this->bank->id])
            ->assertSessionHas('error', 'Bank feeds are not set up yet. The MyBooks team needs to add the Mono keys.');
        $this->assertSame(0, BankFeedConnection::count());
        $this->hook(['event' => 'mono.events.account_updated'])->assertStatus(401);

        // An old connection is not read either.
        $this->connectionRow();
        $this->artisan('bankfeeds:sync')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_the_banks_pages_show_the_feed_counter_and_connect_button(): void
    {
        $this->get(route('banks.show', $this->bank))->assertOk()->assertSee('Connect bank feed');
        $this->get(route('banks.index'))->assertOk()->assertSee('none connected yet');

        $connection = $this->link();
        for ($i = 0; $i < 12; $i++) {
            $this->line(['narration' => "N{$i}"], $connection);
        }

        $this->get(route('banks.index'))->assertOk()->assertSee('12 lines to review');
        $this->get(route('banks.show', $this->bank))->assertOk()->assertSee('Bank feed: Connected')->assertDontSee('Connect bank feed');
    }

    public function test_the_reconciliation_screen_uses_the_feed_as_the_statement(): void
    {
        $connection = $this->link();
        $connection->update(['provider_balance' => 7500000]); // 75,000.00
        $this->bank->update(['current_balance' => 70000]);
        $this->line(['narration' => 'UNSEEN DEPOSIT', 'amount' => 5000], $connection);
        $this->received($this->customer(), 12345.67, '2026-10-06', null, 'NOT-AT-BANK');

        $this->get(route('banks.reconcile', $this->bank))->assertOk()
            ->assertSee('Balance at the bank')->assertSee('75,000.00')
            ->assertSee('UNSEEN DEPOSIT')->assertSee('12,345.67');
    }

    public function test_the_sync_is_scheduled_and_secrets_never_reach_the_log(): void
    {
        $this->assertTrue(collect(app(Schedule::class)->events())->contains(fn ($e) => str_contains((string) $e->command, 'bankfeeds:sync')));

        $messages = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$messages) {
            $messages[] = $e->message.' '.json_encode($e->context);
        });

        $this->bankTx('acc_1', 'credit', 500, '2026-10-05', 'NIP');
        $connection = $this->link();
        $this->failing['acc_1'] = 400;
        app(BankFeedSync::class)->pull($connection);
        $this->hook([], 'wrong-guess');
        $this->hook(['event' => 'mono.events.account_updated', 'data' => ['account' => ['_id' => 'acc_1']]]);
        $this->delete(route('bank-feeds.disconnect', $connection->fresh()));
        config(['services.mono.secret_key' => null]);
        $this->artisan('bankfeeds:sync');

        $this->assertNotEmpty($messages);
        foreach ($messages as $m) {
            $this->assertStringNotContainsString(self::SECRET, $m);
            $this->assertStringNotContainsString(self::HOOK, $m);
            $this->assertStringNotContainsString(self::FULL_NUMBER, $m);
        }
        $this->assertStringNotContainsString(self::SECRET, json_encode(BankFeedConnection::first()->toArray()));
    }

    public function test_the_linker_can_be_used_without_a_web_request_for_the_webhook_path(): void
    {
        // complete() with only an account id (as the webhook does) and no signed-in user.
        $this->post(route('bank-feeds.start'), ['new_bank_name' => 'Fresh account']);
        $ref = BankFeedConnection::firstOrFail()->link_ref;
        $this->bankTx('acc_1', 'credit', 99, '2026-10-08', 'X');
        auth()->logout();

        $done = app(BankFeedLinker::class)->complete($ref, null, 'acc_1');

        $this->assertSame('linked', $done->status);
        $this->assertSame(1, $done->lines()->count());
        $this->assertSame($this->tenant->id, Bank::withoutGlobalScopes()->where('name', 'Fresh account')->value('tenant_id'));
        $this->assertInstanceOf(User::class, $this->user);
    }
}
