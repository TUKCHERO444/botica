<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\RegistroRequest;
use App\Models\Cliente;
use Illuminate\Support\Facades\Auth;

class RegistroController extends Controller
{
    public function showForm()
    {
        return view('public.auth.registro');
    }

    public function register(RegistroRequest $request)
    {
        $cliente = Cliente::create([
            'dni' => $request->dni,
            'nombre' => $request->nombre,
            'apellido_paterno' => $request->apellido_paterno,
            'apellido_materno' => $request->apellido_materno,
            'telefono' => $request->telefono,
            'email' => $request->email,
            'password' => $request->password,
            'registro_web' => true,
        ]);

        Auth::guard('clientes')->login($cliente);

        return redirect()->route('home')->with('success', 'Cuenta creada correctamente. ¡Bienvenido!');
    }
}
