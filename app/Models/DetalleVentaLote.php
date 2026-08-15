<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleVentaLote extends Model
{
    protected $table = 'detalle_venta_lote';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'venta_id_venta',
        'producto_id_producto',
        'lote_id_lote',
        'cantidad',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id_venta', 'id_venta');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id_producto', 'id_producto');
    }

    public function lote()
    {
        return $this->belongsTo(LoteProducto::class, 'lote_id_lote', 'id_lote');
    }
}
