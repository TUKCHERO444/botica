<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleInventarioInicial extends Model
{
    protected $table = 'detalle_inventario_inicial';

    protected $primaryKey = 'id_detalle_inventario_inicial';

    protected $fillable = [
        'inventario_inicial_id',
        'producto_id',
        'producto_presentacion_id',
        'cantidad_presentacion',
        'factor',
        'codigo_lote',
        'fecha_vencimiento',
        'cantidad_base',
        'costo_unitario_base',
        'costo_unitario_presentacion',
        'costo_total',
        'producto_lote_id',
        'lote_ingreso_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_vencimiento' => 'date',
            'cantidad_presentacion' => 'decimal:3',
            'factor' => 'decimal:3',
            'cantidad_base' => 'decimal:3',
            'costo_unitario_base' => 'decimal:4',
            'costo_unitario_presentacion' => 'decimal:4',
            'costo_total' => 'decimal:2',
        ];
    }

    public function inventarioInicial()
    {
        return $this->belongsTo(
            InventarioInicial::class,
            'inventario_inicial_id',
            'id_inventario_inicial'
        );
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id', 'id_producto');
    }

    public function presentacion()
    {
        return $this->belongsTo(
            ProductoPresentacion::class,
            'producto_presentacion_id',
            'id_producto_presentacion'
        );
    }

    public function lote()
    {
        return $this->belongsTo(ProductoLote::class, 'producto_lote_id');
    }

    public function capa()
    {
        return $this->belongsTo(LoteIngreso::class, 'lote_ingreso_id');
    }
}
