<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class UpdateVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_id_cliente' => 'required|integer|exists:cliente,id_cliente',
            'productos' => 'required|array|min:1',
            'productos.*.producto_id_producto' => 'required|integer|exists:producto,id_producto',
            'productos.*.cantidad' => 'required|integer|min:1',
            'productos.*.precio' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'productos.required' => 'Debe agregar al menos un producto.',
            'productos.min' => 'Debe agregar al menos un producto.',
            'productos.*.cantidad.min' => 'La cantidad debe ser al menos 1.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $venta = $this->route('venta');

            if (! $venta || ! $venta->relationLoaded('productos')) {
                $venta->load('productos');
            }

            $oldCantidades = $venta->productos->mapWithKeys(fn ($p) => [
                $p->id_producto => $p->pivot->cantidad,
            ]);

            $productos = $this->input('productos', []);

            foreach ($productos as $i => $p) {
                $producto = DB::table('producto')
                    ->where('id_producto', $p['producto_id_producto'])
                    ->first();

                if (! $producto) {
                    continue;
                }

                $disponible = $producto->stock + ($oldCantidades[$p['producto_id_producto']] ?? 0);

                if ($p['cantidad'] > $disponible) {
                    $validator->errors()->add(
                        "productos.{$i}.cantidad",
                        "\"{$producto->nombre}\" — Stock disponible: {$disponible}, solicitado: {$p['cantidad']}."
                    );
                }
            }
        });
    }
}
