<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleAjusteInventario extends Model
{
    protected $table = 'detalle_ajuste_inventario';

    protected $primaryKey = 'id_detalle_ajuste_inventario';

    protected $fillable = [
        'ajuste_inventario_id',
        'producto_id_producto',
        'producto_lote_id',
        'producto_presentacion_id',
        'lote_ingreso_id',
        'cantidad_base',
        'costo_unitario_base',
        'costo_total',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_base' => 'decimal:3',
            'costo_unitario_base' => 'decimal:4',
            'costo_total' => 'decimal:2',
        ];
    }

    public function ajuste()
    {
        return $this->belongsTo(AjusteInventario::class, 'ajuste_inventario_id', 'id_ajuste_inventario');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id_producto', 'id_producto');
    }

    public function lote()
    {
        return $this->belongsTo(ProductoLote::class, 'producto_lote_id');
    }

    public function presentacion()
    {
        return $this->belongsTo(ProductoPresentacion::class, 'producto_presentacion_id', 'id_producto_presentacion');
    }

    public function capa()
    {
        return $this->belongsTo(LoteIngreso::class, 'lote_ingreso_id');
    }
}
