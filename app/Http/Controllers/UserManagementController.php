<?php

namespace App\Http\Controllers;

use App\Helpers\Response;
use App\Http\Resources\UserIndexResource;
use App\Http\Resources\UserSimpleResource;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\UserSite;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserManagementController extends Controller
{
    public function all()
    {
        $users = User::whereNot('level', 0)->orderBy('name')->get();
        return UserSimpleResource::collection($users);
    }

    public function index(Request $r)
    {
        $r->validate([
            'search' => 'bail|nullable|string|max:50',
            'paginate' => 'bail|nullable|integer|min:1'
        ]);

        try {
            $_users = User::whereNot('level', 0)
            ->when($r->input('search'), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name');
            if ($r->filled('paginate')) {
                $users = $_users->paginate($r->input('paginate'));
            } else {
                $users = $_users->get();
            }
            return UserIndexResource::collection($users);
        } catch (\Throwable $th) {
            return Response::internalError($th->getMessage());
        }
    }

    public function detail(string $uid)
    {
        $user = User::whereNot('level', 0)->where('uid', $uid)->firstOrFail();
        return new UserIndexResource($user);
    }

    public function create(Request $r)
    {
        $r->validate([
            'role' => 'bail|required|exists:roles,uid',
            'name' => 'bail|required|max:100',
            'email' => 'bail|required|email|unique:users,email',
            'password' => 'bail|required|string',
            'is_active' => 'bail|required|boolean',
            'sites' => 'bail|nullable|array',
            'sites.*' => 'bail|required|exists:sites,uid'
        ], [
            'role.required' => 'Tentukan role',
            'name.required' => 'Masukkan nama akun',
            'name.max' => 'Nama maksimal 100 karakter',
            'email.required' => 'Masukkan email',
            'email.unique' => 'Alamat email sudah digunakan',
            'password.required' => 'Masukkan password',
            'is_active.required' => 'Tentukan status akun',
            'sites.*.required' => 'Tentukan cabang',
        ]);

        DB::beginTransaction();
        try {
            $role = Role::where('uid', $r->input('role'))->first();
            $user = new User;
            $user->role()->associate($role);
            $user->name = $r->input('name');
            $user->email = $r->input('email');
            $user->email_verified_at = Carbon::now();
            $user->password = Hash::make($r->input('password'));
            $user->level = 1;
            $user->is_active = $r->boolean('is_active');
            $user->save();

            if ($r->filled('sites')) {
                foreach ($r->input('sites') as $site) {
                    $site = Site::where('uid', $site)->first();
                    $userSite = new UserSite;
                    $userSite->user()->associate($user);
                    $userSite->site()->associate($site);
                    $userSite->save();
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
        $user = User::where('uid', $uid)->firstOrFail();
        $r->validate([
            'role' => 'bail|required|exists:roles,uid',
            'name' => 'bail|required|max:100',
            'password' => 'bail|nullable|string',
            'is_active' => 'bail|required|boolean',
            'sites' => 'bail|nullable|array',
            'sites.*' => 'bail|required|exists:sites,uid'
        ], [
            'role.required' => 'Tentukan role',
            'name.required' => 'Masukkan nama akun',
            'name.max' => 'Nama maksimal 100 karakter',
            'is_active.required' => 'Tentukan status akun',
            'sites.*.required' => 'Tentukan cabang',
        ]);

        DB::beginTransaction();
        try {
            $role = Role::where('uid', $r->input('role'))->first();
            $user->role()->associate($role);
            $user->name = $r->input('name');
            if ($r->filled('password')) {
                $user->password = Hash::make($r->input('password'));
            }
            $user->is_active = $r->boolean('is_active');
            $user->save();

            if ($r->filled('sites')) {
                $keepId = [];
                foreach ($r->input('sites') as $site) {
                    $site = Site::where('uid', $site)->first();
                    $userSite = UserSite::updateOrCreate([
                        'user_id' => $user->id,
                        'site_id' => $site->id
                    ]);
                    $keepId[] = $userSite->id;
                }
                UserSite::where('user_id', $user->id)->whereNotIn('id', $keepId)->delete();
            } else {
                UserSite::where('user_id', $user->id)->delete();
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
        $user = User::whereNot('level', 0)->where('uid', $uid)->firstOrFail();
        DB::beginTransaction();
        try {
            $user->delete();
            DB::commit();
            return Response::deleted();
        } catch (\Throwable $th) {
            DB::rollBack();
        }
    }
}
