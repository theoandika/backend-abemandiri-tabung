<?php

namespace App\Http\Middleware;

use App\Models\RolePermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Helpers\Response as ResponseHelper;

class Permission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if ($request->user()->level == 0) {
            return $next($request);
        }
        
        $roleId = $request->user()->role_id;
        foreach ($permissions as $permission) {
            if(RolePermission::where('role_id', $roleId)->where(function ($q) use ($permission) {
                $q->where('permission_key', $permission)
                ->orWhere('permission_key', 'manage-all');
            })->exists() && $request->user()->level == 1) {
                return $next($request);
            }
        }

        return ResponseHelper::error(__('message.access_denied'), 403);
    }
}
