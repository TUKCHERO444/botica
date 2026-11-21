<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permiso = Permission::firstOrCreate([
            'name' => 'datos-sistema',
            'guard_name' => 'web',
        ]);

        $administrador = Role::where('name', 'Administrador')->first();

        if ($administrador && ! $administrador->hasPermissionTo($permiso)) {
            $administrador->givePermissionTo($permiso);
        }
    }

    public function down(): void
    {
        // No se elimina el permiso para no romper roles personalizados que lo usen.
    }
};
