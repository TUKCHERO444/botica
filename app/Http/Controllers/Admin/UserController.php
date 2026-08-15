<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Admin\Traits\CacheaPanel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use CacheaPanel, Audita;

    public function index()
    {
        if (request()->ajax()) {
            return $this->getIndex('usuarios', function () {
                $users = User::with('roles')->orderBy('id', 'desc')->get();
                return view('admin.usuarios.index', compact('users'))->render();
            });
        }

        $users = User::with('roles')->orderBy('id', 'desc')->get();
        return view('admin.usuarios.full', compact('users'));
    }

    public function create()
    {
        if (request()->ajax()) {
            $roles = Role::orderBy('name')->pluck('name', 'name');
            return view('admin.usuarios.create', compact('roles'));
        }

        return redirect()->route('admin.usuarios.index');
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        $roleName = $data['role'];
        unset($data['role']);
        $data['password'] = bcrypt($data['password']);

        $user = User::create($data);
        $user->assignRole($roleName);
        $this->auditarCrear($user, 'usuarios', "Creó al usuario '{$user->name}' ({$user->email})");

        $this->olvidaIndex('usuarios');

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Usuario creado correctamente',
                'user' => $user->load('roles'),
            ]);
        }

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuario creado correctamente');
    }

    public function edit(User $usuario)
    {
        if (request()->ajax()) {
            $roles = Role::orderBy('name')->pluck('name', 'name');
            $userRole = $usuario->roles->first()?->name;
            return view('admin.usuarios.edit', compact('usuario', 'roles', 'userRole'));
        }

        return redirect()->route('admin.usuarios.index');
    }

    public function update(UpdateUserRequest $request, User $usuario)
    {
        $data = $request->validated();
        $roleName = $data['role'];
        unset($data['role']);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = bcrypt($data['password']);
        }

        $roleChanged = false;
        if ($usuario->id === auth()->id()) {
            $currentRole = $usuario->roles->first()?->name;
            if ($roleName !== $currentRole) {
                $roleName = $currentRole;
                $roleChanged = true;
            }
        }

        $usuario->update($data);
        $usuario->syncRoles([$roleName]);
        $this->auditarActualizar($usuario, 'usuarios', "Actualizó al usuario '{$usuario->name}' ({$usuario->email})");

        $this->olvidaIndex('usuarios');

        $message = 'Usuario actualizado correctamente';
        if ($roleChanged) {
            $message = 'Usuario actualizado correctamente. No puedes cambiarte tu propio rol.';
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'user' => $usuario->fresh()->load('roles'),
            ]);
        }

        return redirect()->route('admin.usuarios.index')->with('success', $message);
    }

    public function toggleStatus(User $usuario)
    {
        if ($usuario->id === auth()->id()) {
            if (request()->ajax()) {
                return response()->json(['success' => false, 'message' => 'No puedes desactivarte a ti mismo'], 422);
            }
            return redirect()->route('admin.usuarios.index')->with('error', 'No puedes desactivarte a ti mismo');
        }

        $nuevoEstado = $usuario->activo ? 0 : 1;
        $usuario->update(['activo' => $nuevoEstado]);
        $verbo = $nuevoEstado ? 'Activó' : 'Desactivó';
        $this->auditar('usuarios', 'toggle-estado', $usuario->id, "{$verbo} al usuario '{$usuario->name}' ({$usuario->email})");

        $this->olvidaIndex('usuarios');

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $nuevoEstado ? 'Usuario activado correctamente' : 'Usuario desactivado correctamente',
            ]);
        }

        return redirect()->route('admin.usuarios.index');
    }

    public function destroy(User $usuario)
    {
        if ($usuario->id === auth()->id()) {
            if (request()->ajax()) {
                return response()->json(['success' => false, 'message' => 'No puedes desactivarte a ti mismo'], 422);
            }
            return redirect()->route('admin.usuarios.index')->with('error', 'No puedes desactivarte a ti mismo');
        }

        $usuario->update(['activo' => false]);
        $this->auditarEliminar($usuario, 'usuarios', "Desactivó al usuario '{$usuario->name}' ({$usuario->email})");

        $this->olvidaIndex('usuarios');

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Usuario desactivado correctamente',
            ]);
        }

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuario desactivado correctamente');
    }
}
