<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\NotificationSetting;
use App\Models\Role;
use App\Models\State;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TestEmailNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

class SettingsController extends Controller
{
    public function company()
    {
        $tenant = auth()->user()->tenant;
        $countries = Country::orderBy('name')->get();
        $states = State::orderBy('name')->get();
        return view('settings.company', compact('tenant', 'countries', 'states'));
    }

    public function updateCompany(Request $request)
    {
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
            'tax_number' => 'nullable|string|max:100',
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

    public function users()
    {
        return view('settings.users.index');
    }

    public function createUser()
    {
        $tenant = auth()->user()->tenant;
        
        // Check if tenant can add more users
        if (!$tenant->canAddUsers()) {
            $plan = $tenant->currentPlan();
            $planName = $plan ? $plan->name : 'current';
            return redirect()->route('settings.users')
                ->with('error', "You have reached the maximum number of users allowed on the {$planName} plan. Please upgrade your subscription to add more users.");
        }

        $roles = Role::forTenant(auth()->user()->tenant_id)->get();
        return view('settings.users.create', compact('roles'));
    }

    public function storeUser(Request $request)
    {
        $tenant = auth()->user()->tenant;
        
        // Check if tenant can add more users
        if (!$tenant->canAddUsers()) {
            $plan = $tenant->currentPlan();
            $planName = $plan ? $plan->name : 'current';
            return redirect()->route('settings.users')
                ->with('error', "You have reached the maximum number of users allowed on the {$planName} plan. Please upgrade your subscription to add more users.");
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
            'phone' => 'nullable|string|max:50',
            'roles' => 'array',
            'roles.*' => 'exists:roles,name',
        ]);

        $user = User::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
        ]);

        if (!empty($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        }

        return redirect()->route('settings.users')->with('success', 'User created successfully.');
    }

    public function editUser(User $user)
    {
        if ($user->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $roles = Role::forTenant(auth()->user()->tenant_id)->get();
        return view('settings.users.edit', compact('user', 'roles'));
    }

    public function updateUser(Request $request, User $user)
    {
        if ($user->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'password' => ['nullable', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
            'phone' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'roles' => 'array',
            'roles.*' => 'exists:roles,name',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (!empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        $user->syncRoles($validated['roles'] ?? []);

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

        $user->delete();
        return redirect()->route('settings.users')->with('success', 'User deleted successfully.');
    }

    public function roles()
    {
        return view('settings.roles.index');
    }

    public function createRole()
    {
        $permissions = Permission::all()->groupBy(function ($permission) {
            // Group by resource (second part of permission name, e.g., "invoices" from "view invoices")
            $parts = explode(' ', $permission->name, 2);
            return $parts[1] ?? $parts[0];
        });
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
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'tenant_id' => $tenantId,
        ]);
        
        if (!empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return redirect()->route('settings.roles')->with('success', 'Role created successfully.');
    }

    public function editRole(Role $role)
    {
        $tenantId = auth()->user()->tenant_id;

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

        $permissions = Permission::all()->groupBy(function ($permission) {
            // Group by resource (second part of permission name)
            $parts = explode(' ', $permission->name, 2);
            return $parts[1] ?? $parts[0];
        });
        $rolePermissions = $role->permissions->pluck('name')->toArray();
        $isCustomizing = $role->isGlobal();
        
        return view('settings.roles.edit', compact('role', 'permissions', 'rolePermissions', 'isCustomizing'));
    }

    public function updateRole(Request $request, Role $role)
    {
        $tenantId = auth()->user()->tenant_id;

        // If it's a global role, create a tenant-specific copy
        if ($role->isGlobal()) {
            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    function ($attribute, $value, $fail) use ($tenantId) {
                        $exists = Role::where('name', $value)
                            ->where('tenant_id', $tenantId)
                            ->exists();
                        if ($exists) {
                            $fail('A role with this name already exists for your organization.');
                        }
                    },
                ],
                'permissions' => 'array',
                'permissions.*' => 'exists:permissions,name',
            ]);

            // Create a new tenant-specific role based on the system role
            $newRole = Role::create([
                'name' => $validated['name'],
                'guard_name' => 'web',
                'tenant_id' => $tenantId,
            ]);
            
            $newRole->syncPermissions($validated['permissions'] ?? []);

            return redirect()->route('settings.roles')->with('success', 'Custom role created successfully based on system role.');
        }
        
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
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
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('settings.roles')->with('success', 'Role updated successfully.');
    }

    public function deleteRole(Role $role)
    {
        // Prevent deleting global/system roles
        if ($role->isGlobal()) {
            return redirect()->back()->with('error', 'System roles cannot be deleted.');
        }

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
            $notifiable = new class($request->test_email) {
                use \Illuminate\Notifications\Notifiable;
                
                public function __construct(public string $email) {}
                
                public function routeNotificationForMail(): string
                {
                    return $this->email;
                }
            };

            $notifiable->notify(new TestEmailNotification($tenant->name));

            return redirect()->back()->with('success', "Test email sent to {$request->test_email}");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to send test email: ' . $e->getMessage());
        }
    }
}
