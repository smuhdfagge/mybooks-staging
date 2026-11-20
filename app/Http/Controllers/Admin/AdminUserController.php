<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAdminTwoFactor;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Models\ActivityLog;
use App\Models\AdminUser;
use App\Services\AdminAuditService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserController extends Controller
{
    /**
     * Display a listing of admin users
     */
    public function index()
    {
        $adminUsers = AdminUser::orderBy('name')->paginate(25);

        return view('admin.users.index', compact('adminUsers'));
    }

    /**
     * Show the form for creating a new admin user
     */
    public function create()
    {
        return view('admin.users.create');
    }

    /**
     * Store a newly created admin user
     */
    public function store(StoreAdminUserRequest $request)
    {
        $validated = $request->validated();

        $adminUser = AdminUser::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'is_active' => true,
        ]);

        AdminAuditService::log(
            ActivityLog::ACTION_CREATED,
            "created admin '{$adminUser->email}' with role '{$adminUser->role}'",
            $adminUser,
            null,
            $adminUser->only(['name', 'email', 'role', 'is_active']),
        );

        return redirect()->route('admin.users.index')
            ->with('success', 'Admin user created successfully.');
    }

    /**
     * Show the form for editing an admin user
     */
    public function edit(AdminUser $adminUser)
    {
        return view('admin.users.edit', compact('adminUser'));
    }

    /**
     * Update the specified admin user
     */
    public function update(UpdateAdminUserRequest $request, AdminUser $adminUser)
    {
        $validated = $request->validated();

        // Prevent demoting your own super_admin role
        if ($adminUser->id === auth('admin')->id() && $validated['role'] !== $adminUser->role) {
            return back()->with('error', 'You cannot change your own role.');
        }

        if ($validated['role'] !== AdminUser::ROLE_SUPER_ADMIN && $this->isLastActiveSuperAdmin($adminUser)) {
            return back()->with('error', 'This is the last active super admin: make someone else a super admin first.');
        }

        $before = $adminUser->getAttributes();
        $adminUser->name = $validated['name'];
        $adminUser->email = $validated['email'];
        $adminUser->role = $validated['role'];

        if (! empty($validated['password'])) {
            $adminUser->password = Hash::make($validated['password']);
            $adminUser->remember_token = Str::random(60);
        }

        $adminUser->save();

        // Other sessions of this admin now fail the password check in
        // EnsureAdminTwoFactor; keep our own session if we changed our own (S5).
        if (! empty($validated['password']) && $adminUser->id === auth('admin')->id()) {
            EnsureAdminTwoFactor::rememberPassword($request, $adminUser);
        }

        AdminAuditService::logChange("updated admin '{$adminUser->email}'", $adminUser, $before);

        return redirect()->route('admin.users.index')
            ->with('success', 'Admin user updated successfully.');
    }

    /**
     * Toggle admin user status
     */
    public function toggleStatus(AdminUser $adminUser)
    {
        // Prevent deactivating yourself
        if ($adminUser->id === auth('admin')->id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        // Deactivating, like deleting, must leave someone who can sign in
        // and manage admins (audit MB-59).
        if ($adminUser->is_active && (AdminUser::where('is_active', true)->count() <= 1 || $this->isLastActiveSuperAdmin($adminUser))) {
            return back()->with('error', 'Cannot deactivate the last active admin who can manage admin users.');
        }

        $before = $adminUser->getAttributes();
        $adminUser->is_active = ! $adminUser->is_active;
        $adminUser->save();

        $status = $adminUser->is_active ? 'activated' : 'deactivated';
        AdminAuditService::logChange("{$status} admin '{$adminUser->email}'", $adminUser, $before);

        return back()->with('success', "Admin user {$status} successfully.");
    }

    /**
     * Remove the specified admin user
     */
    public function destroy(AdminUser $adminUser)
    {
        // Prevent deleting yourself
        if ($adminUser->id === auth('admin')->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        // Ensure at least one admin remains
        if (AdminUser::where('is_active', true)->count() <= 1) {
            return back()->with('error', 'Cannot delete the last active admin user.');
        }
        if ($this->isLastActiveSuperAdmin($adminUser)) {
            return back()->with('error', 'Cannot delete the last active super admin.');
        }

        AdminAuditService::log(
            ActivityLog::ACTION_DELETED,
            "deleted admin '{$adminUser->email}'",
            $adminUser,
            $adminUser->only(['name', 'email', 'role', 'is_active']),
        );
        $adminUser->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'Admin user deleted successfully.');
    }

    /** Whether $adminUser is the only active admin left who can manage admin users. */
    private function isLastActiveSuperAdmin(AdminUser $adminUser): bool
    {
        return $adminUser->isSuperAdmin() && $adminUser->is_active
            && AdminUser::where('role', AdminUser::ROLE_SUPER_ADMIN)->where('is_active', true)->count() <= 1;
    }
}
