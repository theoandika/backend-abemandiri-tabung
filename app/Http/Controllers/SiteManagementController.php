<?php

namespace App\Http\Controllers;

use App\Helpers\Response;
use App\Http\Resources\DetailSiteResource;
use App\Http\Resources\SimpleSiteResource;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SiteManagementController extends Controller
{
    public function all(Request $r)
    {
        $user = $r->user();
        if ($user->level === 0) {
            $sites = Site::orderBy('name')->get();
        } else {
            $sitePermission = $user->userSites->pluck('site_id');
            $sites = Site::orderBy('name')->whereIn('id', $sitePermission)->get();
        }
        return SimpleSiteResource::collection($sites);
    }

    public function index(Request $r)
    {
        $r->validate([
            'search' => 'bail|nullable|string|max:50',
            'paginate' => 'bail|nullable|integer|min:1'
        ]);

        try {
            $_sites = Site::when($r->input('search'), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('code', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%')
                    ->orWhere('address', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name');
            if ($r->filled('paginate')) {
                $sites = $_sites->paginate($r->integer('paginate'));
            } else {
                $sites = $_sites->get();
            }
            return DetailSiteResource::collection($sites);
        } catch (\Throwable $th) {
            return Response::internalError($th->getMessage());
        }
    }

    public function detail(string $uid)
    {
        $site = Site::where('uid', $uid)->firstOrFail();
        return new DetailSiteResource($site);
    }

    public function create(Request $r)
    {
        $r->validate([
            'code' => 'bail|required|string|max:20',
            'name' => 'bail|required|string|max:100',
            'address' => 'bail|nullable|string|max:200',
        ], [
            'code.required' => 'Masukkan kode cabang',
            'name.required' => 'Masukkan nama cabang',
            'name.max' => 'Nama cabang maksimal 100 karakter',
            'address.max' => 'Alamat maksimal 200 karakter',
        ]);

        DB::beginTransaction();
        try {
            $site = new Site;
            $site->code = $r->input('code');
            $site->name = $r->input('name');
            $site->address = $r->input('address');
            $site->save();
            DB::commit();
            return Response::created();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }

    public function update(Request $r, string $uid)
    {
        $site = Site::where('uid', $uid)->firstOrFail();
        $r->validate([
            'code' => 'bail|required|string|max:20',
            'name' => 'bail|required|string|max:100',
            'address' => 'bail|nullable|string|max:200',
        ], [
            'code.required' => 'Masukkan kode cabang',
            'name.required' => 'Masukkan nama cabang',
            'name.max' => 'Nama cabang maksimal 100 karakter',
            'address.max' => 'Alamat maksimal 200 karakter',
        ]);

        DB::beginTransaction();
        try {
            $site->code = $r->input('code');
            $site->name = $r->input('name');
            $site->address = $r->input('address');
            $site->save();
            DB::commit();
            return Response::updated();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }

    public function delete(string $uid)
    {
        $site = Site::where('uid', $uid)->firstOrFail();
        DB::beginTransaction();
        try {
            if ($site->userSites()->exists() || $site->transactions()->exists() || $site->supplierTransactions()->exists() || $site->tubeTransactions()->exists()) {
                return Response::error(__('message.delete_fail_has_relationship'), 403);
            }
            $site->delete();
            DB::commit();
            return Response::deleted();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }
}
