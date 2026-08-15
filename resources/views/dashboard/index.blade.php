@extends('layouts.admin')

@section('title', 'Dashboard - Botica')

@section('content')
    <h1 class="text-2xl font-bold text-stone-800 mb-6">Dashboard</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-6">
            <p class="text-sm text-stone-500 font-medium">Productos</p>
            <p class="text-3xl font-bold text-stone-800 mt-1">0</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-6">
            <p class="text-sm text-stone-500 font-medium">Clientes</p>
            <p class="text-3xl font-bold text-stone-800 mt-1">0</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-6">
            <p class="text-sm text-stone-500 font-medium">Ventas hoy</p>
            <p class="text-3xl font-bold text-stone-800 mt-1">0</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-6">
            <p class="text-sm text-stone-500 font-medium">Atenciones hoy</p>
            <p class="text-3xl font-bold text-stone-800 mt-1">0</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-6">
        <h2 class="text-lg font-semibold text-stone-800 mb-4">Actividad reciente</h2>
        <p class="text-stone-500 text-sm">No hay actividad reciente.</p>
    </div>
@endsection
