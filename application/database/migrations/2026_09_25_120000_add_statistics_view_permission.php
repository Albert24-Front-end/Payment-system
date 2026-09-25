<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permission = DB::table('permissions')->insertGetId(['name' => 'statistics.view']);
        // Admin is the only read-access role currently provisioned by this project.
        foreach (DB::table('roles')->where('name', 'admin')->pluck('id') as $role) {
            DB::table('permission_role')->insert(['permission_id' => $permission, 'role_id' => $role]);
        }
    }

    public function down(): void
    {
        // The permission_role foreign key cascades; other permissions/roles survive.
        DB::table('permissions')->where('name', 'statistics.view')->delete();
    }
};
