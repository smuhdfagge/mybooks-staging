<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who may hand out or change access (audit MB-05).
 *
 * "edit users" let anyone reset an admin's email and password or make
 * themselves admin, and "edit roles" let anyone add any permission to their
 * own role. The rule now: nobody can give, or change for someone else,
 * more than they hold themselves. Admins hold everything, so this never
 * gets in their way.
 */
class Privileges
{
    /** Roles that only an admin may give. */
    public const ADMIN_ROLES = ['admin', 'super-admin'];

    public static function isAdmin(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasRole(self::ADMIN_ROLES);
    }

    /** @return Collection<int, string> */
    public static function permissionsOf(User $user): Collection
    {
        return $user->getAllPermissions()->pluck('name');
    }

    /**
     * Whether $actor holds every one of $permissions.
     *
     * @param  iterable<int, string>  $permissions
     */
    public static function holdsAll(User $actor, iterable $permissions): bool
    {
        if (self::isAdmin($actor)) {
            return true;
        }

        return collect($permissions)->diff(self::permissionsOf($actor))->isEmpty();
    }

    /**
     * The permissions in $permissions that $actor doesn't hold.
     *
     * @param  iterable<int, string>  $permissions
     * @return array<int, string>
     */
    public static function missing(User $actor, iterable $permissions): array
    {
        if (self::isAdmin($actor)) {
            return [];
        }

        return collect($permissions)->diff(self::permissionsOf($actor))->values()->all();
    }

    /** Give $role to someone: never an admin role, and nothing beyond $actor's own access. */
    public static function canAssignRole(User $actor, Role $role): bool
    {
        if (self::isAdmin($actor)) {
            return true;
        }

        return ! in_array($role->name, self::ADMIN_ROLES, true)
            && self::holdsAll($actor, $role->permissions->pluck('name'));
    }

    /** Edit, deactivate or delete $target: never an admin, and nobody with more access than $actor. */
    public static function canManageUser(User $actor, User $target): bool
    {
        if (self::isAdmin($actor)) {
            return true;
        }

        return ! self::isAdmin($target) && self::holdsAll($actor, self::permissionsOf($target));
    }

    /** Change or delete $role: not one $actor has themselves, and nothing beyond their own access. */
    public static function canManageRole(User $actor, Role $role): bool
    {
        if (self::isAdmin($actor)) {
            return true;
        }

        return ! in_array($role->name, self::ADMIN_ROLES, true)
            && ! $actor->roles->contains('id', $role->id)
            && self::holdsAll($actor, $role->permissions->pluck('name'));
    }
}
