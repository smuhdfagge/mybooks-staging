<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * October 2026 audit, fix session 4 (MB-05): "edit users" could reset an
 * admin's email and password or hand out the admin role, and "edit roles"
 * could add any permission to the editor's own role. Nobody may now give,
 * or change for someone else, more access than they hold; another user's
 * sign-in details are an admin's to change, after confirming a password.
 */
class Session4RoleEscalationTest extends TestCase
{
    private const NEW_PASSWORD = 'An0ther-Passw0rd!';

    private const MANAGER = ['view users', 'create users', 'edit users', 'delete users', 'view roles', 'create roles', 'edit roles', 'delete roles', 'view invoices'];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('delete invoices', 'web');

        // A manager with a custom role, and the business's admin.
        $this->createAuthenticatedUser();
        $managerRole = Role::create(['name' => 'Office manager', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        foreach (self::MANAGER as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $managerRole->givePermissionTo(self::MANAGER);
        $this->user->assignRole($managerRole);

        // The admin role holds every permission, as seeded.
        $this->admin = $this->createUserForTenant($this->tenant);
        $this->adminRole()->givePermissionTo([...self::MANAGER, 'delete invoices']);
        $this->admin->assignRole($this->adminRole());
    }

    private function adminRole(): Role
    {
        return Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => null]);
    }

    private function clerk(): User
    {
        $clerk = $this->createUserForTenant($this->tenant);
        $role = Role::create(['name' => 'Clerk', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $role->givePermissionTo('view invoices');
        $clerk->assignRole($role);

        return $clerk;
    }

    /** @return array<string, mixed> */
    private function form(User $user, array $extra = []): array
    {
        return $extra + ['name' => $user->name, 'email' => $user->email, 'is_active' => 1, 'roles' => $user->roles->pluck('id')->all()];
    }

    public function test_a_non_admin_cannot_take_over_an_admin(): void
    {
        $email = $this->admin->email;

        $this->get(route('settings.users.edit', $this->admin))->assertForbidden();
        $this->put(route('settings.users.update', $this->admin), $this->form($this->admin, ['email' => 'mine@evil.test']))->assertForbidden();
        $this->put(route('settings.users.update', $this->admin), $this->form($this->admin, [
            'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ]))->assertForbidden();
        $this->delete(route('settings.users.destroy', $this->admin))->assertForbidden();

        $this->admin->refresh();
        $this->assertSame($email, $this->admin->email);
        $this->assertTrue(Hash::check('password', $this->admin->password));
        $this->get(route('settings.users'))->assertOk()->assertDontSee(route('settings.users.edit', $this->admin), false);
    }

    public function test_a_non_admin_cannot_give_the_admin_role_or_more_than_they_hold(): void
    {
        $clerk = $this->clerk();
        $bigger = Role::create(['name' => 'Controller', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $bigger->givePermissionTo('delete invoices');

        $this->get(route('settings.users.edit', $clerk))->assertOk()->assertDontSee('Controller')->assertDontSee('value="'.$this->adminRole()->id.'"', false);
        $this->put(route('settings.users.update', $clerk), $this->form($clerk, ['roles' => [$this->adminRole()->id]]))->assertSessionHasErrors('roles.0');
        $this->put(route('settings.users.update', $clerk), $this->form($clerk, ['roles' => [$bigger->id]]))->assertSessionHasErrors('roles.0');
        $this->post(route('settings.users.store'), [
            'name' => 'New', 'email' => 'new@example.test', 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
            'roles' => [$this->adminRole()->id],
        ])->assertSessionHasErrors('roles.0');

        // Nor make themselves admin.
        $this->put(route('settings.users.update', $this->user), $this->form($this->user, ['roles' => [$this->adminRole()->id]]))->assertSessionHasErrors('roles.0');
        $this->assertFalse($this->user->fresh()->hasRole('admin'));
        $this->assertFalse($clerk->fresh()->hasRole('admin'));
    }

    public function test_a_non_admin_can_still_manage_a_smaller_user_but_not_their_sign_in(): void
    {
        $clerk = $this->clerk();

        $this->get(route('settings.users.edit', $clerk))->assertOk()->assertDontSee('name="password"', false);
        $this->put(route('settings.users.update', $clerk), $this->form($clerk, ['name' => 'Renamed']))->assertRedirect(route('settings.users'));
        $this->assertSame('Renamed', $clerk->fresh()->name);

        $this->put(route('settings.users.update', $clerk), $this->form($clerk, ['email' => 'other@example.test']))->assertSessionHasErrors('email');
        $this->put(route('settings.users.update', $clerk), $this->form($clerk, [
            'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ]))->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password', $clerk->fresh()->password));
    }

    public function test_a_non_admin_cannot_add_permissions_they_do_not_hold(): void
    {
        $own = $this->user->roles->first();

        // To their own role: not theirs to change at all.
        $this->get(route('settings.roles.edit', $own))->assertForbidden();
        $this->put(route('settings.roles.update', $own), ['name' => $own->name, 'permissions' => [...self::MANAGER, 'delete invoices']])->assertForbidden();
        $this->assertFalse($own->fresh()->hasPermissionTo('delete invoices'));

        // To a new role, or another role.
        $this->post(route('settings.roles.store'), ['name' => 'Sneaky', 'permissions' => ['view invoices', 'delete invoices']])
            ->assertSessionHasErrors('permissions');
        $clerkRole = $this->clerk()->roles->first();
        $this->put(route('settings.roles.update', $clerkRole), ['name' => 'Clerk', 'permissions' => ['view invoices', 'delete invoices']])
            ->assertSessionHasErrors('permissions');
        $this->assertFalse($clerkRole->fresh()->hasPermissionTo('delete invoices'));

        // Within their own access it still works, and is logged.
        $this->post(route('settings.roles.store'), ['name' => 'Reader', 'permissions' => ['view invoices']])->assertSessionHasNoErrors();
        $this->get(route('settings.roles.create'))->assertOk()->assertDontSee('delete invoices');
        $this->assertTrue(ActivityLog::where('action', ActivityLog::ACTION_PERMISSION_CHANGED)->where('model_name', 'Reader')->exists());
    }

    public function test_an_admin_changes_sign_in_details_only_after_confirming_their_password(): void
    {
        $clerk = $this->clerk();
        $this->actingAs($this->admin);
        $change = $this->form($clerk, ['email' => 'clerk@new.test', 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD]);

        $this->put(route('settings.users.update', $clerk), $change)->assertRedirect(route('password.confirm'));
        $this->assertNotSame('clerk@new.test', $clerk->fresh()->email);

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('settings.users.update', $clerk), $change)->assertRedirect(route('settings.users'));
        $clerk->refresh();
        $this->assertSame('clerk@new.test', $clerk->email);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $clerk->password));
        $this->assertTrue(ActivityLog::where('action', ActivityLog::ACTION_PASSWORD_CHANGED)->where('model_id', $clerk->id)->where('user_id', $this->admin->id)->exists());
    }

    public function test_an_admin_can_still_give_any_role_and_permission_and_it_is_logged(): void
    {
        $clerk = $this->clerk();
        $this->actingAs($this->admin);

        $this->put(route('settings.users.update', $clerk), $this->form($clerk, ['roles' => [$this->adminRole()->id]]))->assertRedirect(route('settings.users'));
        $this->assertTrue($clerk->fresh()->hasRole('admin'));
        $this->assertTrue(ActivityLog::where('action', ActivityLog::ACTION_ROLE_CHANGED)->where('model_id', $clerk->id)->where('description', 'like', "%'admin' assigned%")->exists());
        $this->assertTrue(ActivityLog::where('action', ActivityLog::ACTION_ROLE_CHANGED)->where('model_id', $clerk->id)->where('description', 'like', "%'Clerk' removed%")->exists());

        $own = $this->user->roles->first();
        $this->put(route('settings.roles.update', $own), ['name' => $own->name, 'permissions' => [...self::MANAGER, 'delete invoices']])->assertSessionHasNoErrors();
        $this->assertTrue($own->fresh()->hasPermissionTo('delete invoices'));
        $log = ActivityLog::where('action', ActivityLog::ACTION_PERMISSION_CHANGED)->where('model_id', $own->id)->latest('id')->firstOrFail();
        $this->assertSame(['added' => ['delete invoices']], $log->new_values);
    }

    public function test_the_last_admin_is_still_protected(): void
    {
        $this->actingAs($this->admin);
        $this->delete(route('settings.users.destroy', $this->admin))->assertSessionHas('error');
        $this->assertNotNull($this->admin->fresh());
    }
}
