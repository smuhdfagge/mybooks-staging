<?php

namespace Tests\Feature\Notifications;

use App\Livewire\Notifications\NotificationBell;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The header bell.
 *
 * It used to be a button that did nothing, under a red dot that was
 * always lit, while fifteen notification types quietly wrote rows nobody
 * ever read. These tests are mostly about the dot meaning something: on
 * when there is something unread, off when there is not.
 */
class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    private function notify(User $user, array $data, bool $read = false): string
    {
        $id = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'App\\Notifications\\InvoiceOverdueNotification',
            'notifiable_type' => $user::class,
            'notifiable_id' => $user->id,
            'data' => json_encode($data),
            'read_at' => $read ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    public function test_the_dot_is_dark_when_there_is_nothing_unread(): void
    {
        $user = $this->createAuthenticatedUser();
        $this->notify($user, ['type' => 'invoice_sent', 'message' => 'Already seen'], read: true);

        // A dot that is always lit tells you nothing, which is what the old
        // hard-coded one did.
        Livewire::test(NotificationBell::class)
            ->assertViewHas('count', 0)
            ->assertDontSee('bg-red-500', false);
    }

    public function test_it_counts_what_is_unread(): void
    {
        $user = $this->createAuthenticatedUser();
        $this->notify($user, ['type' => 'invoice_overdue', 'message' => 'Invoice #INV-0001 is overdue']);
        $this->notify($user, ['type' => 'bill_due', 'message' => 'Bill #BILL-0002 is due']);
        $this->notify($user, ['type' => 'invoice_sent', 'message' => 'Old news'], read: true);

        Livewire::test(NotificationBell::class)->assertViewHas('count', 2);
    }

    public function test_opening_the_panel_lists_them_without_marking_them_read(): void
    {
        $user = $this->createAuthenticatedUser();
        $this->notify($user, ['type' => 'invoice_overdue', 'message' => 'Invoice #INV-0001 is overdue']);

        // Glancing at the list is not reading them.
        Livewire::test(NotificationBell::class)
            ->call('toggle')
            ->assertSee('Invoice #INV-0001 is overdue')
            ->assertViewHas('count', 1);
    }

    public function test_opening_one_marks_it_read_and_goes_to_the_record(): void
    {
        $user = $this->createAuthenticatedUser(['view invoices']);
        // The id is all the link needs; this is about where the bell sends
        // you, not about invoices.
        $id = $this->notify($user, [
            'type' => 'invoice_overdue',
            'invoice_id' => 4321,
            'message' => 'Invoice is overdue',
        ]);

        Livewire::test(NotificationBell::class)
            ->call('openNotification', $id)
            ->assertRedirect(route('invoices.show', 4321));

        $this->assertNotNull(DB::table('notifications')->where('id', $id)->value('read_at'));
    }

    public function test_it_does_not_offer_a_link_the_user_may_not_follow(): void
    {
        // Without 'view invoices' that link lands on 403, which is a worse
        // answer than no link.
        $user = $this->createAuthenticatedUser();
        $id = $this->notify($user, [
            'type' => 'invoice_overdue',
            'invoice_id' => 4321,
            'message' => 'Invoice is overdue',
        ]);

        Livewire::test(NotificationBell::class)
            ->call('openNotification', $id)
            ->assertNoRedirect();

        $this->assertNotNull(DB::table('notifications')->where('id', $id)->value('read_at'));
    }

    public function test_mark_all_as_read_clears_the_count(): void
    {
        $user = $this->createAuthenticatedUser();
        $this->notify($user, ['type' => 'invoice_overdue', 'message' => 'One']);
        $this->notify($user, ['type' => 'bill_due', 'message' => 'Two']);

        Livewire::test(NotificationBell::class)
            ->call('markAllRead')
            ->assertViewHas('count', 0);
    }

    public function test_one_persons_notifications_stay_theirs(): void
    {
        $user = $this->createAuthenticatedUser();
        $other = User::factory()->create(['tenant_id' => $user->tenant_id]);
        $this->notify($other, ['type' => 'invoice_overdue', 'message' => 'Not for you']);

        Livewire::test(NotificationBell::class)
            ->assertViewHas('count', 0)
            ->call('toggle')
            ->assertDontSee('Not for you');
    }

    public function test_the_header_carries_the_bell(): void
    {
        $this->createAuthenticatedUser(['view dashboard']);

        $this->withoutVite()->get(route('dashboard'))->assertOk()
            ->assertSeeLivewire(NotificationBell::class);
    }
}
