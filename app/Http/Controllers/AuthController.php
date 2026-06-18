<?php

namespace App\Http\Controllers;

use App\Helpers\Response;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $r)
    {
        $r->validate([
            'email' => 'bail|required|email',
            'password' => 'bail|required|string'
        ], [
            'email.required' => 'Masukkan email yang valid',
            'password.required' => 'Masukkan password'
        ]);

        DB::beginTransaction();
        try {
            $user = User::where('email', $r->input('email'))->first();
            if ($user) {
                if ($user->is_active) {
                    if (Hash::check($r->input('password'), $user->password)) {
                        $token = $user->createToken('ho-app');
                        DB::commit();
                        return Response::successData([
                            'token' => $token->plainTextToken
                        ]);
                    }
                } else {
                    return Response::error(__('message.unauthenticated_not_active'), 401);
                }
            }

            return Response::error(__('message.unauthenticated'), 401);
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }

    public function logout(Request $r)
    {
        DB::beginTransaction();
        try {
            $r->user()->currentAccessToken()->delete();
            DB::commit();
            return Response::deleted();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }
}
