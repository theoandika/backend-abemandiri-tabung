<?php

namespace App\Helpers;

use App\Models\RolePermission;
use App\Models\User;

class CheckPermission {
    public static function has(User $user, string $permission) {
        if ($user->level == 0) {
            return true;
        } else {
            $permissions = RolePermission::where('role_id', $user->role_id)->get();
            return (clone $permissions)->where('permission_key', $permission)->count() || (clone $permissions)->where('permission_key', 'manage-all')->count() ? true : false;
        }
    }
}