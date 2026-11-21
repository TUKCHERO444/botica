<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoteConsumo extends Model
{
    protected $table = 'lote_consumo';

    protected $fillable = [
        'lote_ingreso_id',
        'producto_lote_id',
        'referencia_tipo',
        'referencia_id',
        'producto_presentacion_id',
        'cantidad_base',
        'costo_unitario_base',
        'costo_total',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_base' => 'decimal:3',
            'costo_unitario_base' => 'decimal:4',
            'costo_total' => 'decimal:2',
        ];
    }

    public function capa()
    {
        return $this->belongsTo(LoteIngreso::class, 'lote_ingreso_id');
    }

    public function lote()
    {
        return $this->belongsTo(ProductoLote::class, 'producto_lote_id');
    }

    public function presentacion()
    {
        return $this->belongsTo(ProductoPresentacion::class, 'producto_presentacion_id', 'id_producto_presentacion');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
