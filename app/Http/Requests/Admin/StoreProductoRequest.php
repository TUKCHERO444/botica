<?php

namespace App\Http\Requests\Admin;

use App\Models\UnidadMedida;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:50|unique:producto,nombre',
            'descripcion' => 'nullable|string|max:50',
            'precio_compra' => 'nullable|numeric|decimal:0,2|min:0',
            'precio_venta' => 'required|numeric|decimal:0,2|min:0',
            'estado' => 'required|integer|in:0,1',
            'stock_minimo' => 'required|numeric|decimal:0,3|min:0',
            'stock_maximo' => 'nullable|numeric|decimal:0,3|min:0',
            'presentacion_base_paso' => 'nullable|numeric|decimal:0,3|min:0.001|max:1',
            'unidad_base_id' => 'required|integer|exists:unidad_medida,id_unidad_medida,activo,1',
            'categoria_id_categoria' => 'required|integer|exists:categoria,id_categoria',
            'marca_id_marca' => 'required|integer|exists:marca,id_marca',
            'presentaciones' => 'nullable|array',
            'presentaciones.*.presentacion_id' => 'required|integer|distinct|exists:presentacion,id_presentacion,activo,1',
            'presentaciones.*.factor' => 'required|numeric|decimal:0,3|gt:0',
            'presentaciones.*.precio_venta' => 'required|numeric|decimal:0,2|min:0',
            'presentaciones.*.paso' => 'required|numeric|decimal:0,3|gt:0',
            'presentaciones.*.activo' => 'required|boolean',
            'presentaciones.*.codigo_barras' => 'nullable|string|max:50',
            'imagenes' => 'nullable|array|max:4',
            'imagenes.*' => 'required|file|mimes:jpg,jpeg,png,webp|max:2048',
            'orden_imagenes' => 'nullable|array',
            'orden_imagenes.*' => 'integer|min:0',
        ];
    }

    protected function prepareForValidation(): void
    {
        $presentaciones = $this->input('presentaciones');

        if (is_array($presentaciones)) {
            foreach ($presentaciones as $i => $presentacion) {
                if (! is_array($presentacion)) {
                    continue;
                }

                if (! isset($presentacion['paso']) || $presentacion['paso'] === '' || $presentacion['paso'] === null) {
                    $presentaciones[$i]['paso'] = 1;
                }
            }

            $this->merge(['presentaciones' => $presentaciones]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $unidad = UnidadMedida::find($this->input('unidad_base_id'));

            if ($unidad && ! $unidad->presentacion_base_id) {
                $validator->errors()->add(
                    'unidad_base_id',
                    'La unidad de medida seleccionada no tiene una presentación base configurada.'
                );

                return;
            }

            $baseId = $unidad?->presentacion_base_id;

            foreach ($this->input('presentaciones', []) as $i => $presentacion) {
                if ($baseId && (int) $presentacion['presentacion_id'] === (int) $baseId) {
                    $validator->errors()->add(
                        "presentaciones.{$i}.presentacion_id",
                        'La presentación base se crea automáticamente; no debe repetirse.'
                    );
                }

                if ((float) ($presentacion['paso'] ?? 0) > (float) ($presentacion['factor'] ?? 0)) {
                    $validator->errors()->add(
                        "presentaciones.{$i}.paso",
                        'El paso no puede ser mayor que el factor de la presentación.'
                    );
                }
            }

            if ($this->filled('stock_maximo')
                && (float) $this->input('stock_maximo') < (float) $this->input('stock_minimo')) {
                $validator->errors()->add(
                    'stock_maximo',
                    'El stock máximo no puede ser menor que el stock mínimo.'
                );
            }
        });
    }

    public function attributes(): array
    {
        return [
            'unidad_base_id' => 'unidad base',
            'stock_minimo' => 'stock mínimo',
            'presentacion_base_paso' => 'incremento mínimo de la presentación base',
            'presentaciones' => 'presentaciones',
            'presentaciones.*.presentacion_id' => 'presentación',
            'presentaciones.*.factor' => 'factor',
            'presentaciones.*.precio_venta' => 'precio de venta',
            'presentaciones.*.paso' => 'paso',
            'imagenes' => 'imágenes del producto',
            'imagenes.*' => 'imagen',
        ];
    }
}
