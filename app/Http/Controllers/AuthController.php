<?php

namespace App\Http\Controllers;

use App\Support\InicioPanel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Cambio de cuenta: si ya hay una sesión web abierta (por ejemplo otro
        // usuario en el mismo navegador), se cierra antes de autenticar al nuevo.
        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if (Auth::attempt(['activo' => true] + $credentials)) {
            $request->session()->regenerate();

            // Destino según permisos (no siempre el dashboard) y respetando la
            // URL pedida antes del login solo si el rol puede entrar.
            return redirect()->to(InicioPanel::destino($request, Auth::user()));
        }

        return back()->withErrors([
            'email' => 'Las credenciales no coinciden.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
