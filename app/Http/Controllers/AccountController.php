<?php

namespace App\Http\Controllers;

use App\Http\Resources\SimpleUserResource;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function detail(Request $r)
    {
        return new SimpleUserResource($r->user());
    }
}
