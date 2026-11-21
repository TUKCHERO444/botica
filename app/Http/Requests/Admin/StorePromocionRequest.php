<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePromocionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('portada.promociones') ?? false;
    }

    public function rules(): array
    {
        return [
            'imagen' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            'titulo' => 'nullable|string|max:255',
            'subtitulo' => 'nullable|string|max:255',
            'enlace' => 'nullable|url|max:255',
            'tipo_layout' => 'required|in:principal,secundaria,terciaria',
            'tipo_promocion' => 'nullable|in:descuento,combo',
            'ambito' => 'nullable|required_if:tipo_promocion,descuento|in:producto,marca,categoria',
            'ambito_id' => 'nullable|required_if:tipo_promocion,descuento|integer',
            'descuento_tipo' => 'nullable|required_if:tipo_promocion,descuento|in:porcentaje,fijo',
            'descuento_valor' => 'nullable|required_if:tipo_promocion,descuento|numeric|decimal:0,2|min:0|max:999999',
            'combo_id_combo' => 'nullable|required_if:tipo_promocion,combo|integer|exists:combo,id_combo',
        ];
    }

    public function attributes(): array
    {
        return [
            'imagen' => 'imagen de la promoción',
            'titulo' => 'título',
            'subtitulo' => 'subtítulo',
            'enlace' => 'enlace',
            'tipo_layout' => 'tipo layout',
            'tipo_promocion' => 'tipo de promoción',
            'ambito' => 'ámbito',
            'ambito_id' => 'entidad',
            'descuento_tipo' => 'tipo de descuento',
            'descuento_valor' => 'valor del descuento',
            'combo_id_combo' => 'combo',
        ];
    }
}
