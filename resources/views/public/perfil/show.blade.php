@extends('layouts.public')

@section('title', 'Mi Perfil')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">

    @if (session('success'))
        <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Card: Bienvenida --}}
    <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-6 mb-6 flex items-center gap-5">
        <div class="w-16 h-16 rounded-full bg-emerald-600 text-white flex items-center justify-center text-2xl font-bold shrink-0">
            {{ strtoupper(substr($cliente->nombre, 0, 1)) }}
        </div>
        <div>
            <h1 class="text-xl font-bold text-stone-800">¡Hola, {{ $cliente->nombre_completo }}!</h1>
            <div class="mt-1 space-y-0.5 text-sm text-stone-500">
                <p>
                    <svg class="inline w-4 h-4 mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Cliente desde {{ $cliente->created_at?->format('d/m/Y') ?? '—' }}
                </p>
                <p>
                    <svg class="inline w-4 h-4 mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    Último acceso {{ $cliente->ultimo_login?->format('d/m/Y g:i a') ?? '—' }}
                </p>
            </div>
        </div>
    </div>

    {{-- Card: Mis Datos Personales --}}
    <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-6 mb-6">
        <h2 class="text-lg font-bold text-stone-800 mb-5">Mis Datos Personales</h2>

        <form method="POST" action="{{ route('public.perfil.update') }}">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-stone-700 mb-1">DNI</label>
                    <div class="w-full rounded-lg border border-stone-200 bg-stone-100 px-4 py-2 text-sm text-stone-500">
                        {{ $cliente->dni ?? '—' }}
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-stone-700 mb-1">Nombres <span class="text-red-500">*</span></label>
                        <input type="text" name="nombre" value="{{ old('nombre', $cliente->nombre) }}" maxlength="50" required
                            class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none @error('nombre') border-red-400 @enderror">
                        @error('nombre')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-stone-700 mb-1">Ap. Paterno <span class="text-red-500">*</span></label>
                        <input type="text" name="apellido_paterno" value="{{ old('apellido_paterno', $cliente->apellido_paterno) }}" maxlength="25" required
                            class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none @error('apellido_paterno') border-red-400 @enderror">
                        @error('apellido_paterno')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-stone-700 mb-1">Ap. Materno <span class="text-red-500">*</span></label>
                        <input type="text" name="apellido_materno" value="{{ old('apellido_materno', $cliente->apellido_materno) }}" maxlength="25" required
                            class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none @error('apellido_materno') border-red-400 @enderror">
                        @error('apellido_materno')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-stone-700 mb-1">Correo electrónico <span class="text-red-500">*</span></label>
                        <input type="email" name="email" value="{{ old('email', $cliente->email) }}" maxlength="255" required
                            class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none @error('email') border-red-400 @enderror"
                            placeholder="correo@ejemplo.com">
                        @error('email')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-stone-700 mb-1">Teléfono <span class="text-red-500">*</span></label>
                        <input type="text" name="telefono" value="{{ old('telefono', $cliente->telefono) }}" maxlength="9" required
                            class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none @error('telefono') border-red-400 @enderror"
                            placeholder="987654321">
                        @error('telefono')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <button type="submit"
                    class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-6 py-2.5 rounded-lg transition text-sm">
                    Guardar cambios
                </button>
            </div>
        </form>
    </div>

    {{-- Card: Cambiar Contraseña --}}
    <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-6 mb-6">
        <h2 class="text-lg font-bold text-stone-800 mb-5">Cambiar Contraseña</h2>

        <form method="POST" action="{{ route('public.perfil.password') }}">
            @csrf
            @method('PUT')

            <div class="space-y-4 max-w-md">
                <div>
                    <label class="block text-sm font-medium text-stone-700 mb-1">Contraseña actual <span class="text-red-500">*</span></label>
                    <input type="password" name="current_password" required
                        class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none @error('current_password') border-red-400 @enderror"
                        placeholder="Ingrese su contraseña actual">
                    @error('current_password')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700 mb-1">Nueva contraseña <span class="text-red-500">*</span></label>
                    <input type="password" name="password" required minlength="8"
                        class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none @error('password') border-red-400 @enderror"
                        placeholder="Mínimo 8 caracteres">
                    @error('password')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700 mb-1">Confirmar nueva contraseña <span class="text-red-500">*</span></label>
                    <input type="password" name="password_confirmation" required minlength="8"
                        class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                        placeholder="Repita la nueva contraseña">
                </div>
            </div>

            <div class="mt-6">
                <button type="submit"
                    class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-6 py-2.5 rounded-lg transition text-sm">
                    Actualizar contraseña
                </button>
            </div>
        </form>
    </div>

    {{-- Card: Historial --}}
    <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-6">
        <h2 class="text-lg font-bold text-stone-800 mb-5">Historial</h2>

        <h3 class="text-sm font-semibold text-stone-700 mb-3">Últimas Compras</h3>
        @if ($cliente->ventas->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-stone-500 text-left">
                            <th class="pb-2 pr-4 font-medium">Código</th>
                            <th class="pb-2 pr-4 font-medium">Fecha</th>
                            <th class="pb-2 font-medium text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cliente->ventas as $venta)
                            <tr class="border-b border-stone-100">
                                <td class="py-2.5 pr-4 text-stone-800">{{ $venta->correlativo }}</td>
                                <td class="py-2.5 pr-4 text-stone-500">{{ $venta->created_at?->format('d/m/Y') }}</td>
                                <td class="py-2.5 text-right font-medium text-stone-800">S/ {{ number_format($venta->total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-stone-400 italic">No has realizado compras aún.</p>
        @endif

        <h3 class="text-sm font-semibold text-stone-700 mt-8 mb-3">Últimas Atenciones</h3>
        @if ($cliente->atenciones->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-stone-200 text-stone-500 text-left">
                            <th class="pb-2 pr-4 font-medium">Código</th>
                            <th class="pb-2 pr-4 font-medium">Fecha</th>
                            <th class="pb-2 font-medium text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cliente->atenciones as $atencion)
                            <tr class="border-b border-stone-100">
                                <td class="py-2.5 pr-4 text-stone-800">{{ $atencion->correlativo }}</td>
                                <td class="py-2.5 pr-4 text-stone-500">{{ $atencion->created_at?->format('d/m/Y') }}</td>
                                <td class="py-2.5 text-right font-medium text-stone-800">S/ {{ number_format($atencion->total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-stone-400 italic">No has recibido atenciones aún.</p>
        @endif
    </div>

</div>
@endsection
