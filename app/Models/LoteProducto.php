<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoteProducto extends Model
{
    protected $table = 'lote_producto';

    protected $primaryKey = 'id_lote';

    public $timestamps = false;

    protected $fillable = [
        'producto_id_producto',
        'codigo_lote',
        'fecha_vencimiento',
        'cantidad',
        'ingreso_id_ingreso',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id_producto', 'id_producto');
    }

    public function ingreso()
    {
        return $this->belongsTo(Ingreso::class, 'ingreso_id_ingreso', 'id_ingreso');
    }

    public function scopeDisponible($query)
    {
        return $query->where('cantidad', '>', 0);
    }

    public function scopeVigente($query)
    {
        return $query->where('fecha_vencimiento', '>', now());
    }

    public function scopeVencido($query)
    {
        return $query->where('fecha_vencimiento', '<=', now());
    }

    public function scopeProximoAVencer($query, int $dias = 30)
    {
        return $query->whereBetween('fecha_vencimiento', [now(), now()->addDays($dias)]);
    }
}
