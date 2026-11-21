<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleConciliacionLote extends Model
{
    protected $table = 'detalle_conciliacion_lote';

    protected $primaryKey = 'id_detalle_conciliacion_lote';

    protected $fillable = [
        'detalle_conciliacion_inventario_id',
        'producto_lote_id',
        'cantidad_lote',
        'suma_capas',
        'diferencia',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_lote' => 'decimal:3',
            'suma_capas' => 'decimal:3',
            'diferencia' => 'decimal:3',
        ];
    }

    public function detalle()
    {
        return $this->belongsTo(
            DetalleConciliacionInventario::class,
            'detalle_conciliacion_inventario_id',
            'id_detalle_conciliacion_inventario'
        );
    }

    public function lote()
    {
        return $this->belongsTo(ProductoLote::class, 'producto_lote_id');
    }
}
