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
        'producto_presentacion_id',
        'cantidad_presentacion',
        'unidad_presentacion',
        'factor',
        'producto_lote_id',
        'lote_codigo',
        'fecha_vencimiento',
        'costo_unitario_base',
        'observacion',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'stock_anterior' => 'decimal:3',
        'stock_nuevo' => 'decimal:3',
        'cantidad_presentacion' => 'decimal:3',
        'factor' => 'decimal:3',
        'fecha_vencimiento' => 'date',
        'costo_unitario_base' => 'decimal:4',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id', 'id_producto');
    }

    public function productoPresentacion()
    {
        return $this->belongsTo(ProductoPresentacion::class, 'producto_presentacion_id', 'id_producto_presentacion');
    }

    public function productoLote()
    {
        return $this->belongsTo(ProductoLote::class, 'producto_lote_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
