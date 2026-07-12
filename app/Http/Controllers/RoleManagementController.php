<?php

namespace App\Http\Controllers;

use App\Constants\Permission;
use App\Helpers\Response;
use App\Http\Resources\RoleDetailResource;
use App\Http\Resources\RoleFullResource;
use App\Http\Resources\RoleNameResource;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoleManagementController extends Controller
{
    public function all()
    {
        $roles = Role::orderBy('name')->get();
        return RoleNameResource::collection($roles);
    }

    public function index(Request $r)
    {
        $r->validate([
            'search' => 'bail|nullable|string|max:50',
            'permissions' => 'bail|nullable|array',
            'permissions.*' => [
                'bail',
                'required',
                Rule::in((new Permission)->list()->pluck('key'))
            ],
            'paginate' => 'bail|nullable|integer|min:1'    
        ], [
            'permissions.*.required' => 'Input tidak sesuai',
        ]);

        try {
            $_roles = Role::when($r->input('search'), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%'.$search.'%')
                    ->orWhereRelation('rolePermissions', 'permission_name', 'like', '%'.$search.'%');
                });
            })
            ->when($r->input('permissions'), function ($q, $permissions) {
                $q->whereHas('rolePermissions', function ($q) use ($permissions) {
                    $q->whereIn('permission_key', $permissions);
                });
            })
            ->orderBy('name');
            if ($r->filled('paginate')) {
                $roles = $_roles->paginate($r->input('paginate'));
            } else {
                $roles = $_roles->get();
            }
            return RoleFullResource::collection($roles);
        } catch (\Throwable $th) {
            return Response::internalError($th->getMessage());
        }
    }

    public function detail(string $uid)
    {
        $role = Role::where('uid', $uid)->firstOrFail();
        return new RoleDetailResource($role);
    }

    public function create(Request $r)
    {
        $r->validate([
            'name' => 'bail|required|string|max:50',
            'permissions' => 'bail|required|array|min:1',
            'permissions.*' => [
                'distinct',
                'required',
                Rule::in((new Permission)->list()->pluck('key'))
            ]
        ], [
            'name.required' => 'Masukkan nama role',
            'name.max' => 'Nama role maksimal 50 karakter',
            'permissions.required' => 'Tentukan hak akses',
            'permissions.min' => 'Tentukan hak akses',
            'permissions.*.distinct' => 'Input tidak sesuai',
            'permissions.*.required' => 'Tentukan hak akses',
        ]);

        DB::beginTransaction();
        try {
            $role = new Role;
            $role->name = $r->input('name');
            $role->save();
            if (collect($r->input('permissions'))->contains('manage-all')) {
                $access = new RolePermission;
                $access->permission_key = 'manage-all';
                $access->role()->associate($role);
                $access->save();
            } else {
                foreach ($r->input('permissions') as $permission) {
                    $access = new RolePermission;
                    $access->permission_key = $permission;
                    $access->role()->associate($role);
                    $access->save();
                }
            }
            DB::commit();
            return Response::created();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }

    public function update(Request $r, string $uid)
    {
        $role = Role::where('uid', $uid)->firstOrFail();
        $r->validate([
            'name' => 'bail|required|string|max:50',
            'permissions' => 'bail|required|array|min:1',
            'permissions.*' => [
                'distinct',
                'required',
                Rule::in((new Permission)->list()->pluck('key'))
            ]
        ], [
            'name.required' => 'Tentukan nama role',
            'name.max' => 'Nama role maksimal 50 karakter',
            'permissions.required' => 'Tentukan hak akses',
            'permissions.min' => 'Tentukan hak akses',
            'permissions.*.distinct' => 'Input tidak sesuai',
            'permissions.*.required' => 'Tentukan hak akses',
        ]);

        DB::beginTransaction();
        try {
            $role->name = $r->input('name');
            $role->save();
            $role->rolePermissions()->delete();
            if (collect($r->input('permissions'))->contains('manage-all')) {
                $access = new RolePermission;
                $access->permission_key = 'manage-all';
                $access->role()->associate($role);
                $access->save();
            } else {
                foreach ($r->input('permissions') as $permission) {
                    $access = new RolePermission;
                    $access->permission_key = $permission;
                    $access->role()->associate($role);
                    $access->save();
                }
            }
            DB::commit();
            return Response::updated();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }

    public function delete(string $uid)
    {
        $role = Role::where('uid', $uid)->firstOrFail();
        DB::beginTransaction();
        try {
            if ($role->users()->exists()) {
                return Response::error('Role tidak dapat dihapus', 403);
            }
            $role->delete();
            DB::commit();
            return Response::deleted();
        } catch (\Throwable $th) {
            return Response::internalError($th->getMessage());
        }
    }
}
