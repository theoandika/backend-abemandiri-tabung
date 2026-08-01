<?php

namespace App\Http\Controllers;

use App\Helpers\Response;
use App\Http\Resources\AccountResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    public function detail(Request $r)
    {
        return new AccountResource($r->user());
    }

    public function updatePassword(Request $r)
    {
        $r->validate([
            'new_password' => 'bail|required|string|confirmed',
            'old_password' => 'bail|required',
        ], [
            'new_password.required' => 'Masukkan password baru',
            'new_password.confirmed' => 'Konfirmasi kata sandi tidak cocok',
            'old_password.required' => 'Masukkan kata sandi lama',
        ]);

        DB::beginTransaction();
        try {
            $user = $r->user();
            if (Hash::check($r->input('old_password'), $user->password)) {
                $user->password = Hash::make($r->input('new_password'));
                $user->save();
                DB::commit();
                return Response::updated();
            } else {
                return Response::error(__('message.password.invalid'), 401);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }
}
