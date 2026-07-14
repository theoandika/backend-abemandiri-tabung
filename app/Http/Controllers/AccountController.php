<?php

namespace App\Http\Controllers;

use App\Http\Resources\AccountResource;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function detail(Request $r)
    {
        return new AccountResource($r->user());
    }
}
