<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoteIngreso extends Model
{
    protected $table = 'lote_ingreso';

    protected $fillable = [
        'producto_lote_id',
        'ingreso_id',
        'detalle_ingreso_id',
        'producto_presentacion_id',
        'cantidad_presentacion',
        'factor',
        'cantidad_base',
        'cantidad_disponible_base',
        'costo_unitario_base',
        'costo_total',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_presentacion' => 'decimal:3',
            'factor' => 'decimal:3',
            'cantidad_base' => 'decimal:3',
            'cantidad_disponible_base' => 'decimal:3',
            'costo_unitario_base' => 'decimal:4',
            'costo_total' => 'decimal:2',
        ];
    }

    public function lote()
    {
        return $this->belongsTo(ProductoLote::class, 'producto_lote_id');
    }

    public function ingreso()
    {
        return $this->belongsTo(Ingreso::class, 'ingreso_id', 'id_ingreso');
    }

    public function presentacion()
    {
        return $this->belongsTo(ProductoPresentacion::class, 'producto_presentacion_id', 'id_producto_presentacion');
    }

    public function consumos()
    {
        return $this->hasMany(LoteConsumo::class, 'lote_ingreso_id');
    }

    public function scopeDisponible($query)
    {
        return $query->where('cantidad_disponible_base', '>', 0);
    }
}
