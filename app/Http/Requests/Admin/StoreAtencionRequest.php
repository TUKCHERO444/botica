<?php

namespace App\Http\Requests\Admin;

use App\Models\LoteProducto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class StoreAtencionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_id_cliente' => 'nullable|integer|exists:cliente,id_cliente|required_without:cliente_data',
            'cliente_data' => 'nullable|array|required_without:cliente_id_cliente',
            'cliente_data.dni' => 'required_with:cliente_data|string|size:8|unique:cliente,dni',
            'cliente_data.nombre' => 'required_with:cliente_data|string|max:50',
            'cliente_data.apellido_paterno' => 'required_with:cliente_data|string|max:25',
            'cliente_data.apellido_materno' => 'required_with:cliente_data|string|max:25',
            'observacio' => 'nullable|string|max:50',
            'productos' => 'nullable|array',
            'productos.*.producto_id_producto' => 'required_with:productos|integer|exists:producto,id_producto',
            'productos.*.cantidad' => 'required_with:productos|integer|min:1',
            'productos.*.precio' => 'required_with:productos|numeric|min:0',
            'servicios' => 'required|array|min:1',
            'servicios.*.servicio_id_servicio' => 'required|integer|exists:servicio,id_servicio',
            'servicios.*.precio' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'servicios.required' => 'Debe agregar al menos un servicio.',
            'servicios.min' => 'Debe agregar al menos un servicio.',
            'productos.*.cantidad.min' => 'La cantidad del producto debe ser al menos 1.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $productos = $this->input('productos', []);

            if (empty($productos)) return;

            foreach ($productos as $i => $p) {
                $producto = DB::table('producto')
                    ->where('id_producto', $p['producto_id_producto'])
                    ->first();

                if (!$producto) continue;

                if ($p['cantidad'] > $producto->stock) {
                    $validator->errors()->add(
                        "productos.{$i}.cantidad",
                        "\"{$producto->nombre}\" — Stock disponible: {$producto->stock}, solicitado: {$p['cantidad']}."
                    );
                    continue;
                }

                $lotesDisponibles = LoteProducto::where('producto_id_producto', $p['producto_id_producto'])
                    ->where('cantidad', '>', 0)
                    ->sum('cantidad');

                if ($p['cantidad'] > $lotesDisponibles) {
                    $validator->errors()->add(
                        "productos.{$i}.cantidad",
                        "\"{$producto->nombre}\" — Stock físico en lotes: {$lotesDisponibles}, solicitado: {$p['cantidad']}. Realice un ingreso para reponer inventario."
                    );
                }
            }
        });
    }
}
