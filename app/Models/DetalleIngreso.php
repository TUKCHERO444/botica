<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleIngreso extends Model
{
    protected $table = 'detalle_ingreso';

    protected $primaryKey = 'id_detalle_ingreso';

    public $timestamps = false;

    protected $fillable = [
        'ingreso_id_ingreso',
        'producto_id_producto',
        'producto_presentacion_id',
        'producto_lote_id',
        'lote_codigo',
        'fecha_vencimiento',
        'cantidad',
        'factor',
        'cantidad_base',
        'costo_unitario',
        'costo_unitario_base',
        'costo_total',
    ];

    protected function casts(): array
    {
        return [
            'fecha_vencimiento' => 'date',
            'cantidad' => 'decimal:3',
            'factor' => 'decimal:3',
            'cantidad_base' => 'decimal:3',
            'costo_unitario' => 'decimal:4',
            'costo_unitario_base' => 'decimal:4',
            'costo_total' => 'decimal:2',
        ];
    }

    public function ingreso()
    {
        return $this->belongsTo(Ingreso::class, 'ingreso_id_ingreso', 'id_ingreso');
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

    public function loteIngreso()
    {
        return $this->hasOne(LoteIngreso::class, 'detalle_ingreso_id', 'id_detalle_ingreso');
    }
}
