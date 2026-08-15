<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KardexMovimiento extends Model
{
    protected $table = 'kardex_movimientos';

    protected $fillable = [
        'producto_id',
        'usuario_id',
        'tipo_movimiento',
        'referencia_tipo',
        'referencia_id',
        'referencia_codigo',
        'cantidad',
        'stock_anterior',
        'stock_nuevo',
        'observacion',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'stock_anterior' => 'integer',
        'stock_nuevo' => 'integer',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id', 'id_producto');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
