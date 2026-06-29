<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[WithoutTimestamps]
class RolePermission extends Model
{
    protected static function booted(): void
    {
        static::creating(function ($permission) {
            $permission->permission_name = (new \App\Constants\Permission)->getName($permission->permission_key);
        });
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
