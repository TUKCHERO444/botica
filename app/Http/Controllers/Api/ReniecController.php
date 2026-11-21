<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Reniec\ReniecNotFoundException;
use App\Exceptions\Reniec\ReniecServiceException;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Services\ReniecService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReniecController extends Controller
{
    public function __construct(
        private readonly ReniecService $reniecService
    ) {}

    /**
     * Verifica si el DNI ya existe en la base local. No devuelve datos
     * personales y no consume la API.
     */
    public function verificar(Request $request): JsonResponse
    {
        $request->validate([
            'dni' => 'required|string|size:8|regex:/^\d{8}$/',
        ]);

        return response()->json([
            'success' => true,
            'exists' => Cliente::where('dni', $request->input('dni'))->exists(),
        ]);
    }

    public function checkDni(Request $request): JsonResponse
    {
        $request->validate([
            'dni' => 'required|string|size:8|regex:/^\d{8}$/',
        ]);

        $dni = $request->input('dni');

        if (Cliente::where('dni', $dni)->exists()) {
            return response()->json([
                'success' => true,
                'exists' => true,
                'message' => 'Este DNI ya está registrado. Acércate a la botica para habilitar tu cuenta.',
            ]);
        }

        try {
            $data = $this->reniecService->consultar($dni);

            return response()->json([
                'success' => true,
                'data' => [
                    'nombre' => $data['nombre'],
                    'apellido_paterno' => $data['apellido_paterno'],
                    'apellido_materno' => $data['apellido_materno'],
                    'nombre_completo' => $data['nombre_completo'],
                ],
            ]);
        } catch (ReniecNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (ReniecServiceException $e) {
            logger()->error('RENIEC (público): Error de servicio', [
                'dni' => substr($dni, 0, 2).'****'.substr($dni, -2),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al consultar RENIEC. Verifique el DNI o intente más tarde.',
            ], 503);
        }
    }
}
