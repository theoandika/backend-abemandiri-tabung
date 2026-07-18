<?php

namespace App\Http\Controllers;

use App\Helpers\Response;
use App\Http\Resources\DetailTubeContentTypeResource;
use App\Models\TubeContentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TubeContentTypeManagementController extends Controller
{
    public function all()
    {
        $contentTypes = TubeContentType::orderBy('code')->orderBy('name')->get();
        return DetailTubeContentTypeResource::collection($contentTypes);
    }

    public function index(Request $r)
    {
        $r->validate([
            'search' => 'bail|nullable|string|max:50',
            'paginate' => 'bail|nullable|integer|min:1'
        ]);

        try {
            $_contentType = TubeContentType::when($r->input('search'), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('code', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name');
            if ($r->filled('paginate')) {
                $contentTypes = $_contentType->paginate($r->integer('paginate'));
            } else {
                $contentTypes = $_contentType->get();
            }
            return DetailTubeContentTypeResource::collection($contentTypes);
        } catch (\Throwable $th) {
            return Response::internalError($th->getMessage());
        }
    }

    public function detail(string $uid)
    {
        $contentType = TubeContentType::where('uid', $uid)->firstOrFail();
        return new DetailTubeContentTypeResource($contentType);
    }

    public function create(Request $r)
    {
        $r->validate([
            'code' => 'bail|nullable|string|max:20',
            'name' => 'bail|required|string|max:50'
        ], [
            'code.max' => 'Kode maksimal 20 karakter',
            'name.required' => 'Masukkan nama jenis isi tabung',
            'name.max' => 'Nama maksimal 50 karakter'
        ]);

        DB::beginTransaction();
        try {
            $contentType = new TubeContentType;
            $contentType->code = $r->input('code');
            $contentType->name = $r->input('name');
            $contentType->save();
            DB::commit();
            return Response::created();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }

    public function update(Request $r, string $uid)
    {
        $contentType = TubeContentType::where('uid', $uid)->firstOrFail();
        $r->validate([
            'code' => 'bail|nullable|string|max:20',
            'name' => 'bail|required|string|max:50'
        ], [
            'code.max' => 'Kode maksimal 20 karakter',
            'name.required' => 'Masukkan nama jenis isi tabung',
            'name.max' => 'Nama maksimal 50 karakter'
        ]);

        DB::beginTransaction();
        try {
            $contentType->code = $r->input('code');
            $contentType->name = $r->input('name');
            $contentType->save();
            DB::commit();
            return Response::updated();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }

    public function delete(string $uid)
    {
        $contentType = TubeContentType::where('uid', $uid)->firstOrFail();

        DB::beginTransaction();
        try {
            if ($contentType->tubeContents()->exists()) {
                return Response::error(__('message.delete_fail_has_relationship'), 403);
            }
            $contentType->delete();
            DB::commit();
            return Response::deleted();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }
}
