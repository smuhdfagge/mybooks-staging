<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Notifications\InvoiceOverdueNotification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationDeduplicationTest extends TestCase
{
    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser();
        $this->service = app(NotificationService::class);
    }

    public function test_overdue_reminder_sends_on_first_call(): void
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => 'unpaid',
            'due_date' => now()->subDays(5),
            'balance_due' => 500,
        ]);

        Notification::fake();

        $count = $this->service->sendOverdueReminders($this->tenant->id);

        $this->assertEquals(1, $count);
        Notification::assertSentTo($customer, InvoiceOverdueNotification::class);
    }

    public function test_overdue_reminder_skips_duplicate_within_cooldown(): void
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => 'unpaid',
            'due_date' => now()->subDays(5),
            'balance_due' => 500,
        ]);

        // First call — sends the notification (real, not faked, so it stores in DB)
        $firstCount = $this->service->sendOverdueReminders($this->tenant->id);
        $this->assertEquals(1, $firstCount);

        // Second call — should skip because cooldown hasn't expired
        $secondCount = $this->service->sendOverdueReminders($this->tenant->id);
        $this->assertEquals(0, $secondCount);
    }

    public function test_overdue_reminder_resends_after_cooldown_expires(): void
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => 'unpaid',
            'due_date' => now()->subDays(5),
            'balance_due' => 500,
        ]);

        // First call
        $this->service->sendOverdueReminders($this->tenant->id);

        // Manually back-date the stored notification beyond cooldown
        $customer->notifications()->update(['created_at' => now()->subHours(25)]);

        // Second call — should send again because cooldown has passed
        $secondCount = $this->service->sendOverdueReminders($this->tenant->id);
        $this->assertEquals(1, $secondCount);
    }

    public function test_overdue_reminder_skips_customer_without_email(): void
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => null,
        ]);

        Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => 'unpaid',
            'due_date' => now()->subDays(5),
            'balance_due' => 500,
        ]);

        Notification::fake();

        $count = $this->service->sendOverdueReminders($this->tenant->id);

        $this->assertEquals(0, $count);
        Notification::assertNothingSent();
    }
}
