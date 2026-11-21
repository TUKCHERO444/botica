<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleConciliacionInventario extends Model
{
    protected $table = 'detalle_conciliacion_inventario';

    protected $primaryKey = 'id_detalle_conciliacion_inventario';

    protected $fillable = [
        'conciliacion_inventario_id',
        'producto_id',
        'stock_cache',
        'stock_lotes',
        'diferencia',
        'lotes_inconsistentes',
    ];

    protected function casts(): array
    {
        return [
            'stock_cache' => 'decimal:3',
            'stock_lotes' => 'decimal:3',
            'diferencia' => 'decimal:3',
        ];
    }

    public function conciliacion()
    {
        return $this->belongsTo(
            ConciliacionInventario::class,
            'conciliacion_inventario_id',
            'id_conciliacion_inventario'
        );
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id', 'id_producto');
    }

    public function detallesLote()
    {
        return $this->hasMany(
            DetalleConciliacionLote::class,
            'detalle_conciliacion_inventario_id',
            'id_detalle_conciliacion_inventario'
        )->orderBy('id_detalle_conciliacion_lote');
    }
}
