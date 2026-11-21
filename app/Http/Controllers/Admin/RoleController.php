<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Admin\Traits\CacheaPanel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use Audita, CacheaPanel;

    public function index()
    {
        if (request()->ajax()) {
            return $this->getIndex('roles', function () {
                $roles = Role::with('permissions')->orderBy('id', 'desc')->paginate(10)->withQueryString();

                return view('admin.roles.index', compact('roles'))->render();
            });
        }

        $roles = Role::with('permissions')->orderBy('id', 'desc')->paginate(10)->withQueryString();

        return view('admin.roles.full', compact('roles'));
    }

    public function create()
    {
        if (request()->ajax()) {
            $permisos = Permission::orderBy('name')->get();

            return view('admin.roles.create', compact('permisos'));
        }

        return redirect()->route('admin.roles.index');
    }

    public function store(StoreRoleRequest $request)
    {
        $data = $request->validated();

        $role = Role::create(['name' => $data['name']]);
        $role->syncPermissions($data['permissions']);
        $this->auditarCrear($role, 'roles', "Creó el rol '{$role->name}'");

        $this->olvidaIndex('roles');

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Rol creado correctamente',
                'role' => $role->load('permissions'),
            ]);
        }

        return redirect()->route('admin.roles.index')->with('success', 'Rol creado correctamente');
    }

    public function edit(Role $role)
    {
        if (request()->ajax()) {
            $permisos = Permission::orderBy('name')->get();
            $rolePermissions = $role->permissions->pluck('name')->toArray();

            return view('admin.roles.edit', compact('role', 'permisos', 'rolePermissions'));
        }

        return redirect()->route('admin.roles.index');
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        if ($role->name === 'Administrador') {
            if (request()->ajax()) {
                return response()->json(['success' => false, 'message' => 'No puedes editar el rol Administrador'], 422);
            }

            return redirect()->route('admin.roles.index')->with('error', 'No puedes editar el rol Administrador');
        }

        $data = $request->validated();
        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions']);
        $this->auditarActualizar($role, 'roles', "Actualizó el rol '{$role->name}'");

        $this->olvidaIndex('roles');

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Rol actualizado correctamente',
                'role' => $role->fresh()->load('permissions'),
            ]);
        }

        return redirect()->route('admin.roles.index')->with('success', 'Rol actualizado correctamente');
    }

    public function destroy(Role $role)
    {
        if ($role->name === 'Administrador') {
            if (request()->ajax()) {
                return response()->json(['success' => false, 'message' => 'No puedes eliminar el rol Administrador'], 422);
            }

            return redirect()->route('admin.roles.index')->with('error', 'No puedes eliminar el rol Administrador');
        }

        $nombre = $role->name;
        $role->delete();
        $this->auditarEliminar($role, 'roles', "Eliminó el rol '{$nombre}'");

        $this->olvidaIndex('roles');

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Rol eliminado correctamente',
            ]);
        }

        return redirect()->route('admin.roles.index')->with('success', 'Rol eliminado correctamente');
    }
}
