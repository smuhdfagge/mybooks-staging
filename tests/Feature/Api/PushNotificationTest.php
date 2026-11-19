<?php

namespace Tests\Feature\Api;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\Vendor;
use App\Services\Push\PushMessage;
use App\Services\Push\PushSender;
use Carbon\Carbon;
use Tests\Support\FakePushSender;
use Tests\TestCase;

/**
 * Mobile plan A2: who gets which push notification, with the Firebase
 * sender faked.
 */
class PushNotificationTest extends TestCase
{
    private FakePushSender $push;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 09:00:00');
        $this->push = new FakePushSender;
        $this->app->instance(PushSender::class, $this->push);
        $this->createAuthenticatedUser(['view expenses', 'create expenses', 'edit expenses', 'view invoices', 'view bills']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function phone(User $user, string $token): void
    {
        UserDevice::create(['user_id' => $user->id, 'token_hash' => UserDevice::hashToken($token), 'token' => $token, 'platform' => 'android']);
    }

    /** @param array<int, string> $permissions */
    private function colleague(array $permissions = [], bool $admin = false, ?string $phone = null, bool $active = true): User
    {
        $user = $this->createUserForTenant($this->tenant, $permissions, ['is_active' => $active]);
        if ($admin) {
            $user->assignRole('admin');
        }
        if ($phone) {
            $this->phone($user, $phone);
        }

        return $user;
    }

    public function test_an_expense_sent_for_approval_goes_to_admins_who_could_approve_it(): void
    {
        $this->user->assignRole('admin');
        $this->phone($this->user, 'submitter-phone'); // raised it, so can't approve it
        $this->colleague([], admin: true, phone: 'admin-phone');
        $this->colleague([], admin: true); // no phone
        $this->colleague(['view expenses'], phone: 'staff-phone'); // not an admin
        $this->colleague([], admin: true, phone: 'inactive-phone', active: false);

        $expense = Expense::factory()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->user->id, 'name' => 'Diesel', 'amount' => 48000, 'total' => 48000]);
        $this->postJson("/api/v1/expenses/{$expense->id}/submit")->assertOk();

        $this->assertSame(['admin-phone'], $this->push->tokensFor(PushMessage::EXPENSE_AWAITING_APPROVAL));
        $message = $this->push->sent[0]['message'];
        $this->assertSame(['type' => 'expense_awaiting_approval', 'id' => (string) $expense->id], $message->data());
        $this->assertStringContainsString('Diesel', $message->body);
        $this->assertStringContainsString('48,000.00', $message->body);

        // Other changes to the expense send nothing more.
        $expense->update(['notes' => 'Receipt to follow']);
        $this->assertCount(1, $this->push->sent);
    }

    public function test_an_invoice_paid_in_full_goes_to_people_who_see_invoices(): void
    {
        $this->phone($this->user, 'my-phone');
        $this->colleague(['view invoices'], phone: 'sales-phone');
        $this->colleague(['view bills'], phone: 'purchases-phone');

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Chukwu Stores']);
        $invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'status' => 'unpaid']);

        $invoice->update(['status' => 'partial']);
        $this->assertSame([], $this->push->sent);

        $invoice->update(['status' => 'paid', 'amount_paid' => $invoice->total, 'balance_due' => 0]);
        $tokens = $this->push->tokensFor(PushMessage::INVOICE_PAID);
        sort($tokens);
        $this->assertSame(['my-phone', 'sales-phone'], $tokens);
        $this->assertStringContainsString('Chukwu Stores', $this->push->sent[0]['message']->body);
    }

    public function test_the_daily_reminders_push_invoices_overdue_today_and_bills_due_tomorrow(): void
    {
        $this->phone($this->user, 'my-phone');
        $this->colleague(['view bills'], phone: 'bills-only');
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);

        $newlyOverdue = Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'status' => 'unpaid', 'due_date' => '2026-11-19']);
        Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'status' => 'unpaid', 'due_date' => '2026-11-15']); // already reminded
        Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'status' => 'paid', 'due_date' => '2026-11-19', 'balance_due' => 0]);
        $dueTomorrow = Bill::factory()->create(['tenant_id' => $this->tenant->id, 'vendor_id' => $vendor->id, 'status' => 'unpaid', 'due_date' => '2026-11-21']);
        Bill::factory()->create(['tenant_id' => $this->tenant->id, 'vendor_id' => $vendor->id, 'status' => 'unpaid', 'due_date' => '2026-11-22']);
        Bill::factory()->create(['tenant_id' => $this->tenant->id, 'vendor_id' => $vendor->id, 'status' => 'paid', 'due_date' => '2026-11-21', 'balance_due' => 0]);

        $this->artisan('notifications:send-push-reminders')->assertSuccessful()
            ->expectsOutput('Pushed 1 overdue invoices and 1 bills due tomorrow.');

        $this->assertSame(['my-phone'], $this->push->tokensFor(PushMessage::INVOICE_OVERDUE));
        $bills = $this->push->tokensFor(PushMessage::BILL_DUE);
        sort($bills);
        $this->assertSame(['bills-only', 'my-phone'], $bills);
        $idsOf = fn (string $type) => array_values(array_unique(array_map(fn ($s) => $s['message']->id,
            array_filter($this->push->sent, fn ($s) => $s['message']->type === $type))));
        $this->assertSame([$newlyOverdue->id], $idsOf(PushMessage::INVOICE_OVERDUE));
        $this->assertSame([$dueTomorrow->id], $idsOf(PushMessage::BILL_DUE));
    }

    public function test_nothing_crosses_to_another_business(): void
    {
        $this->phone($this->user, 'my-phone');
        auth()->forgetGuards();
        [$other] = $this->createTenantWithSubscription();
        $this->actingAs($this->user);
        Tenant::run($other, function () use ($other) {
            $customer = Customer::factory()->create(['tenant_id' => $other->id]);
            Invoice::factory()->create(['tenant_id' => $other->id, 'customer_id' => $customer->id, 'status' => 'unpaid', 'due_date' => '2026-11-19'])
                ->update(['status' => 'paid', 'balance_due' => 0]);
        });

        $this->artisan('notifications:send-push-reminders', ['--tenant' => $other->id])->assertSuccessful();
        $this->assertSame([], $this->push->sent);
    }

    public function test_a_phone_firebase_says_is_gone_is_forgotten(): void
    {
        $this->phone($this->user, 'old-phone');
        $this->phone($this->user, 'new-phone');
        $this->push->deadTokens = ['old-phone'];

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'status' => 'unpaid'])
            ->update(['status' => 'paid', 'balance_due' => 0]);

        $this->assertSame(['new-phone'], $this->push->tokensFor(PushMessage::INVOICE_PAID));
        $this->assertSame(['new-phone'], UserDevice::pluck('token')->all());
        $this->assertNotNull(UserDevice::first()->last_sent_at);
    }

    public function test_a_failing_sender_does_not_stop_the_change(): void
    {
        $this->phone($this->user, 'my-phone');
        $this->app->instance(PushSender::class, new class implements PushSender
        {
            public function send(string $token, PushMessage $message): string
            {
                throw new \RuntimeException('Firebase is down');
            }
        });

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'status' => 'unpaid']);
        $invoice->update(['status' => 'paid', 'balance_due' => 0]);

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(1, UserDevice::count());
    }
}
