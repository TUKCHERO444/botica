<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('portada.banners') ?? false;
    }

    public function rules(): array
    {
        return [
            'imagen' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            'titulo' => 'nullable|string|max:255',
            'subtitulo' => 'nullable|string|max:255',
            'enlace' => 'nullable|url|max:255',
        ];
    }

    public function attributes(): array
    {
        return [
            'imagen' => 'imagen del banner',
            'titulo' => 'título',
            'subtitulo' => 'subtítulo',
            'enlace' => 'enlace',
        ];
    }
}
