<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHeaderTextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('portada.header-text') ?? false;
    }

    public function rules(): array
    {
        return [
            'texto' => 'required|string|max:60',
        ];
    }

    public function attributes(): array
    {
        return [
            'texto' => 'texto del header',
        ];
    }
}
