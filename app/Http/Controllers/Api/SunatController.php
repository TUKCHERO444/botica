<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Sunat\SunatNotFoundException;
use App\Exceptions\Sunat\SunatServiceException;
use App\Http\Controllers\Controller;
use App\Models\Proveedor;
use App\Services\SunatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SunatController extends Controller
{
    public function __construct(
        private readonly SunatService $sunatService
    ) {}

    /**
     * Verifica si el RUC ya existe en la base local. No consume la API.
     */
    public function verificar(Request $request): JsonResponse
    {
        $request->validate([
            'ruc' => 'required|string|size:11|regex:/^\d{11}$/',
        ]);

        return response()->json([
            'success' => true,
            'exists' => Proveedor::where('ruc', $request->input('ruc'))->exists(),
        ]);
    }

    public function checkRuc(Request $request): JsonResponse
    {
        $request->validate([
            'ruc' => 'required|string|size:11|regex:/^\d{11}$/',
        ]);

        $ruc = $request->input('ruc');

        if (Proveedor::where('ruc', $ruc)->exists()) {
            return response()->json([
                'success' => true,
                'exists' => true,
                'message' => 'Este RUC ya está registrado.',
            ]);
        }

        try {
            $data = $this->sunatService->consultar($ruc);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (SunatNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (SunatServiceException $e) {
            logger()->error('SUNAT (público): Error de servicio', [
                'ruc' => substr($ruc, 0, 3).'*****'.substr($ruc, -3),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al consultar SUNAT. Verifique el RUC o intente más tarde.',
            ], 503);
        }
    }
}
