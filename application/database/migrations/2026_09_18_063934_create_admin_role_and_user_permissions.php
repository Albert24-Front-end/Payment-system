<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    const array USER_PERMISSIONS = [
        "users.view",
        "users.create",
        "users.update",
        "users.delete",
        "users.ban",
        "users.unban"
    ];
    public function up(): void
    {
        // переформатируем массив разрешений действий над юзерами
        $permissions = [];
        foreach(self::USER_PERMISSIONS as $permission){
            $permissions[] = Permission::create(["name" => $permission]);
        }

        // создаем роль админа и связываем ее с разрешениями
        $role = Role::create(["name" => "admin"]);
        $role->permissions()->attach($permissions);
    }

    public function down(): void
    {
        Permission::whereIn("name", self::USER_PERMISSIONS)->delete();
        Role::where("name", "admin")->delete();
    }
};
