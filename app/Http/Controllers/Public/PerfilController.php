<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\PerfilInfoRequest;
use App\Http\Requests\Public\PerfilPasswordRequest;
use App\Models\Atencion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class PerfilController extends Controller
{
    public function show()
    {
        $cliente = Auth::guard('clientes')->user()->load([
            'ventas' => function ($q) {
                $q->latest()->limit(10);
            },
            'atenciones' => function ($q) {
                $q->where('estado', Atencion::ESTADO_CONFIRMADA)
                    ->latest()
                    ->limit(10);
            },
        ]);

        return view('public.perfil.show', compact('cliente'));
    }

    public function updateInfo(PerfilInfoRequest $request)
    {
        $cliente = Auth::guard('clientes')->user();

        $cliente->update($request->validated());

        return redirect()->route('public.perfil')
            ->with('success', 'Datos actualizados correctamente.');
    }

    public function updatePassword(PerfilPasswordRequest $request)
    {
        $cliente = Auth::guard('clientes')->user();

        if (! Hash::check($request->current_password, $cliente->password)) {
            return back()->withErrors([
                'current_password' => 'La contraseña actual no es correcta.',
            ]);
        }

        $cliente->password = $request->password;
        $cliente->save();

        return redirect()->route('public.perfil')
            ->with('success', 'Contraseña actualizada correctamente.');
    }
}
