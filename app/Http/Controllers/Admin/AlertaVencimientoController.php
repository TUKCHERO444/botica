<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoteProducto;

class AlertaVencimientoController extends Controller
{
    public function index()
    {
        $hoy = now()->startOfDay();

        $vencidos = LoteProducto::with('producto')
            ->where('cantidad', '>', 0)
            ->where('fecha_vencimiento', '<=', $hoy)
            ->orderBy('fecha_vencimiento')
            ->get();

        $criticos = LoteProducto::with('producto')
            ->where('cantidad', '>', 0)
            ->whereBetween('fecha_vencimiento', [$hoy->copy()->addDay(), $hoy->copy()->addDays(7)])
            ->orderBy('fecha_vencimiento')
            ->get();

        $proximos = LoteProducto::with('producto')
            ->where('cantidad', '>', 0)
            ->whereBetween('fecha_vencimiento', [$hoy->copy()->addDays(8), $hoy->copy()->addDays(30)])
            ->orderBy('fecha_vencimiento')
            ->get();

        $vigentes = LoteProducto::with('producto')
            ->where('cantidad', '>', 0)
            ->where('fecha_vencimiento', '>', $hoy->copy()->addDays(30))
            ->orderBy('fecha_vencimiento')
            ->get();

        if (request()->ajax()) {
            return view('admin.alertas.index', compact('vencidos', 'criticos', 'proximos', 'vigentes', 'hoy'));
        }

        return view('admin.alertas.full', compact('vencidos', 'criticos', 'proximos', 'vigentes', 'hoy'));
    }
}
