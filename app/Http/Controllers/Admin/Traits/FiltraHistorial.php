<?php

namespace App\Http\Controllers\Admin\Traits;

use Carbon\Carbon;
use Illuminate\Http\Request;

trait FiltraHistorial
{
    /**
     * Normaliza un filtro de fecha (Y-m-d). Un valor inválido se ignora
     * en lugar de responder 422, porque el panel se navega por AJAX.
     */
    protected function fechaFiltro(Request $request, string $campo): ?Carbon
    {
        $valor = $request->input($campo);

        if (! is_string($valor) || ! Carbon::hasFormat($valor, 'Y-m-d')) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $valor)->startOfDay();
    }

    /**
     * Devuelve [fecha, desde, hasta]. Una fecha exacta manda sobre el rango;
     * un rango invertido se intercambia.
     *
     * @return array{0: ?Carbon, 1: ?Carbon, 2: ?Carbon}
     */
    protected function rangoFechas(Request $request): array
    {
        $fecha = $this->fechaFiltro($request, 'fecha');
        $desde = $this->fechaFiltro($request, 'desde');
        $hasta = $this->fechaFiltro($request, 'hasta');

        if ($fecha) {
            return [$fecha, null, null];
        }

        if ($desde && $hasta && $hasta->lt($desde)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [null, $desde, $hasta];
    }
}
