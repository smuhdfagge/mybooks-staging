<?php

namespace App\Http\Controllers;

use App\Actions\Auth\SignOutOtherSessions;
use App\Models\ActivityLog;
use App\Models\Country;
use App\Models\NotificationSetting;
use App\Models\Role;
use App\Models\State;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\EmailAddressChangedNotification;
use App\Notifications\TestEmailNotification;
use App\Rules\Tin;
use App\Services\ActivityLogService;
use App\Support\Privileges;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Exceptions\RoleAlreadyExists;
use Spatie\Permission\Models\Permission;

class SettingsController extends Controller
{
    /**
     * Role names are unique within an organisation (H4). This only shows if
     * two people create the same name at the same moment.
     */
    private const ROLE_NAME_TAKEN = 'That role name is already in use. Please choose a different name.';

    /** Validation: not a reserved system role name. */
    private function notReservedRoleName(): \Closure
    {
        return function ($attribute, $value, $fail) {
            if (in_array(mb_strtolower(trim((string) $value)), Role::RESERVED_NAMES, true)) {
                $fail('That name is reserved for a system role. Please choose a different name.');
            }
        };
    }

    /**
     * /settings has no page of its own; company settings is the first one.
     */
    public function index()
    {
        return redirect()->route('settings.company');
    }

    public function company()
    {
        $tenant = auth()->user()->tenant;
        $countries = Country::orderBy('name')->get();
        $states = State::orderBy('name')->get();

        return view('settings.company', compact('tenant', 'countries', 'states'));
    }

    public function updateCompany(Request $request)
    {
        // The website is optional, and "www.example.com" is enough: add the
        // https:// people usually leave out.
        $website = trim((string) $request->input('website'));
        if ($website !== '' && ! preg_match('#^https?://#i', $website)) {
            $website = 'https://'.$website;
        }
        $request->merge(['website' => $website !== '' ? $website : null]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'website' => 'nullable|url|max:255',
            'tax_number' => ['nullable', 'string', 'max:100', new Tin],
            'currency' => 'nullable|string|size:3',
            'fiscal_year_start' => 'nullable|date',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:2048',
        ]);

        $tenant = auth()->user()->tenant;

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'public');
            $validated['logo'] = $path;
        }

        $tenant->update($validated);

        return redirect()->back()->with('success', 'Company profile updated successfully.');
    }

    /**
     * Roles a tenant may assign: its own roles plus the shared system roles,
     * never the platform-wide super-admin role (finding H4), and only roles
     * within the editor's own access (audit MB-05).
     */
    private function assignableRoles()
    {
        $actor = auth()->user();

        return Role::forTenant($actor->tenant_id)
            ->where('name', '!=', 'super-admin')
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get()
            ->filter(fn (Role $role) => Privileges::canAssignRole($actor, $role))
            ->values();
    }

    /**
     * Validation rules for the roles[] field, which carries role IDs.
     */
    private function roleRules(): array
    {
        return [
            'roles' => 'array',
            'roles.*' => ['integer', Rule::in($this->assignableRoles()->pluck('id')->all())],
        ];
    }

    private function rolesFromIds(array $ids)
    {
        return $this->assignableRoles()->whereIn('id', $ids)->values();
    }

    /** Someone with more access than the editor, or any admin for a non-admin, is off limits (audit MB-05). */
    private function authorizeManaging(User $user): void
    {
        abort_unless(Privileges::canManageUser(auth()->user(), $user), 403, 'You can only manage users who have no more access than you.');
    }

    /** Record which roles were added to and taken from $user. */
    private function logRoleChanges(User $user, array $before, array $after): void
    {
        foreach (array_diff($after, $before) as $name) {
            ActivityLogService::logRoleChanged($user, $name, 'assigned');
        }
        foreach (array_diff($before, $after) as $name) {
            ActivityLogService::logRoleChanged($user, $name, 'removed');
        }
    }

    /** Record which permissions were added to and taken from $role. */
    private function logPermissionChanges(Role $role, array $before, array $after): void
    {
        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));
        if ($added || $removed) {
            ActivityLogService::log(ActivityLog::ACTION_PERMISSION_CHANGED, "Permissions changed for role '{$role->name}'",
                Role::class, $role->id, $role->name, array_filter(['added' => $added, 'removed' => $removed]));
        }
    }

    public function users()
    {
        $tenant = auth()->user()->tenant;

        return view('settings.users.index', [
            'users' => User::where('tenant_id', $tenant->id)->with('roles:id,name')->orderBy('name')->paginate(25),
            'tenant' => $tenant,
            'plan' => $tenant->currentPlan(),
            'userCount' => $tenant->users()->count(),
            'canAddUsers' => $tenant->canAddUsers(),
        ]);
    }

    public function createUser()
    {
        $tenant = auth()->user()->tenant;

        // Check if tenant can add more users
        if (! $tenant->canAddUsers()) {
            $plan = $tenant->currentPlan();
            $planName = $plan ? $plan->name : 'current';

            return redirect()->route('settings.users')
                ->with('error', "You have reached the maximum number of users allowed on the {$planName} plan. Please upgrade your subscription to add more users.");
        }

        $roles = $this->assignableRoles();

        return view('settings.users.create', compact('roles'));
    }

    public function storeUser(Request $request)
    {
        $tenant = auth()->user()->tenant;

        // Check if tenant can add more users
        if (! $tenant->canAddUsers()) {
            $plan = $tenant->currentPlan();
            $planName = $plan ? $plan->name : 'current';

            return redirect()->route('settings.users')
                ->with('error', "You have reached the maximum number of users allowed on the {$planName} plan. Please upgrade your subscription to add more users.");
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::defaults()],
            'phone' => 'nullable|string|max:50',
            ...$this->roleRules(),
        ]);

        $user = User::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
        ]);

        if (! empty($validated['roles'])) {
            $user->syncRoles($this->rolesFromIds($validated['roles']));
            $this->logRoleChanges($user, [], $user->roles->pluck('name')->all());
        }

        return redirect()->route('settings.users')->with('success', 'User created successfully.');
    }

    public function editUser(User $user)
    {
        if ($user->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $isSelf = $user->id === auth()->id() && ! auth()->user()->isSuperAdmin();
        if (! $isSelf) {
            $this->authorizeManaging($user);
        }
        $roles = $this->assignableRoles();
        $canChangeCredentials = $isSelf || Privileges::isAdmin(auth()->user());

        return view('settings.users.edit', compact('user', 'roles', 'isSelf', 'canChangeCredentials'));
    }

    public function updateUser(Request $request, User $user)
    {
        if ($user->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        // Users cannot change their own roles or deactivate themselves (H4).
        $isSelf = $user->id === auth()->id() && ! auth()->user()->isSuperAdmin();
        if (! $isSelf) {
            $this->authorizeManaging($user);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'phone' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            ...$this->roleRules(),
        ]);

        // Another person's sign-in details are an admin's to change, and only
        // just after confirming their own password (audit MB-05): otherwise
        // whoever holds "edit users" could take over any account.
        $changesCredentials = mb_strtolower($validated['email']) !== mb_strtolower($user->email) || ! empty($validated['password']);
        if (! $isSelf && $changesCredentials) {
            if (! Privileges::isAdmin(auth()->user())) {
                return back()->withInput($request->except('password', 'password_confirmation'))
                    ->withErrors(['email' => 'Only an admin can change another user\'s email or password.']);
            }
            if (! $this->passwordRecentlyConfirmed($request)) {
                $request->session()->put('url.intended', route('settings.users.edit', $user));

                return redirect()->route('password.confirm')
                    ->with('status', 'Confirm your password, then save the change to '.$user->name.'\'s sign-in details again.');
            }
        }

        $oldEmail = $user->email;
        // The last active admin keeps the admin role and stays active, or
        // nobody could manage the business (O7).
        if (! $isSelf && $user->isLastActiveAdmin()) {
            $keepsAdmin = $this->rolesFromIds($validated['roles'] ?? [])->contains(fn ($role) => $role->name === 'admin');
            if (! $keepsAdmin || ! ($validated['is_active'] ?? true)) {
                return back()->withInput()->with('error', 'This is the only admin of the business. Make another user an admin first.');
            }
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'is_active' => $isSelf ? $user->is_active : ($validated['is_active'] ?? true),
        ]);

        if ($user->email !== $oldEmail) {
            // Tell the old address about the change (S6).
            Notification::route('mail', $oldEmail)
                ->notify(new EmailAddressChangedNotification($user->name, $oldEmail, $user->email, changedByAdmin: true));
        }

        if (! empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
            // The user is signed out everywhere else, API tokens included (S5).
            app(SignOutOtherSessions::class)->handle($user, $request);
            if (! $isSelf) {
                ActivityLogService::log(ActivityLog::ACTION_PASSWORD_CHANGED, "Password changed for user '{$user->name}' by '".auth()->user()->name."'",
                    User::class, $user->id, $user->name);
            }
        }

        if (! $isSelf) {
            // Keep any role the editor cannot see or assign (e.g. super-admin).
            $before = $user->roles->pluck('name')->all();
            $assignable = $this->assignableRoles()->pluck('id');
            $kept = $user->roles->reject(fn ($role) => $assignable->contains($role->id));
            $user->syncRoles($kept->merge($this->rolesFromIds($validated['roles'] ?? [])));
            $this->logRoleChanges($user, $before, $user->fresh()->roles->pluck('name')->all());
        }

        return redirect()->route('settings.users')->with('success', 'User updated successfully.');
    }

    public function deleteUser(User $user)
    {
        if ($user->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        if ($user->id === auth()->id()) {
            return redirect()->back()->with('error', 'You cannot delete your own account.');
        }
        $this->authorizeManaging($user);

        if ($user->isLastActiveAdmin()) {
            return redirect()->back()->with('error', 'This is the only admin of the business. Make another user an admin first.');
        }

        $user->delete();

        return redirect()->route('settings.users')->with('success', 'User deleted successfully.');
    }

    /** Whether the signed-in person confirmed their password within auth.password_timeout. */
    private function passwordRecentlyConfirmed(Request $request): bool
    {
        return time() - (int) $request->session()->get('auth.password_confirmed_at', 0) < (int) config('auth.password_timeout', 10800);
    }

    /** Permissions to offer in the role forms: only those the editor holds (audit MB-05). */
    private function grantablePermissions()
    {
        $actor = auth()->user();
        $held = Privileges::isAdmin($actor) ? null : Privileges::permissionsOf($actor);

        return Permission::all()
            ->when($held !== null, fn ($all) => $all->filter(fn ($p) => $held->contains($p->name)))
            ->groupBy(function ($permission) {
                // Group by resource (second part of permission name, e.g., "invoices" from "view invoices")
                $parts = explode(' ', $permission->name, 2);

                return $parts[1] ?? $parts[0];
            });
    }

    /** Validation: every permission given is one the editor holds. */
    private function heldPermissionsOnly(): \Closure
    {
        return function ($attribute, $value, $fail) {
            if ($missing = Privileges::missing(auth()->user(), (array) $value)) {
                $fail('You can only give permissions you have yourself. Not yours: '.implode(', ', $missing).'.');
            }
        };
    }

    /** A role the editor has, or one beyond their access, is not theirs to change (audit MB-05). */
    private function authorizeRole(Role $role): void
    {
        abort_unless(Privileges::canManageRole(auth()->user(), $role), 403, 'You can only change roles you do not have yourself and that give no more access than you have.');
    }

    public function roles()
    {
        $tenantId = auth()->user()->tenant_id;

        return view('settings.roles.index', [
            'roles' => Role::forTenant($tenantId)
                ->withCount(['permissions', 'users' => fn ($q) => $q->where('tenant_id', $tenantId)])
                ->orderByRaw('tenant_id IS NULL DESC')->orderBy('name')
                ->paginate(25),
        ]);
    }

    public function createRole()
    {
        $permissions = $this->grantablePermissions();

        return view('settings.roles.create', compact('permissions'));
    }

    public function storeRole(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                $this->notReservedRoleName(),
                function ($attribute, $value, $fail) use ($tenantId) {
                    $exists = Role::withoutGlobalScopes()
                        ->where('name', $value)
                        ->where('tenant_id', $tenantId)
                        ->exists();
                    if ($exists) {
                        $fail('A role with this name already exists.');
                    }
                },
            ],
            'permissions' => ['array', $this->heldPermissionsOnly()],
            'permissions.*' => 'exists:permissions,name',
        ]);

        try {
            $role = Role::create([
                'name' => $validated['name'],
                'guard_name' => 'web',
                'tenant_id' => $tenantId,
            ]);
        } catch (RoleAlreadyExists) {
            return back()->withInput()->withErrors(['name' => self::ROLE_NAME_TAKEN]);
        }

        if (! empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
            $this->logPermissionChanges($role, [], $validated['permissions']);
        }

        return redirect()->route('settings.roles')->with('success', 'Role created successfully.');
    }

    public function editRole(Role $role)
    {
        $tenantId = auth()->user()->tenant_id;
        $this->authorizeRole($role);

        // If it's a global role, check if tenant already has a customized version
        if ($role->isGlobal()) {
            $tenantRole = Role::where('name', $role->name)
                ->where('tenant_id', $tenantId)
                ->first();

            if ($tenantRole) {
                // Redirect to edit the existing tenant-specific version
                return redirect()->route('settings.roles.edit', $tenantRole);
            }
        }

        $permissions = $this->grantablePermissions();
        $rolePermissions = $role->permissions->pluck('name')->toArray();
        $isCustomizing = $role->isGlobal();

        return view('settings.roles.edit', compact('role', 'permissions', 'rolePermissions', 'isCustomizing'));
    }

    public function updateRole(Request $request, Role $role)
    {
        $tenantId = auth()->user()->tenant_id;
        $this->authorizeRole($role);

        // If it's a global role, create a tenant-specific copy
        if ($role->isGlobal()) {
            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    $this->notReservedRoleName(),
                    function ($attribute, $value, $fail) use ($tenantId) {
                        $exists = Role::where('name', $value)
                            ->where('tenant_id', $tenantId)
                            ->exists();
                        if ($exists) {
                            $fail('A role with this name already exists for your organization.');
                        }
                    },
                ],
                'permissions' => ['array', $this->heldPermissionsOnly()],
                'permissions.*' => 'exists:permissions,name',
            ]);

            // Create a new tenant-specific role based on the system role
            try {
                $newRole = Role::create([
                    'name' => $validated['name'],
                    'guard_name' => 'web',
                    'tenant_id' => $tenantId,
                ]);
            } catch (RoleAlreadyExists) {
                return back()->withInput()->withErrors(['name' => self::ROLE_NAME_TAKEN]);
            }

            $newRole->syncPermissions($validated['permissions'] ?? []);
            $this->logPermissionChanges($newRole, [], $validated['permissions'] ?? []);

            return redirect()->route('settings.roles')->with('success', 'Custom role created successfully based on system role.');
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                $this->notReservedRoleName(),
                function ($attribute, $value, $fail) use ($tenantId, $role) {
                    $exists = Role::where('name', $value)
                        ->where('tenant_id', $tenantId)
                        ->where('id', '!=', $role->id)
                        ->exists();
                    if ($exists) {
                        $fail('A role with this name already exists.');
                    }
                },
            ],
            'permissions' => ['array', $this->heldPermissionsOnly()],
            'permissions.*' => 'exists:permissions,name',
        ]);

        $before = $role->permissions->pluck('name')->all();
        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions'] ?? []);
        $this->logPermissionChanges($role, $before, $validated['permissions'] ?? []);

        return redirect()->route('settings.roles')->with('success', 'Role updated successfully.');
    }

    public function destroyRole(Role $role)
    {
        // Prevent deleting global/system roles
        if ($role->isGlobal()) {
            return redirect()->back()->with('error', 'System roles cannot be deleted.');
        }

        $this->authorizeRole($role);

        if ($role->users()->count() > 0) {
            return redirect()->back()->with('error', 'Cannot delete role assigned to users.');
        }

        $role->delete();

        return redirect()->route('settings.roles')->with('success', 'Role deleted successfully.');
    }

    /**
     * Show notification settings
     */
    public function notifications()
    {
        $settings = NotificationSetting::getForTenant(auth()->user()->tenant_id);

        return view('settings.notifications', compact('settings'));
    }

    /**
     * Update notification settings
     */
    public function updateNotifications(Request $request)
    {
        $validated = $request->validate([
            'send_invoice_on_create' => 'boolean',
            'send_payment_confirmation' => 'boolean',
            'send_overdue_reminders' => 'boolean',
            'overdue_reminder_days' => 'required|integer|min:1|max:30',
            'send_payment_reminders' => 'boolean',
            'payment_reminder_days_before' => 'required|integer|min:1|max:14',
            'send_bill_due_reminders' => 'boolean',
            'bill_reminder_days_before' => 'required|integer|min:1|max:14',
            'send_low_stock_alerts' => 'boolean',
            'low_stock_alert_frequency' => 'required|in:daily,weekly',
            'send_payroll_notifications' => 'boolean',
            'send_leave_notifications' => 'boolean',
            'email_from_name' => 'nullable|string|max:255',
            'email_from_address' => 'nullable|email|max:255',
            'email_reply_to' => 'nullable|email|max:255',
        ]);

        // Convert checkbox values
        $validated['send_invoice_on_create'] = $request->boolean('send_invoice_on_create');
        $validated['send_payment_confirmation'] = $request->boolean('send_payment_confirmation');
        $validated['send_overdue_reminders'] = $request->boolean('send_overdue_reminders');
        $validated['send_payment_reminders'] = $request->boolean('send_payment_reminders');
        $validated['send_bill_due_reminders'] = $request->boolean('send_bill_due_reminders');
        $validated['send_low_stock_alerts'] = $request->boolean('send_low_stock_alerts');
        $validated['send_payroll_notifications'] = $request->boolean('send_payroll_notifications');
        $validated['send_leave_notifications'] = $request->boolean('send_leave_notifications');

        $settings = NotificationSetting::getForTenant(auth()->user()->tenant_id);
        $settings->update($validated);

        return redirect()->back()->with('success', 'Notification settings updated successfully.');
    }

    /**
     * Send a test email
     */
    public function sendTestEmail(Request $request)
    {
        $request->validate([
            'test_email' => 'required|email',
        ]);

        try {
            $tenant = auth()->user()->tenant;

            // Create an anonymous notifiable for the test email
            $notifiable = new class($request->test_email)
            {
                use Notifiable;

                public function __construct(public string $email) {}

                public function routeNotificationForMail(): string
                {
                    return $this->email;
                }
            };

            $notifiable->notify(new TestEmailNotification($tenant->name));

            return redirect()->back()->with('success', "Test email sent to {$request->test_email}");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to send test email: '.$e->getMessage());
        }
    }
}
