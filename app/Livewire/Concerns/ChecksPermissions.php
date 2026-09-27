<?php

namespace App\Livewire\Concerns;

/**
 * Permission checks for Livewire actions (finding C2).
 *
 * Livewire actions are sent to /livewire/update, not to the page's route, so
 * the route's `permission:` middleware does not protect them. Every action
 * that changes data must check the permission itself.
 *
 * Table components list their bulk actions in bulkActionPermissions():
 *
 *     protected function bulkActionPermissions(): array
 *     {
 *         return [
 *             'activate' => 'edit customers',
 *             'delete'   => 'delete customers',
 *         ];
 *     }
 *
 * An action missing from that list is refused, so a new bulk action cannot
 * be added without deciding who may run it.
 */
trait ChecksPermissions
{
    /** Use as a bulk-action permission for admin-only actions. */
    public const ADMIN_ONLY = '__admin_only__';

    /**
     * Abort with 403 unless the user has at least one of the permissions.
     */
    protected function requirePermission(string ...$permissions): void
    {
        $user = auth()->user();

        foreach ($permissions as $permission) {
            if ($user?->can($permission)) {
                return;
            }
        }

        abort(403, 'You do not have permission to perform this action.');
    }

    /**
     * Abort with 403 unless the user is a tenant admin or super admin.
     * Mirrors the `role:admin` route middleware.
     */
    protected function requireAdmin(): void
    {
        $user = auth()->user();

        abort_unless(
            $user && ($user->isSuperAdmin() || $user->hasRole('admin')),
            403,
            'You do not have permission to perform this action.'
        );
    }

    /**
     * Check the permission for the currently selected bulk action.
     */
    protected function authorizeBulkAction(): void
    {
        $map = method_exists($this, 'bulkActionPermissions') ? $this->bulkActionPermissions() : [];
        $permission = $map[$this->bulkAction] ?? null;

        abort_if($permission === null, 403, 'This action is not allowed.');

        if ($permission === self::ADMIN_ONLY) {
            $this->requireAdmin();

            return;
        }

        $this->requirePermission(...(array) $permission);
    }
}
