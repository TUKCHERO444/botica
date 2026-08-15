<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtencionProductoLote extends Model
{
    protected $table = 'atencion_producto_lote';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'atencion_id_atencion',
        'producto_id_producto',
        'lote_id_lote',
        'cantidad',
    ];

    public function atencion()
    {
        return $this->belongsTo(Atencion::class, 'atencion_id_atencion', 'id_atencion');
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
