<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'Botica'))</title>
    <meta name="description" content="@yield('meta_description', 'Botica en línea')">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-stone-50 text-stone-800 antialiased flex flex-col">

    <header class="bg-emerald-700 text-white text-sm text-center py-1.5 overflow-hidden">
        <div class="marquee-container" id="header-marquee">
            <div class="marquee-track" id="header-marquee-track">
                <span class="marquee-content">{{ $headerTexts->isNotEmpty() ? $headerTexts->pluck('texto')->implode(' &bull; ') : 'Bienvenido a nuestra botica — Productos naturales y de calidad' }}</span>
            </div>
        </div>
    </header>

    <nav class="bg-white shadow-sm border-b border-stone-200 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 flex items-center gap-4 h-14">
            <a href="/" class="text-xl font-bold text-emerald-700 shrink-0">
                Botica
            </a>
            <div class="flex-1 flex items-center gap-2 relative">
                <input type="text" id="navbar-search" autocomplete="off" placeholder="Buscar productos..."
                    class="w-full max-w-md rounded-lg border border-stone-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <button id="navbar-search-btn" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-4 py-2 rounded-lg transition">
                    Buscar
                </button>
                <div id="navbar-search-suggestions"
                     class="absolute z-50 top-full mt-1 left-0 right-0 max-w-md bg-white border border-stone-200 rounded-lg shadow-lg hidden overflow-hidden">
                </div>
            </div>
            <div class="hidden md:flex items-center gap-1">
                <a href="{{ route('public.productos.index') }}"
                   class="text-stone-600 hover:text-emerald-700 px-3 py-2 text-sm font-medium transition rounded-lg hover:bg-emerald-50">
                    Nuestros productos
                </a>
                @guest('clientes')
                    <a href="{{ route('public.login') }}"
                       class="text-stone-600 hover:text-emerald-700 px-3 py-2 text-sm font-medium transition rounded-lg hover:bg-emerald-50">
                        Iniciar sesión
                    </a>
                    <a href="{{ route('public.registro') }}"
                       class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                        Registrarse
                    </a>
                @else
                    <a href="{{ route('public.perfil') }}"
                       class="text-stone-600 hover:text-emerald-700 px-3 py-2 text-sm font-medium transition rounded-lg hover:bg-emerald-50">
                        Mi Perfil
                    </a>
                    <span class="text-sm text-stone-600">Hola, {{ Auth::guard('clientes')->user()->nombre }}</span>
                    <form method="POST" action="{{ route('public.logout') }}" class="inline">
                        @csrf
                        <button type="submit"
                            class="text-stone-600 hover:text-emerald-700 px-3 py-2 text-sm font-medium transition rounded-lg hover:bg-emerald-50">
                            Cerrar sesión
                        </button>
                    </form>
                @endguest
                @guest('clientes')
                    <a href="{{ route('public.login') }}" class="relative text-stone-600 hover:text-emerald-700 p-2 transition" title="Carrito">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
                        </svg>
                    </a>
                @else
                    <a href="{{ route('public.carrito.index') }}" class="relative text-stone-600 hover:text-emerald-700 p-2 transition" title="Carrito">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
                        </svg>
                        <span id="cart-badge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1 {{ $cartCount > 0 ? '' : 'hidden' }}">
                            {{ $cartCount }}
                        </span>
                    </a>
                @endguest
            </div>
            @guest('clientes')
                <a href="{{ route('public.login') }}" class="relative md:hidden text-stone-600 hover:text-emerald-700 p-2" title="Carrito">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
                    </svg>
                </a>
            @else
                <a href="{{ route('public.carrito.index') }}" class="relative md:hidden text-stone-600 hover:text-emerald-700 p-2" title="Carrito">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
                    </svg>
                    <span id="cart-badge-mobile" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1 {{ $cartCount > 0 ? '' : 'hidden' }}">
                        {{ $cartCount }}
                    </span>
                </a>
            @endguest
            <button id="menu-toggle" class="md:hidden text-stone-600 hover:text-emerald-700 p-2" aria-label="Menú">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>
    </nav>

    <div id="mobile-menu" class="hidden md:hidden bg-white border-b border-stone-200 px-4 py-3">
        <div class="flex flex-col gap-1">
            <a href="{{ route('public.productos.index') }}"
               class="text-stone-600 hover:text-emerald-700 px-3 py-2 text-sm font-medium transition rounded-lg hover:bg-emerald-50 w-full text-left">
                Nuestros productos
            </a>
            @guest('clientes')
                <a href="{{ route('public.login') }}"
                   class="text-stone-600 hover:text-emerald-700 px-3 py-2 text-sm font-medium transition rounded-lg hover:bg-emerald-50 w-full text-left">
                    Iniciar sesión
                </a>
                <a href="{{ route('public.registro') }}"
                   class="text-stone-600 hover:text-emerald-700 px-3 py-2 text-sm font-medium transition rounded-lg hover:bg-emerald-50 w-full text-left">
                    Registrarse
                </a>
            @else
                <a href="{{ route('public.perfil') }}"
                   class="text-stone-600 hover:text-emerald-700 px-3 py-2 text-sm font-medium transition rounded-lg hover:bg-emerald-50 w-full text-left">
                    Mi Perfil
                </a>
                <span class="text-stone-600 px-3 py-2 text-sm w-full text-left">Hola, {{ Auth::guard('clientes')->user()->nombre }}</span>
                <form method="POST" action="{{ route('public.logout') }}">
                    @csrf
                    <button type="submit"
                        class="text-stone-600 hover:text-emerald-700 px-3 py-2 text-sm font-medium transition rounded-lg hover:bg-emerald-50 w-full text-left">
                        Cerrar sesión
                    </button>
                </form>
            @endguest
        </div>
    </div>

    <main class="flex-1">
        @yield('content')
    </main>

    <x-footer />

    @stack('scripts')
    @vite(['resources/js/public/menu-toggle.js', 'resources/js/public/navbar-search.js', 'resources/js/public/header-text.js'])
</body>
</html>
