<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class UpdateAtencionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_id_cliente' => 'required|integer|exists:cliente,id_cliente',
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
            'productos.*.cantidad.min' => 'La cantidad del producto debe ser al menos 1.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $productos = $this->input('productos', []);

            if (empty($productos)) {
                return;
            }

            $atencion = $this->route('atencione');

            if (! $atencion || ! $atencion->relationLoaded('productos')) {
                $atencion->load('productos');
            }

            $oldCantidades = $atencion->productos->mapWithKeys(fn ($p) => [
                $p->id_producto => $p->pivot->cantidad,
            ]);

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
