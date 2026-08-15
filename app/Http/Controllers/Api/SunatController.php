<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Sunat\SunatNotFoundException;
use App\Exceptions\Sunat\SunatServiceException;
use App\Http\Controllers\Controller;
use App\Services\SunatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SunatController extends Controller
{
    public function __construct(
        private readonly SunatService $sunatService
    ) {}

    public function checkRuc(Request $request): JsonResponse
    {
        $request->validate([
            'ruc' => 'required|string|size:11|regex:/^\d{11}$/',
        ]);

        $ruc = $request->input('ruc');

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
                'ruc' => substr($ruc, 0, 3) . '*****' . substr($ruc, -3),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al consultar SUNAT. Verifique el RUC o intente más tarde.',
            ], 503);
        }
    }
}
