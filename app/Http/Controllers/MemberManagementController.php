<?php

namespace App\Http\Controllers;

use App\Helpers\Response;
use App\Http\Resources\DetailMemberResource;
use App\Http\Resources\SimpleMemberResource;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberManagementController extends Controller
{
    public function all()
    {
        $members = Member::all();
        return SimpleMemberResource::collection($members);
    }

    public function index(Request $r)
    {
        $r->validate([
            'search' => 'bail|nullable|string|max:50',
            'paginate' => 'bail|nullable|integer|min:1'
        ]);

        try {
            $_members = Member::when($r->input('search'), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('code', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%')
                    ->orWhere('phone_number', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name');
            if ($r->filled('paginate')) {
                $members = $_members->paginate($r->integer('paginate'));
            } else {
                $members = $_members->get();
            }
            return DetailMemberResource::collection($members);
        } catch (\Throwable $th) {
            return Response::internalError($th->getMessage());
        }
    }

    public function detail(string $uid)
    {
        $member = Member::where('uid', $uid)->firstOrFail();
        return new DetailMemberResource($member);
    }

    public function create(Request $r)
    {
        $r->validate([
            'code' => 'bail|nullable|string|max:20',
            'name' => 'bail|required|string|max:100',
            'address' => 'bail|nullable|string|max:200',
            'phone_number' => 'bail|nullable|numeric|max_digits:15'
        ], [
            'code.max' => 'Kode member maksimal 20 karakter',
            'name.required' => 'Masukkan nama member',
            'name.max' => 'Nama member maksimal 100 karakter',
            'address.max' => 'Alamat maksimal 200 karakter',
            'phone_number.numeric' => 'Nomor telepon tidak valid',
            'phone_number.max_digits' => 'Nomor telepon maksimal 15 karakter'
        ]);

        DB::beginTransaction();
        try {
            $member = new Member;
            $member->code = $r->input('code');
            $member->name = $r->input('name');
            $member->address = $r->input('address');
            $member->phone_number = $r->input('phone_number');
            $member->save();
            DB::commit();
            return Response::created();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }

    public function update(Request $r, string $uid)
    {
        $member = Member::where('uid', $uid)->firstOrFail();
        $r->validate([
            'code' => 'bail|nullable|string|max:20',
            'name' => 'bail|required|string|max:100',
            'address' => 'bail|nullable|string|max:200',
            'phone_number' => 'bail|nullable|numeric|max_digits:15'
        ], [
            'code.max' => 'Kode member maksimal 20 karakter',
            'name.required' => 'Masukkan nama member',
            'name.max' => 'Nama member maksimal 100 karakter',
            'address.max' => 'Alamat maksimal 200 karakter',
            'phone_number.numeric' => 'Nomor telepon tidak valid',
            'phone_number.max_digits' => 'Nomor telepon maksimal 15 karakter'
        ]);

        DB::beginTransaction();
        try {
            $member->code = $r->input('code');
            $member->name = $r->input('name');
            $member->address = $r->input('address');
            $member->phone_number = $r->input('phone_number');
            $member->save();
            DB::commit();
            return Response::updated();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }

    public function delete(string $uid)
    {
        $member = Member::where('uid', $uid)->firstOrFail();
        DB::beginTransaction();
        try {
            if ($member->transactions()->exists() || $member->tubeTransactions()->exists()) {
                return Response::error(__('message.delete_fail_has_relationship'), 403);
            }
            $member->delete();
            DB::commit();
            return Response::deleted();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }
}
