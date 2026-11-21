<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Combo extends Model
{
    protected $table = 'combo';

    protected $primaryKey = 'id_combo';

    protected $fillable = [
        'nombre',
        'descripcion',
        'imagen_url',
        'public_id',
        'precio_sugerido',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'precio_sugerido' => 'decimal:2',
    ];

    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'combo_producto',
            'combo_id_combo', 'producto_id_producto')
            ->withPivot('id_combo_producto', 'producto_presentacion_id', 'cantidad');
    }
}
