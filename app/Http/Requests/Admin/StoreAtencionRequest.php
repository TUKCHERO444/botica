<?php

namespace App\Http\Requests\Admin;

use App\Helpers\CantidadHelper;
use App\Helpers\TotalesHelper;
use App\Http\Requests\Admin\Concerns\ValidaPagos;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use Illuminate\Foundation\Http\FormRequest;

class StoreAtencionRequest extends FormRequest
{
    use ValidaPagos;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'cliente_id_cliente' => 'nullable|integer|exists:cliente,id_cliente|required_without:cliente_data',
            'cliente_data' => 'nullable|array|required_without:cliente_id_cliente',
            'cliente_data.dni' => 'required_with:cliente_data|string|size:8|unique:cliente,dni',
            'cliente_data.nombre' => 'required_with:cliente_data|string|max:50',
            'cliente_data.apellido_paterno' => 'required_with:cliente_data|string|max:25',
            'cliente_data.apellido_materno' => 'required_with:cliente_data|string|max:25',
            'observacio' => 'nullable|string|max:50',
            'productos' => 'nullable|array',
            'productos.*.producto_presentacion_id' => 'required_with:productos|integer|exists:producto_presentacion,id_producto_presentacion',
            'productos.*.cantidad' => 'required_with:productos|numeric|decimal:0,3|gt:0',
            'productos.*.precio' => 'nullable|numeric|decimal:0,2|min:0',
            'servicios' => 'required|array|min:1',
            'servicios.*.servicio_id_servicio' => 'required|integer|exists:servicio,id_servicio',
            'servicios.*.precio' => 'required|numeric|decimal:0,2|min:0',
        ], $this->reglasPagos());
    }

    public function messages(): array
    {
        return array_merge([
            'servicios.required' => 'Debe agregar al menos un servicio.',
            'servicios.min' => 'Debe agregar al menos un servicio.',
            'productos.*.cantidad.gt' => 'La cantidad del producto debe ser mayor que cero.',
        ], $this->mensajesPagos());
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $productos = $this->input('productos', []);

            if (empty($productos)) {
                return;
            }

            $solicitado = [];

            foreach ($productos as $i => $p) {
                $presentacion = ProductoPresentacion::with('producto')
                    ->find($p['producto_presentacion_id'] ?? null);

                if (! $presentacion || ! $presentacion->activo || ! $presentacion->producto?->estado) {
                    $validator->errors()->add(
                        "productos.{$i}.producto_presentacion_id",
                        'La presentación seleccionada no está disponible.'
                    );

                    continue;
                }

                $cantidad = (string) $p['cantidad'];

                if (! CantidadHelper::esMultiplo($cantidad, $presentacion->paso)) {
                    $validator->errors()->add(
                        "productos.{$i}.cantidad",
                        "La cantidad debe ser múltiplo de {$presentacion->paso} para esta presentación."
                    );

                    continue;
                }

                $productoId = $presentacion->producto_id_producto;
                $cantidadBase = CantidadHelper::aBase($cantidad, $presentacion->factor);

                $solicitado[$productoId] = CantidadHelper::sumar(
                    $solicitado[$productoId] ?? 0,
                    $cantidadBase
                );

                $stockVendible = $presentacion->producto->stockVendible();

                if (CantidadHelper::comparar($cantidadBase, $stockVendible) > 0) {
                    $validator->errors()->add(
                        "productos.{$i}.cantidad",
                        "\"{$presentacion->producto->nombre}\" — Stock vendible: "
                        .CantidadHelper::formatear($stockVendible)
                        .', solicitado: '.CantidadHelper::formatear($cantidadBase).'.'
                    );
                }
            }

            foreach ($solicitado as $productoId => $cantidadBase) {
                $producto = Producto::find($productoId);

                if ($producto && CantidadHelper::comparar($cantidadBase, $producto->stockVendible()) > 0) {
                    $validator->errors()->add(
                        'productos',
                        "\"{$producto->nombre}\" — La suma solicitada supera el stock vendible disponible."
                    );
                }
            }
        });

        $validator->after(function ($validator) {
            $this->validarPagos(
                $validator,
                TotalesHelper::atencion($this->input('productos', []), $this->input('servicios', []))
            );
        });
    }
}
