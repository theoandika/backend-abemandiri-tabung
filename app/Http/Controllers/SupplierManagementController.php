<?php

namespace App\Http\Controllers;

use App\Helpers\Response;
use App\Http\Resources\DetailSupplierResource;
use App\Http\Resources\SimpleSupplierResource;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierManagementController extends Controller
{
    public function all()
    {
        $suppliers = Supplier::orderBy('code')->orderBy('name')->get();
        return SimpleSupplierResource::collection($suppliers);
    }

    public function index(Request $r)
    {
        $r->validate([
            'search' => 'bail|nullable|string|max:50',
            'paginate' => 'bail|nullable|integer|min:1'
        ]);

        try {
            $_supplier = Supplier::when($r->input('search'), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('code', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name');
            if ($r->filled('paginate')) {
                $suppliers = $_supplier->paginate($r->integer('paginate'));
            } else {
                $suppliers = $_supplier->get();
            }
            return DetailSupplierResource::collection($suppliers);
        } catch (\Throwable $th) {
            return Response::internalError($th->getMessage());
        }
    }

    public function detail(string $uid)
    {
        $supplier = Supplier::where('uid', $uid)->firstOrFail();
        return new DetailSupplierResource($supplier);
    }

    public function create(Request $r)
    {
        $r->validate([
            'code' => 'bail|nullable|string|max:20',
            'name' => 'bail|required|string|max:100',
            'description' => 'bail|nullable|string|max:200'
        ], [
            'code.max' => 'Kode supplier maksimal 20 karakter',
            'name.required' => 'Masukkan nama supplier',
            'name.max' => 'Nama supplier maksimal 100 karakter',
            'description.max' => 'Dekripsi maksimal 200 karakter'
        ]);

        DB::beginTransaction();
        try {
            $supplier = new Supplier;
            $supplier->code = $r->input('code');
            $supplier->name = $r->input('name');
            $supplier->description = $r->input('description');
            $supplier->save();
            DB::commit();
            return Response::created();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }

    public function update(Request $r, string $uid)
    {
        $supplier = Supplier::where('uid', $uid)->firstOrFail();
        $r->validate([
            'code' => 'bail|nullable|string|max:20',
            'name' => 'bail|required|string|max:100',
            'description' => 'bail|nullable|string|max:200'
        ], [
            'code.max' => 'Kode supplier maksimal 20 karakter',
            'name.required' => 'Masukkan nama supplier',
            'name.max' => 'Nama supplier maksimal 100 karakter',
            'description.max' => 'Dekripsi maksimal 200 karakter'
        ]);

        DB::beginTransaction();
        try {
            $supplier->code = $r->input('code');
            $supplier->name = $r->input('name');
            $supplier->description = $r->input('description');
            $supplier->save();
            DB::commit();
            return Response::updated();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }

    public function delete(string $uid)
    {
        $supplier = Supplier::where('uid', $uid)->firstOrFail();
        DB::beginTransaction();
        try {
            if ($supplier->supplierTransactions()->exists() || $supplier->tubeTransactions()->exists()) {
                return Response::error(__('message.delete_fail_has_relationship'), 403);
            }
            $supplier->delete();
            DB::commit();
            return Response::deleted();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }
}
