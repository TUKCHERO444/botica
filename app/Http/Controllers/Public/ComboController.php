<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Combo;

class ComboController extends Controller
{
    public function show(Combo $combo)
    {
        if (!$combo->activo) {
            abort(404);
        }

        $combo->load('productos.categoria');

        return view('public.combos.show', compact('combo'));
    }
}
