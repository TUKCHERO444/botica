@extends('layouts.public')

@section('title', 'Iniciar sesión')

@section('content')
<div class="max-w-sm mx-auto px-4 py-12">
    <h1 class="text-2xl font-bold text-stone-800 mb-6 text-center">Iniciar sesión</h1>

    <form method="POST" action="{{ route('public.login') }}" class="space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Correo electrónico</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none @error('email') border-red-400 @enderror"
                placeholder="correo@ejemplo.com">
            @error('email')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Contraseña</label>
            <input type="password" name="password" required
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>

        <button type="submit"
            class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-4 py-2.5 rounded-lg transition">
            Iniciar sesión
        </button>

        <p class="text-sm text-stone-500 text-center">
            ¿No tienes cuenta?
            <a href="{{ route('public.registro') }}" class="text-emerald-600 hover:text-emerald-700 font-medium">Regístrate</a>
        </p>
    </form>
</div>
@endsection
