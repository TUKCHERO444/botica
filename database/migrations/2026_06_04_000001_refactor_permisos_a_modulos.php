<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        $modulos = [
            'dashboard', 'productos', 'categorias', 'marcas', 'proveedores',
            'clientes', 'ventas', 'servicios', 'atenciones', 'ingresos',
            'usuarios', 'roles', 'caja', 'sliders',
        ];

        $acciones = ['ver', 'crear', 'editar', 'eliminar'];

        foreach ($modulos as $modulo) {
            $permisosViejos = array_map(fn($a) => "{$modulo}.{$a}", $acciones);

            $permisoNuevo = Permission::firstOrCreate([
                'name' => $modulo,
                'guard_name' => 'web',
            ]);

            $rolesConPermiso = DB::table('role_has_permissions')
                ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
                ->whereIn('permissions.name', $permisosViejos)
                ->distinct()
                ->pluck('role_has_permissions.role_id');

            $inserts = [];
            foreach ($rolesConPermiso as $roleId) {
                $yaTiene = DB::table('role_has_permissions')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permisoNuevo->id)
                    ->exists();

                if (!$yaTiene) {
                    $inserts[] = [
                        'role_id' => $roleId,
                        'permission_id' => $permisoNuevo->id,
                    ];
                }
            }

            if (!empty($inserts)) {
                DB::table('role_has_permissions')->insert($inserts);
            }

            Permission::whereIn('name', $permisosViejos)->delete();
        }

        $slidersGestionar = Permission::where('name', 'sliders.gestionar')->first();
        if ($slidersGestionar) {
            $roleIds = DB::table('role_has_permissions')
                ->where('permission_id', $slidersGestionar->id)
                ->pluck('role_id');

            $slidersPerm = Permission::where('name', 'sliders')->first();

            if ($slidersPerm && $roleIds->isNotEmpty()) {
                $inserts = [];
                foreach ($roleIds as $roleId) {
                    $yaTiene = DB::table('role_has_permissions')
                        ->where('role_id', $roleId)
                        ->where('permission_id', $slidersPerm->id)
                        ->exists();

                    if (!$yaTiene) {
                        $inserts[] = [
                            'role_id' => $roleId,
                            'permission_id' => $slidersPerm->id,
                        ];
                    }
                }

                if (!empty($inserts)) {
                    DB::table('role_has_permissions')->insert($inserts);
                }
            }

            $slidersGestionar->delete();
        }
    }

    public function down(): void
    {
    }
};
