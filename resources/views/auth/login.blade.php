<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión - Botica</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-emerald-800 flex items-center justify-center p-4">

    <div class="w-full max-w-sm bg-white rounded-xl shadow-lg p-8">
        <h1 class="text-2xl font-bold text-center text-emerald-800 mb-2">Botica</h1>
        <p class="text-sm text-stone-500 text-center mb-6">Inicia sesión para administrar el sistema</p>

        <form method="POST" action="/login" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-stone-700 mb-1">Correo electrónico</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}"
                    class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 @error('email') border-red-500 @enderror"
                    placeholder="admin@sistema.com" required autofocus>
                @error('email')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-stone-700 mb-1">Contraseña</label>
                <input type="password" name="password" id="password"
                    class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 @error('password') border-red-500 @enderror"
                    placeholder="••••••••" required>
                @error('password')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-medium py-2.5 rounded-lg transition text-sm">
                Iniciar sesión
            </button>
        </form>

        <div class="mt-6 pt-4 border-t border-stone-200 text-center">
            <a href="{{ route('public.login') }}" class="text-sm text-emerald-600 hover:text-emerald-700 font-medium">
                ¿Eres cliente? Inicia sesión aquí
            </a>
        </div>
    </div>

</body>
</html>
