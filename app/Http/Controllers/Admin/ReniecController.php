<?php

namespace App\Http\Controllers\Admin;

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

    public function checkDni(Request $request): JsonResponse
    {
        $request->validate([
            'dni' => 'required|string|size:8|regex:/^\d{8}$/',
        ]);

        $dni = $request->input('dni');

        $cliente = Cliente::where('dni', $dni)->first([
            'id_cliente', 'nombre', 'apellido_paterno',
            'apellido_materno', 'dni', 'registro_web',
        ]);

        if ($cliente) {
            return response()->json([
                'success' => true,
                'exists' => true,
                'cliente' => [
                    'id_cliente' => $cliente->id_cliente,
                    'nombre' => $cliente->nombre,
                    'apellido_paterno' => $cliente->apellido_paterno,
                    'apellido_materno' => $cliente->apellido_materno,
                    'dni' => $cliente->dni,
                    'registro_web' => $cliente->registro_web,
                ],
            ]);
        }

        try {
            $data = $this->reniecService->consultar($dni);

            return response()->json([
                'success' => true,
                'exists' => false,
                'data' => [
                    'dni' => $data['dni'],
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
            logger()->error('RENIEC: Error de servicio', [
                'dni' => substr($dni, 0, 2) . '****' . substr($dni, -2),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al consultar RENIEC. Verifique el DNI o intente más tarde.',
            ], 503);
        }
    }
}
