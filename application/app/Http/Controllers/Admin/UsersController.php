<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\UserAdminService;

class UsersController extends Controller
{
    public function usersList(UserAdminService $adminService)
    {
        return [
           "success" => true,
           "data" => $adminService->getUserList()->toResourceCollection(UserResource::class),
        ];
    }
}
