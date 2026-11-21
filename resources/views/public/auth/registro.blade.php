@extends('layouts.public')

@section('title', 'Crear cuenta')

@section('content')
<div class="max-w-lg mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-stone-800 mb-6">Crear cuenta</h1>

    <form method="POST" action="{{ route('public.registro') }}" class="space-y-4">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1">DNI <span class="text-red-500">*</span></label>
                <div class="flex gap-2">
                    <input type="text" name="dni" id="registro-dni-input" value="{{ old('dni') }}" maxlength="8" required
                        class="w-full min-w-0 flex-1 rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none @error('dni') border-red-400 @enderror"
                        placeholder="12345678">
                    <button type="button" id="btn-verificar-dni-registro"
                        class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium transition disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap"
                        disabled>
                        Verificar DNI
                    </button>
                    <button type="button" id="btn-buscar-dni-registro"
                        class="px-3 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-stone-300 text-white rounded-lg text-sm font-medium transition disabled:cursor-not-allowed flex items-center gap-1 whitespace-nowrap"
                        data-public="true"
                        disabled>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        Buscar DNI
                    </button>
                </div>
                @error('dni')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1">Nombres <span class="text-red-500">*</span></label>
                <input type="text" name="nombre" id="registro-nombre-input" value="{{ old('nombre') }}" maxlength="50" required
                    class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none @error('nombre') border-red-400 @enderror">
                @error('nombre')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1">Apellido Paterno <span class="text-red-500">*</span></label>
                <input type="text" name="apellido_paterno" id="registro-apellido-paterno-input" value="{{ old('apellido_paterno') }}" maxlength="25" required
                    class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none @error('apellido_paterno') border-red-400 @enderror">
                @error('apellido_paterno')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1">Apellido Materno <span class="text-red-500">*</span></label>
                <input type="text" name="apellido_materno" id="registro-apellido-materno-input" value="{{ old('apellido_materno') }}" maxlength="25" required
                    class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none @error('apellido_materno') border-red-400 @enderror">
                @error('apellido_materno')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1">Teléfono <span class="text-red-500">*</span></label>
                <input type="text" name="telefono" value="{{ old('telefono') }}" maxlength="9" required
                    class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none @error('telefono') border-red-400 @enderror"
                    placeholder="987654321">
                @error('telefono')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1">Correo electrónico <span class="text-red-500">*</span></label>
                <input type="email" name="email" value="{{ old('email') }}" maxlength="255" required
                    class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none @error('email') border-red-400 @enderror"
                    placeholder="correo@ejemplo.com">
                @error('email')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1">Contraseña <span class="text-red-500">*</span></label>
                <input type="password" name="password" required minlength="8"
                    class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none @error('password') border-red-400 @enderror"
                    placeholder="Mínimo 8 caracteres">
                @error('password')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1">Confirmar contraseña <span class="text-red-500">*</span></label>
                <input type="password" name="password_confirmation" required minlength="8"
                    class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                    placeholder="Repite la contraseña">
            </div>
        </div>

        <div class="pt-2">
            <button type="submit"
                class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-4 py-2.5 rounded-lg transition">
                Crear cuenta
            </button>
        </div>

        <p class="text-sm text-stone-500 text-center">
            ¿Ya tienes cuenta?
            <a href="{{ route('public.login') }}" class="text-emerald-600 hover:text-emerald-700 font-medium">Inicia sesión</a>
        </p>
    </form>
</div>
@endsection

@push('scripts')
    @vite('resources/js/public/registro.js')
@endpush
