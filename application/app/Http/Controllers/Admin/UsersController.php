<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserCollection;
use App\Services\UserAdminService;
use Illuminate\Http\Request;

class UsersController extends Controller
{
    public function usersList(UserAdminService $adminService, Request $request)
    {
        return new UserCollection($adminService->getUserList(
            $request->input("perPage", 10),
            $request->all(),
        ));
    }
}
