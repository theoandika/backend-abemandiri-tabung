<?php

namespace App\Http\Controllers;

use App\Constants\Permission;

class RolePermissionController extends Controller
{
    public function all()
    {
        return (new Permission)->list();
    }
}
