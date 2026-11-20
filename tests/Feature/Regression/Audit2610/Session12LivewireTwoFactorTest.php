<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Livewire\Expenses\ExpensesTable;
use App\Models\Expense;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * October 2026 audit, fix session 12:
 * - MB-10: Livewire actions ran in sessions that hadn't finished two-factor
 *   sign-in (probe S08: a remember-me session deleted an expense);
 * - MB-11: an admin could approve their own expense from the list.
 */
class Session12LivewireTwoFactorTest extends TestCase
{
    /** A real ExpensesTable snapshot from a page rendered in a fully signed-in session. */
    private function snapshot(): string
    {
        $html = $this->withSession(['two_factor_verified' => true])->get(route('expenses.index'))->assertOk()->getContent();
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $m);
        $snapshot = collect($m[1])->map(fn ($s) => html_entity_decode($s, ENT_QUOTES))
            ->first(fn ($s) => str_contains($s, 'expenses.expenses-table') || str_contains($s, 'ExpensesTable'));
        $this->assertNotNull($snapshot, 'expenses table snapshot not found');

        return $snapshot;
    }

    private function deleteOne(string $snapshot, int $id)
    {
        $uri = collect(app('router')->getRoutes()->getRoutes())->first(fn ($r) => $r->getName() === 'default-livewire.update')->uri();

        return $this->withHeaders(['X-Livewire' => '1'])->postJson('/'.$uri, [
            'components' => [['snapshot' => $snapshot, 'updates' => new \stdClass, 'calls' => [['method' => 'deleteOne', 'params' => [$id], 'metadata' => new \stdClass]]]],
        ]);
    }

    private function adminRole(): void
    {
        Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => null]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_a_livewire_action_needs_two_factor_sign_in_finished(): void
    {
        $this->createAuthenticatedUser(['view expenses', 'delete expenses'], ['two_factor_confirmed_at' => now()]);
        $this->subscription->update(['ends_at' => '2030-12-31']);
        $first = Expense::factory()->create(['tenant_id' => $this->tenant->id, 'status' => Expense::STATUS_DRAFT]);
        $second = Expense::factory()->create(['tenant_id' => $this->tenant->id, 'status' => Expense::STATUS_DRAFT]);
        $snapshot = $this->snapshot();

        // A new session for the same user (a remember-me cookie) without 2FA: refused and signed out.
        $this->flushSession();
        $response = $this->deleteOne($snapshot, $second->id);
        $this->assertNotNull(Expense::withoutGlobalScope('tenant')->find($second->id), 'deleted without two-factor sign-in');
        $this->assertTrue($response->isRedirect(route('two-factor.challenge')) || $response->status() >= 400, 'status '.$response->status());
        $this->assertGuest();

        // Control: the same request once two-factor sign-in is done works.
        $this->actingAs($this->user)->withSession(['two_factor_verified' => true]);
        $this->deleteOne($snapshot, $first->id)->assertOk();
        $this->assertNull(Expense::withoutGlobalScope('tenant')->find($first->id));
    }

    public function test_a_livewire_action_needs_a_verified_email(): void
    {
        $this->createAuthenticatedUser(['view expenses', 'delete expenses']);
        $this->subscription->update(['ends_at' => '2030-12-31']);
        $expense = Expense::factory()->create(['tenant_id' => $this->tenant->id, 'status' => Expense::STATUS_DRAFT]);
        $snapshot = $this->snapshot();

        $this->user->forceFill(['email_verified_at' => null])->save();
        $this->deleteOne($snapshot, $expense->id);

        $this->assertNotNull(Expense::withoutGlobalScope('tenant')->find($expense->id));
    }

    public function test_an_admin_cant_approve_their_own_expense_from_the_list(): void
    {
        $this->createAuthenticatedUser(['view expenses']);
        $this->subscription->update(['ends_at' => '2030-12-31']);
        $this->adminRole();
        $this->user->assignRole('admin');
        $own = Expense::factory()->create(['tenant_id' => $this->tenant->id, 'status' => Expense::STATUS_PENDING_APPROVAL, 'created_by' => $this->user->id]);

        Livewire::test(ExpensesTable::class)->call('approveExpense', $own->id)
            ->assertSet('errorMessage', fn ($m) => str_contains($m, 'Another admin must approve it'));
        $this->assertSame(Expense::STATUS_PENDING_APPROVAL, $own->fresh()->status);

        // Another admin can.
        $other = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $other->assignRole('admin');
        $this->actingAs($other);
        Livewire::test(ExpensesTable::class)->call('approveExpense', $own->id)->assertSet('errorMessage', '');
        $this->assertSame(Expense::STATUS_APPROVED, $own->fresh()->status);
    }

    public function test_bulk_approve_skips_the_admins_own_expenses(): void
    {
        $this->createAuthenticatedUser(['view expenses']);
        $this->subscription->update(['ends_at' => '2030-12-31']);
        $this->adminRole();
        $this->user->assignRole('admin');
        $clerk = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $own = Expense::factory()->create(['tenant_id' => $this->tenant->id, 'status' => Expense::STATUS_PENDING_APPROVAL, 'created_by' => $this->user->id]);
        $theirs = Expense::factory()->create(['tenant_id' => $this->tenant->id, 'status' => Expense::STATUS_PENDING_APPROVAL, 'created_by' => $clerk->id]);

        Livewire::test(ExpensesTable::class)
            ->set('selectedItems', [(string) $own->id, (string) $theirs->id])
            ->set('bulkAction', 'approve')
            ->call('applyBulkAction')
            ->assertSet('successMessage', 'Approved 1 expense(s). Skipped 1 you recorded yourself: another admin must approve those.');

        $this->assertSame(Expense::STATUS_PENDING_APPROVAL, $own->fresh()->status);
        $this->assertSame(Expense::STATUS_APPROVED, $theirs->fresh()->status);
    }
}
