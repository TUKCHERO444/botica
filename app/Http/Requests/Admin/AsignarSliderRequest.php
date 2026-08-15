<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AsignarSliderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('sliders') ?? false;
    }

    public function rules(): array
    {
        return [
            'producto_id' => 'required|integer|exists:producto,id_producto',
        ];
    }
}
