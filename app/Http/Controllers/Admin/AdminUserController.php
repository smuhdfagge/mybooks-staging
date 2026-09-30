<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Models\ActivityLog;
use App\Models\AdminUser;
use App\Services\AdminAuditService;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    /**
     * Display a listing of admin users
     */
    public function index()
    {
        $adminUsers = AdminUser::orderBy('name')->paginate(15);

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

        $before = $adminUser->getAttributes();
        $adminUser->name = $validated['name'];
        $adminUser->email = $validated['email'];
        $adminUser->role = $validated['role'];

        if (! empty($validated['password'])) {
            $adminUser->password = Hash::make($validated['password']);
        }

        $adminUser->save();

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
}
