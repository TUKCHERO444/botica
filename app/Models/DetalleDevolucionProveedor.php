<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleDevolucionProveedor extends Model
{
    protected $table = 'detalle_devolucion_proveedor';

    protected $primaryKey = 'id_detalle_devolucion_proveedor';

    protected $fillable = [
        'devolucion_proveedor_id',
        'detalle_ingreso_id',
        'lote_ingreso_id',
        'producto_id_producto',
        'producto_presentacion_id',
        'producto_lote_id',
        'cantidad',
        'factor',
        'cantidad_base',
        'costo_unitario_base',
        'costo_total',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'factor' => 'decimal:3',
            'cantidad_base' => 'decimal:3',
            'costo_unitario_base' => 'decimal:4',
            'costo_total' => 'decimal:2',
        ];
    }

    public function devolucion()
    {
        return $this->belongsTo(DevolucionProveedor::class, 'devolucion_proveedor_id', 'id_devolucion_proveedor');
    }

    public function detalleIngreso()
    {
        return $this->belongsTo(DetalleIngreso::class, 'detalle_ingreso_id', 'id_detalle_ingreso');
    }

    public function capa()
    {
        return $this->belongsTo(LoteIngreso::class, 'lote_ingreso_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id_producto', 'id_producto');
    }

    public function presentacion()
    {
        return $this->belongsTo(ProductoPresentacion::class, 'producto_presentacion_id', 'id_producto_presentacion');
    }

    public function lote()
    {
        return $this->belongsTo(ProductoLote::class, 'producto_lote_id');
    }
}
