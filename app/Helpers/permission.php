<?php

if (! function_exists('canPermission')) {
    /**
     * Check if authenticated user has permission.
     */
    function canPermission(string $permission): bool {
        return auth()->check() && auth()->user()->canPermission($permission);
    }
}

if (! function_exists('cannotPermission')) {
    /**
     * Check if authenticated user does not have permission.
     */
    function cannotPermission(string $permission): bool {
        return ! canPermission($permission);
    }
}

if (! function_exists('userCanAnyPermission')) {
    /**
     * Check if user has any permission.
     */
    function userCanAnyPermission(array $permissions): bool {
        foreach ($permissions as $permission) {
            if (canPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('userCanAllPermissions')) {
    /**
     * Check if user has all permissions.
     */
    function userCanAllPermissions(array $permissions): bool {
        foreach ($permissions as $permission) {
            if (! canPermission($permission)) {
                return false;
            }
        }

        return true;
    }
}